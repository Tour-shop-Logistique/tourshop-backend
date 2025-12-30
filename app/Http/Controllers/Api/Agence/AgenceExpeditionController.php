<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use App\Models\Expedition;
use App\Models\Agence;
use App\Models\TarifAgenceGroupage;
use App\Models\User;
use App\Models\Colis;
use App\Models\ColisArticle;
use App\Models\ContactExpedition;
use App\Services\ExpeditionTarificationService;
use App\Services\ZoneService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Enums\ExpeditionStatus;
use App\Enums\TypeExpedition;
use App\Enums\StatutPaiement;
use App\Enums\UserType;
use Illuminate\Support\Facades\Log;

class AgenceExpeditionController extends Controller
{
    protected ExpeditionTarificationService $expeditionTarificationService;
    protected ZoneService $zoneService;

    public function __construct(ExpeditionTarificationService $expeditionTarificationService, ZoneService $zoneService)
    {
        $this->expeditionTarificationService = $expeditionTarificationService;
        $this->zoneService = $zoneService;
    }

    /**
     * Lister les expéditions de l'agence
     */
    public function listerExpeditions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence non trouvée'], 404);
            }

            $query = Expedition::pourAgence($agence->id)
                ->with(['expediteurContact', 'destinataireContact', 'livreurEnlevement', 'livreurDeplacement', 'livreurLivraison', 'zoneDepart', 'zoneDestination']);

            // Filtrage par statut
            if ($request->has('statut_expedition') && $request->statut_expedition) {
                $query->where('statut_expedition', $request->statut_expedition);
            }

            // Filtrage par type d'expédition
            if ($request->has('type_expedition') && $request->type_expedition) {
                $query->where('type_expedition', $request->type_expedition);
            }

            // Filtrage par date
            if ($request->has('date_debut') && $request->date_debut) {
                $query->whereDate('created_at', '>=', $request->date_debut);
            }
            if ($request->has('date_fin') && $request->date_fin) {
                $query->whereDate('created_at', '<=', $request->date_fin);
            }

            // Recherche par référence ou code suivi
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('code_suivi_expedition', 'like', "%{$search}%");
                });
            }

            $expeditions = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Liste des expéditions récupérée avec succès',
                'expeditions' => $expeditions
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur listing expéditions agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des expéditions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle expédition (côté agence) avec expéditeur et destinataire
     */
    public function creerExpedition(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence non trouvée'], 404);
            }

            $validator = Validator::make($request->all(), [
                'user_id' => 'nullable|uuid|exists:users,id',
                'pays_depart' => ['required', 'string', 'max:150'],
                'pays_destination' => ['required', 'string', 'max:150'],
                'type_expedition' => ['required', 'string', 'in:' . implode(',', array_map(fn($case) => $case->value, TypeExpedition::cases()))],
                'is_paiement_credit' => ['nullable', 'boolean'],
                'is_livraison_domicile' => ['nullable', 'boolean'],
                'statut_paiement' => ['nullable', 'string', 'in:' . implode(',', array_map(fn($case) => $case->value, StatutPaiement::cases()))],

                // Validation Expéditeur
                'expediteur_nom_prenom' => ['required', 'string', 'max:255'],
                'expediteur_telephone' => ['required', 'string', 'max:20'],
                'expediteur_email' => ['nullable', 'email', 'max:255'],
                'expediteur_adresse' => ['required', 'string', 'max:255'],
                'expediteur_ville' => ['required', 'string', 'max:255'],
                'expediteur_pays' => ['required', 'string', 'max:150'],
                'expediteur_societe' => ['nullable', 'string', 'max:150'],
                'expediteur_code_postal' => ['required', 'string', 'max:20'],
                'expediteur_etat' => ['required', 'string', 'max:150'],
                'expediteur_quartier' => ['required', 'string', 'max:255'],

                // Validation Destinataire (obligatoire pour simple mais optionnel pour groupage)
                'destinataire_nom_prenom' => ['nullable', 'string', 'max:255'],
                'destinataire_telephone' => ['nullable', 'string', 'max:20'],
                'destinataire_email' => ['nullable', 'email', 'max:255'],
                'destinataire_adresse' => ['nullable', 'string', 'max:255'],
                'destinataire_ville' => ['required', 'string', 'max:255'],
                'destinataire_pays' => ['required', 'string', 'max:150'],
                'destinataire_societe' => ['nullable', 'string', 'max:150'],
                'destinataire_code_postal' => ['nullable', 'string', 'max:20'],
                'destinataire_etat' => ['nullable', 'string', 'max:150'],
                'destinataire_quartier' => ['nullable', 'string', 'max:255'],

                // Validation des colis
                'colis' => ['required', 'array', 'min:1'],
                'colis.*.code_colis' => ['required', 'string'],
                'colis.*.designation' => ['nullable', 'string'],
                'colis.*.longueur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.largeur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.hauteur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.poids' => ['required', 'numeric', 'min:0'],
                'colis.*.prix_emballage' => ['nullable', 'numeric', 'min:0'],

                // Validation des articles dans chaque colis 
                'colis.*.articles' => ['required', 'array', 'min:1'],
                'colis.*.articles.*' => ['uuid', 'exists:produits,id'],
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();

            // Vérifier que l'utilisateur (client) existe et est actif
            if (isset($validated['user_id']) && $validated['user_id']) {
                $client = User::where('id', $validated['user_id'])
                    ->where('type', 'client')
                    ->where('actif', true)
                    ->first();
                if (!$client) {
                    return response()->json(['success' => false, 'message' => 'Utilisateur (client) invalide ou inactif'], 422);
                }
            }

            $zoneDepart = $this->zoneService->getZoneByCountry($validated['pays_depart']);
            $zoneDestination = $this->zoneService->getZoneByCountry($validated['pays_destination']);

            // 1. Créer le contact Expéditeur
            $expediteur = ContactExpedition::create([
                'type_contact' => 'expediteur',
                'nom_prenom' => $validated['expediteur_nom_prenom'],
                'telephone' => $validated['expediteur_telephone'],
                'email' => $validated['expediteur_email'] ?? null,
                'adresse' => $validated['expediteur_adresse'] ?? null,
                'ville' => $validated['expediteur_ville'] ?? null,
                'pays' => $validated['expediteur_pays'] ?? null,
                'societe' => $validated['expediteur_societe'] ?? null,
                'code_postal' => $validated['expediteur_code_postal'] ?? null,
                'etat' => $validated['expediteur_etat'] ?? null,
                'quartier' => $validated['expediteur_quartier'] ?? null,
            ]);

            // 2. Créer le contact Destinataire
            $destinataire = ContactExpedition::create([
                'type_contact' => 'destinataire',
                'nom_prenom' => $validated['destinataire_nom_prenom'],
                'telephone' => $validated['destinataire_telephone'],
                'email' => $validated['destinataire_email'] ?? null,
                'adresse' => $validated['destinataire_adresse'] ?? null,
                'ville' => $validated['destinataire_ville'] ?? null,
                'pays' => $validated['destinataire_pays'] ?? null,
                'societe' => $validated['destinataire_societe'] ?? null,
                'code_postal' => $validated['destinataire_code_postal'] ?? null,
                'etat' => $validated['destinataire_etat'] ?? null,
                'quartier' => $validated['destinataire_quartier'] ?? null,
            ]);

            // 3. Créer l'expédition    
            $expeditionData = [
                'user_id' => $validated['user_id'] ?? $user->id,
                'agence_id' => $agence->id,
                'zone_depart_id' => $zoneDepart->id,
                'zone_destination_id' => $zoneDestination->id,
                'pays_depart' => $validated['pays_depart'],
                'pays_destination' => $validated['pays_destination'],
                'expediteur_contact_id' => $expediteur->id,
                'destinataire_contact_id' => $destinataire->id,
                'type_expedition' => $validated['type_expedition'],
                'is_paiement_credit' => $validated['is_paiement_credit'],
                'is_livraison_domicile' => $validated['is_livraison_domicile'],
                'statut_paiement' => $validated['statut_paiement'],
                'statut_expedition' => ExpeditionStatus::ACCEPTED,
                'date_livraison_agence' => now(),
            ];

            $expedition = Expedition::create($expeditionData);

            // 4. Créer les colis associés à l'expédition avec leurs articles
            if (!empty($validated['colis'])) {
                foreach ($validated['colis'] as $colisData) {
                    // Créer le colis
                    $colis = Colis::create([
                        'expedition_id' => $expedition->id,
                        'code_colis' => $colisData['code_colis'],
                        'designation' => $colisData['designation'] ?? null,
                        'poids' => $colisData['poids'],
                        'longueur' => $colisData['longueur'],
                        'largeur' => $colisData['largeur'],
                        'hauteur' => $colisData['hauteur'],
                        'prix_emballage' => $colisData['prix_emballage'],
                    ]);

                    // Créer les articles du colis
                    if (!empty($colisData['articles'])) {
                        foreach ($colisData['articles'] as $articleData) {
                            ColisArticle::create(['colis_id' => $colis->id, 'produit_id' => $articleData]);
                        }
                    }

                    // Charger les articles avec leurs produits et catégories
                    $colis->load('articles.produit.category');

                    // Calculer le prix_unitaire selon le type d'expédition
                    $prixUnitaire = null;

                    if ($validated['type_expedition'] !== TypeExpedition::LD->value) {
                        $paysDepart = strtolower(trim($validated['pays_depart']));
                        $paysDestination = strtolower(trim($validated['pays_destination']));
                        $isCoteDivoireFrance = (str_contains($paysDepart, "ivoire") && str_contains($paysDestination, "france")) ||
                            (str_contains($paysDepart, "france") && str_contains($paysDestination, "ivoire"));

                        // GROUPAGE_DHD : Côte d'Ivoire ↔ France - utiliser prix_kg de la catégorie
                        if ($validated['type_expedition'] === TypeExpedition::GROUPAGE_DHD->value && $isCoteDivoireFrance) {
                            $premierArticle = $colis->articles->first();
                            if ($premierArticle && $premierArticle->produit && $premierArticle->produit->category) {
                                $prixUnitaire = $premierArticle->produit->category->prix_kg;
                            }
                        }
                        // GROUPAGE_AFRIQUE : Rechercher tarif par pays de destination
                        elseif ($validated['type_expedition'] === TypeExpedition::GROUPAGE_AFRIQUE->value) {
                            $tarifAgenceGroupage = TarifAgenceGroupage::pourAgence($agence->id)
                                ->whereHas('tarifGroupage', function ($query) use ($validated, $paysDestination) {
                                    $query->where('type_expedition', $validated['type_expedition'])
                                        ->whereRaw('LOWER(pays) = ?', [$paysDestination]);
                                })
                                ->with('tarifGroupage')
                                ->first();

                            if ($tarifAgenceGroupage && $tarifAgenceGroupage->tarifGroupage) {
                                $prixUnitaire = $tarifAgenceGroupage->tarifGroupage->prix_unitaire;
                            }
                        }
                        // GROUPAGE_CA : Tarif général sans filtre de pays
                        elseif ($validated['type_expedition'] === TypeExpedition::GROUPAGE_CA->value) {
                            $tarifAgenceGroupage = TarifAgenceGroupage::pourAgence($agence->id)
                                ->whereHas('tarifGroupage', function ($query) use ($validated) {
                                    $query->where('type_expedition', $validated['type_expedition']);
                                })
                                ->with('tarifGroupage')
                                ->first();

                            if ($tarifAgenceGroupage && $tarifAgenceGroupage->tarifGroupage) {
                                $prixUnitaire = $tarifAgenceGroupage->tarifGroupage->prix_unitaire;
                            }
                        }

                        // Mettre à jour le colis avec le prix_unitaire et prix_total
                        if ($prixUnitaire !== null) {
                            $colis->update([
                                'prix_unitaire' => $prixUnitaire,
                                'prix_total' => $prixUnitaire * $colis->poids
                            ]);
                        }
                    }

                    // Si pas de désignation, générer à partir des produits
                    if ($colis->designation == null) {
                        $colis->update([
                            'designation' => $colis->articles->pluck('produit.designation')->implode(', '),
                        ]);
                    }
                }
            }

            // 5. Calculer le tarif si des colis sont présents
            if (!empty($validated['colis'])) {
                // Appel au service de tarification pour calculer les montants
                $resultatTarif = $this->expeditionTarificationService->calculerTarifExpedition($expedition);

                // Si le calcul a réussi, mettre à jour l'expédition avec les montants
                if ($resultatTarif['success'] ?? false) {
                    $tarif = $resultatTarif['tarif'];
                    $expedition->update([
                        'montant_base' => $tarif['montant_base'],
                        'pourcentage_prestation' => $tarif['pourcentage_prestation'] ?? null,
                        'montant_prestation' => $tarif['montant_prestation'],
                        'montant_expedition' => $tarif['montant_expedition'],
                    ]);
                }
            }

            DB::commit();

            // Charger les relations pour la réponse
            $expedition->load(['expediteurContact', 'destinataireContact']);

            return response()->json([
                'success' => true,
                'message' => 'Expédition créée avec succès',
                'expedition' => $expedition
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création expédition agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'expédition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher les détails d'une expédition
     */
    public function voirDetailsExpedition(string $id): JsonResponse
    {
        try {
            $user = request()->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence non trouvée'], 404);
            }

            $expedition = Expedition::pourAgence($agence->id)
                ->with(['client', 'expediteurContact', 'destinataireContact', 'livreur', 'zoneDepart', 'zoneDestination'])
                ->find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Expédition récupérée avec succès',
                'expedition' => $expedition
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur détails expédition agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'expédition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accepter une expédition initiée par un client
     */
    public function accepterExpedition(string $id): JsonResponse
    {
        try {
            $user = request()->user();
            $agence = Agence::where('user_id', $user->id)->first();

            $expedition = Expedition::pourAgence($agence->id)
                ->where('statut_expedition', ExpeditionStatus::EN_ATTENTE)
                ->find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée ou non éligible'], 404);
            }

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::ACCEPTED,
                'date_expedition_depart' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expédition acceptée avec succès',
                'expedition' => $expedition
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur acceptation expédition agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Refuser une expédition initiée par un client
     */
    public function refuserExpedition(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $agence = Agence::where('user_id', $user->id)->first();

            $validator = Validator::make($request->all(), [
                'motif_refus' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $expedition = Expedition::pourAgence($agence->id)
                ->where('statut_expedition', ExpeditionStatus::EN_ATTENTE)
                ->find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée ou non éligible'], 404);
            }

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::REFUSED,
                'description' => ($expedition->description ?? '') . ' | Refus: ' . $request->motif_refus
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expédition refusée avec succès',
                'expedition' => $expedition
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur refus expédition agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour le statut d'une expédition
     */
    public function mettreAJourStatut(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $agence = Agence::where('user_id', $user->id)->first();

            $validator = Validator::make($request->all(), [
                'statut' => 'required|in:in_progress,shipped,delivered,cancelled',
                'date_livraison_reelle' => 'nullable|date|required_if:statut,delivered'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            $validated = $validator->validated();

            $expedition = Expedition::pourAgence($agence->id)->find($id);
            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);
            }

            // Vérification basique des transitions (à enrichir si besoin)
            $nouveauStatut = $validated['statut'];

            $updateData = ['statut_expedition' => $nouveauStatut];
            if ($nouveauStatut === ExpeditionStatus::DELIVERED->value && isset($validated['date_livraison_reelle'])) {
                $updateData['date_livraison_reelle'] = $validated['date_livraison_reelle'];
            }

            $expedition->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'expedition' => $expedition
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur mise à jour statut expédition agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirmer la réception du colis à l'agence de départ
     */
    public function confirmerReceptionAgenceDepart(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $agence = Agence::where('user_id', $user->id)->first();

            $expedition = Expedition::pourAgence($agence->id)->find($id);
            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);
            }

            $validator = Validator::make($request->all(), [
                'poids_reel' => 'nullable|numeric|min:0.01'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            $validated = $validator->validated();

            $updateData = [
                'statut_expedition' => ExpeditionStatus::RECU_AGENCIA,
            ];

            if (isset($validated['poids_reel'])) {
                $updateData['poids_total_kg'] = $validated['poids_reel'];
            }

            $expedition->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Réception confirmée à l\'agence de départ',
                'expedition' => $expedition
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur confirmation réception agence départ : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Expédier vers l'entrepôt (transit)
     */
    public function expedierVersEntrepot(string $id): JsonResponse
    {
        try {
            $user = request()->user();
            $agence = Agence::where('user_id', $user->id)->first();
            $expedition = Expedition::pourAgence($agence->id)->find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);
            }

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::EN_TRANSIT_ENTREPOT,
                'date_deplacement_entrepot' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expédition en route vers l\'entrepôt',
                'expedition' => $expedition
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur expédition vers entrepôt : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirmer la réception par l'agence de destination
     */
    public function confirmerReceptionAgenceDestination(string $id): JsonResponse
    {
        try {
            // Note: Idéalement vérifier que l'utilisateur appartient à l'agence de destination
            $expedition = Expedition::find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);
            }

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::RECU_AGENCIA_DESTINATION,
                'date_reception_agence' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Colis reçu à l\'agence de destination',
                'expedition' => $expedition
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur confirmation réception agence destination : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Configurer la livraison à domicile
     */
    public function configurerLivraisonDomicile(Request $request, string $id): JsonResponse
    {
        try {
            $expedition = Expedition::find($id);
            if (!$expedition)
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);

            $validator = Validator::make($request->all(), [
                'livreur_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails())
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

            $expedition->update([
                'livreur_id' => $request->livreur_id,
                'is_livraison_domicile' => true
            ]);

            return response()->json(['success' => true, 'message' => 'Livraison à domicile configurée', 'expedition' => $expedition]);

        } catch (\Exception $e) {
            Log::error('Erreur configuration livraison domicile : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Préparer le retrait en agence
     */
    public function preparerRetraitAgence(string $id): JsonResponse
    {
        try {
            $expedition = Expedition::find($id);
            if (!$expedition)
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::EN_ATTENTE_RETRAIT
            ]);

            return response()->json(['success' => true, 'message' => 'Prêt pour retrait en agence', 'expedition' => $expedition]);

        } catch (\Exception $e) {
            Log::error('Erreur préparation retrait agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirmer le retrait par le client
     */
    public function confirmerRetraitClient(string $id): JsonResponse
    {
        try {
            $expedition = Expedition::find($id);
            if (!$expedition)
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::DELIVERED,
                'date_reception_client' => now(),
                'code_validation_reception' => null
            ]);

            return response()->json(['success' => true, 'message' => 'Retrait confirmé', 'expedition' => $expedition]);

        } catch (\Exception $e) {
            Log::error('Erreur confirmation retrait client : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }
}
