<?php

namespace App\Http\Controllers\Api\Agence;

use App\Enums\ExpeditionStatus;
use App\Enums\StatutPaiement;
use App\Enums\TypeExpedition;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\Colis;
use App\Models\Expedition;
use App\Models\TarifAgenceGroupage;
use App\Models\User;
use App\Services\ExpeditionTarificationService;
use App\Services\ZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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
                ->withLivreurs()
                ->with('colis');

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
                    $q
                        ->where('reference', 'like', "%{$search}%")
                        ->orWhere('code_suivi_expedition', 'like', "%{$search}%");
                });
            }

            $paginator = $query->orderBy('created_at', 'desc')->paginate(20);

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expeditions = collect($paginator->items())->map(function ($expedition) {
                return $expedition->masquerIdsLivreurs();
            });

            return response()->json([
                'success' => true,
                'message' => 'Liste des expéditions récupérée avec succès',
                'data' => $expeditions->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ]
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
     * Simuler le tarif d'une expédition avant enregistrement
     */
    public function simulerExpedition(Request $request): JsonResponse
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

            $validator = Validator::make($request->all(), [
                'pays_depart' => ['required', 'string', 'max:150'],
                'pays_destination' => ['required', 'string', 'max:150'],
                'type_expedition' => ['required', 'string', 'in:' . implode(',', array_map(fn($case) => $case->value, TypeExpedition::cases()))],
                'expediteur_ville' => ['nullable', 'string', 'max:255'],
                'destinataire_ville' => ['nullable', 'string', 'max:255'],
                'colis' => ['required', 'array', 'min:1'],
                'colis.*.category_id' => ['nullable', 'uuid', 'exists:category_products,id'],
                'colis.*.poids' => ['required', 'numeric', 'min:0'],
                'colis.*.longueur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.largeur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.hauteur' => ['nullable', 'numeric', 'min:0'],
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();
            $validated['agence_id'] = $agence->id;

            $resultat = $this->expeditionTarificationService->simulerTarifExpedition($validated);

            if ($resultat['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tarification simulée avec succès',
                    'data' => $resultat
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $resultat['message'] ?? 'Erreur lors de la simulation'
                ], 422);
            }
        } catch (\Exception $e) {
            Log::error('Erreur simulation expédition agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la simulation de l\'expédition',
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
                // 'user_id' => 'nullable|uuid|exists:users,id',
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
                'expediteur_societe' => ['nullable', 'string', 'max:150'],
                'expediteur_code_postal' => ['nullable', 'string', 'max:20'],
                'expediteur_etat' => ['nullable', 'string', 'max:150'],
                'expediteur_quartier' => ['nullable', 'string', 'max:255'],
                // Validation Destinataire
                'destinataire_nom_prenom' => ['required', 'string', 'max:255'],
                'destinataire_telephone' => ['required', 'string', 'max:20'],
                'destinataire_email' => ['nullable', 'email', 'max:255'],
                'destinataire_adresse' => ['required', 'string', 'max:255'],
                'destinataire_ville' => ['required', 'string', 'max:255'],
                'destinataire_societe' => ['nullable', 'string', 'max:150'],
                'destinataire_code_postal' => ['nullable', 'string', 'max:20'],
                'destinataire_etat' => ['nullable', 'string', 'max:150'],
                'destinataire_quartier' => ['nullable', 'string', 'max:255'],
                // Validation des colis
                'colis' => ['required', 'array', 'min:1'],
                'colis.*.code_colis' => ['required', 'string'],
                'colis.*.category_id' => ['nullable', 'uuid', 'exists:category_products,id'],
                'colis.*.designation' => ['nullable', 'string'],
                'colis.*.longueur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.largeur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.hauteur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.poids' => ['required', 'numeric', 'min:0'],
                'colis.*.prix_emballage' => ['nullable', 'numeric', 'min:0'],
                // Validation des articles dans chaque colis
                'colis.*.articles' => ['nullable', 'array', 'min:1'],
                'colis.*.articles.*' => ['string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();

            // Vérifier que l'utilisateur (client) existe et est actif
            if ($user->id) {
                $client = User::where('id', $user->id)
                    ->where('actif', true)
                    ->first();
                if (!$client) {
                    return response()->json(['success' => false, 'message' => 'Utilisateur (client) invalide ou inactif'], 422);
                }
            }

            $zoneDepart = $this->zoneService->getZoneByCountry($validated['pays_depart']);
            $zoneDestination = $this->zoneService->getZoneByCountry($validated['pays_destination']);

            if (!$zoneDepart || !$zoneDestination) {
                return response()->json([
                    'success' => false,
                    'message' => 'Zone de départ ou de destination introuvable pour les pays spécifiés.'
                ], 422);
            }

            // 1. Préparer les données JSON pour l'expéditeur
            $expediteurData = [
                'nom_prenom' => $validated['expediteur_nom_prenom'],
                'telephone' => $validated['expediteur_telephone'],
                'email' => $validated['expediteur_email'] ?? null,
                'adresse' => $validated['expediteur_adresse'] ?? null,
                'ville' => $validated['expediteur_ville'] ?? null,
                'societe' => $validated['expediteur_societe'] ?? null,
                'code_postal' => $validated['expediteur_code_postal'] ?? null,
                'etat' => $validated['expediteur_etat'] ?? null,
                'quartier' => $validated['expediteur_quartier'] ?? null,
            ];

            // 2. Préparer les données JSON pour le destinataire
            $destinataireData = [
                'nom_prenom' => $validated['destinataire_nom_prenom'],
                'telephone' => $validated['destinataire_telephone'],
                'email' => $validated['destinataire_email'] ?? null,
                'adresse' => $validated['destinataire_adresse'] ?? null,
                'ville' => $validated['destinataire_ville'] ?? null,
                'societe' => $validated['destinataire_societe'] ?? null,
                'code_postal' => $validated['destinataire_code_postal'] ?? null,
                'etat' => $validated['destinataire_etat'] ?? null,
                'quartier' => $validated['destinataire_quartier'] ?? null,
            ];

            // 3. Créer l'expédition
            $expeditionData = [
                'user_id' => $user->id,
                'agence_id' => $agence->id,
                'zone_depart_id' => $zoneDepart->id,
                'zone_destination_id' => $zoneDestination->id,
                'pays_depart' => $validated['pays_depart'],
                'pays_destination' => $validated['pays_destination'],
                'expediteur' => $expediteurData,
                'destinataire' => $destinataireData,
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
                    Colis::create([
                        'expedition_id' => $expedition->id,
                        'category_id' => $colisData['category_id'] ?? null,
                        'code_colis' => $colisData['code_colis'],
                        'designation' => $colisData['designation'] ?? null,
                        'articles' => $colisData['articles'] ?? null,
                        'poids' => $colisData['poids'],
                        'longueur' => $colisData['longueur'],
                        'largeur' => $colisData['largeur'],
                        'hauteur' => $colisData['hauteur'],
                        'prix_emballage' => $colisData['prix_emballage'],
                    ]);
                }
            }

            // 5. Calculer le tarif si des colis sont présents
            if (!empty($validated['colis'])) {
                // Appel au service de tarification pour calculer les montants
                $resultatTarif = $this->expeditionTarificationService->calculerTarifExpedition($expedition);

                // Si le calcul a réussi, mettre à jour l'expédition avec les montants
                if ($resultatTarif['success']) {
                    $tarif = $resultatTarif['tarif'];
                    $expedition->update([
                        'montant_base' => $tarif['montant_base'],
                        'pourcentage_prestation' => $tarif['pourcentage_prestation'],
                        'montant_prestation' => $tarif['montant_prestation'],
                        'montant_expedition' => $tarif['montant_expedition'],
                        'frais_emballage' => $tarif['frais_emballage'],
                    ]);
                } else {
                    Log::warning("Calcul tarif échoué pour l'expédition " . $expedition->reference . ' : ' . ($resultatTarif['message'] ?? 'Erreur inconnue'));
                }
            }

            DB::commit();

            // Les données d'expéditeur et destinataire sont déjà dans les champs JSON
            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

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
                'message' => "Erreur lors de la création de l'expédition",
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
                ->with(['user', 'zoneDepart', 'zoneDestination'])
                ->withLivreurs()
                ->find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);
            }

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

            return response()->json([
                'success' => true,
                'message' => 'Expédition récupérée avec succès',
                'expedition' => $expedition
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur détails expédition agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la récupération de l'expédition",
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
                ->withLivreurs()
                ->find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée ou non éligible'], 404);
            }

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::ACCEPTED,
                'date_expedition_depart' => now()
            ]);

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

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
                ->withLivreurs()
                ->find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée ou non éligible'], 404);
            }

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::REFUSED,
                'description' => ($expedition->description ?? '') . ' | Refus: ' . $request->motif_refus
            ]);

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

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

            $expedition = Expedition::pourAgence($agence->id)
                ->withLivreurs()
                ->find($id);
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

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

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

            $expedition = Expedition::pourAgence($agence->id)
                ->withLivreurs()
                ->find($id);
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

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

            return response()->json([
                'success' => true,
                'message' => "Réception confirmée à l'agence de départ",
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
            $expedition = Expedition::pourAgence($agence->id)
                ->withLivreurs()
                ->find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);
            }

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::EN_TRANSIT_ENTREPOT,
                'date_deplacement_entrepot' => now()
            ]);

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

            return response()->json([
                'success' => true,
                'message' => "Expédition en route vers l'entrepôt",
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
            $expedition = Expedition::withLivreurs()
                ->find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);
            }

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::RECU_AGENCIA_DESTINATION,
                'date_reception_agence' => now()
            ]);

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

            return response()->json([
                'success' => true,
                'message' => "Colis reçu à l'agence de destination",
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
            $expedition = Expedition::withLivreurs()
                ->find($id);
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

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

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
            $expedition = Expedition::withLivreurs()
                ->find($id);
            if (!$expedition)
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::EN_ATTENTE_RETRAIT
            ]);

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

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
            $expedition = Expedition::withLivreurs()
                ->find($id);
            if (!$expedition)
                return response()->json(['success' => false, 'message' => 'Expédition non trouvée'], 404);

            $expedition->update([
                'statut_expedition' => ExpeditionStatus::DELIVERED,
                'date_reception_client' => now(),
                'code_validation_reception' => null
            ]);

            // Masquer les IDs des livreurs car les relations livreur sont chargées
            $expedition->masquerIdsLivreurs();

            return response()->json(['success' => true, 'message' => 'Retrait confirmé', 'expedition' => $expedition]);
        } catch (\Exception $e) {
            Log::error('Erreur confirmation retrait client : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }
}
