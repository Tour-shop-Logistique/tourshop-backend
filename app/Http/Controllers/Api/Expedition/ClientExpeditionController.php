<?php

namespace App\Http\Controllers\Api\Expedition;

use App\Http\Controllers\Controller;
use App\Models\Expedition;
use App\Models\User;
use App\Models\Agence;
use App\Models\Zone;
use App\Services\TarificationService;
use App\Services\ExpeditionTarificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ClientExpeditionController extends Controller
{
    protected ExpeditionTarificationService $expeditionTarificationService;

    public function __construct(ExpeditionTarificationService $expeditionTarificationService)
    {
        $this->expeditionTarificationService = $expeditionTarificationService;
    }

    /**
     * Lister les expéditions du client
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type !== 'client') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $client = $user; // L'utilisateur connecté est le client

            $query = Expedition::pourUser($client->id)
                ->with(['agence', 'destinataire', 'livreur', 'zoneDepart', 'zoneDestination']);

            // Filtrage par statut
            if ($request->has('statut') && $request->statut) {
                $query->where('statut', $request->statut);
            }

            // Filtrage par mode d'expédition
            if ($request->has('mode_expedition') && $request->mode_expedition) {
                $query->where('mode_expedition', $request->mode_expedition);
            }

            // Filtrage par date
            if ($request->has('date_debut') && $request->date_debut) {
                $query->whereDate('created_at', '>=', $request->date_debut);
            }
            if ($request->has('date_fin') && $request->date_fin) {
                $query->whereDate('created_at', '<=', $request->date_fin);
            }

            $expeditions = $query->orderBy('created_at', 'desc')->paginate(50);

            return response()->json([
                'message' => 'Liste des expéditions récupérée avec succès',
                'data' => $expeditions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des expéditions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initier une nouvelle expédition (côté client)
     */
    public function initiate(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type !== 'client') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $client = $user; // L'utilisateur connecté est le client

            $validator = Validator::make($request->all(), [
                'agence_id' => 'required|uuid|exists:agences,id',
                'zone_depart_id' => 'required|uuid|exists:zones,id',
                'zone_destination_id' => 'required|uuid|exists:zones,id',
                'mode_expedition' => 'required|in:simple,groupage',
                'type_colis' => 'required_if:mode_expedition,groupage|nullable|string',
                'description' => 'nullable|string|max:500',
                'date_expedition_souhaitee' => 'nullable|date|after_or_equal:today'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();

            // Créer l'expédition en attente de validation par l'agence (sans articles pour l'instant)
            $expedition = Expedition::create([
                'user_id' => $client->id,
                'agence_id' => $validated['agence_id'],
                'zone_depart_id' => $validated['zone_depart_id'],
                'zone_destination_id' => $validated['zone_destination_id'],
                'mode_expedition' => $validated['mode_expedition'],
                'type_colis' => $validated['type_colis'] ?? null,
                'statut' => \App\Enums\ExpeditionStatus::EN_ATTENTE, // En attente de validation agence
                'description' => $validated['description'] ?? null,
                'date_expedition' => $validated['date_expedition_souhaitee'] ?? null
            ]);

            // Charger les relations
            $expedition->load(['agence', 'destinataire', 'zoneDepart', 'zoneDestination']);

            return response()->json([
                'message' => 'Expédition initiée avec succès. Ajoutez maintenant les articles.',
                'data' => $expedition
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'initiation de l\'expédition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher les détails d'une expédition
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type !== 'client') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $client = $user; // L'utilisateur connecté est le client

            $expedition = Expedition::pourUser($client->id)
                ->with(['agence', 'destinataire', 'livreur', 'zoneDepart', 'zoneDestination'])
                ->find($id);

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            return response()->json([
                'message' => 'Expédition récupérée avec succès',
                'data' => $expedition
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de l\'expédition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler une expédition (pour les expéditions en attente ou acceptées uniquement)
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type !== 'client') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $client = $user; // L'utilisateur connecté est le client

            $validator = Validator::make($request->all(), [
                'motif_annulation' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $expedition = Expedition::pourUser($client->id)
                ->whereIn('statut', [\App\Enums\ExpeditionStatus::EN_ATTENTE, \App\Enums\ExpeditionStatus::ACCEPTED])
                ->find($id);

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée ou non éligible à l\'annulation'], 404);
            }

            $expedition->update([
                'statut' => \App\Enums\ExpeditionStatus::CANCELLED,
                'description' => ($expedition->description ?? '') . ' | Annulation client: ' . $request->motif_annulation
            ]);

            $expedition->load(['agence', 'zoneDepart', 'zoneDestination']);

            return response()->json([
                'message' => 'Expédition annulée avec succès',
                'data' => $expedition
            ]);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'annulation de l\'expédition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simuler le coût d'une expédition avant de la créer
     */
    public function simulate(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type !== 'client') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $validator = Validator::make($request->all(), [
                'agence_id' => 'required|uuid|exists:agences,id',
                'zone_depart_id' => 'required|uuid|exists:zones,id',
                'zone_destination_id' => 'required|uuid|exists:zones,id',
                'type_expedition' => ['required', 'string', 'in:' . implode(',', array_map(fn($case) => $case->value, \App\Enums\TypeExpedition::cases()))],
                'expediteur_ville' => ['nullable', 'string', 'max:255'],
                'destinataire_ville' => ['nullable', 'string', 'max:255'],
                'colis' => ['required', 'array', 'min:1'],
                'colis.*.category_id' => ['nullable', 'uuid', 'exists:category_products,id'],
                'colis.*.poids' => ['required', 'numeric', 'min:0'],
                'colis.*.longueur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.largeur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.hauteur' => ['nullable', 'numeric', 'min:0'],
                'colis.*.prix_emballage' => ['nullable', 'numeric', 'min:0'],
                'colis.*.code_colis' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();

            // Vérifier que l'agence est active
            $agence = Agence::find($validated['agence_id']);
            if (!$agence || !$agence->actif) {
                return response()->json(['success' => false, 'message' => 'Agence invalide ou inactive'], 422);
            }

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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la simulation du tarif',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des expéditions du client
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type !== 'client') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $client = $user; // L'utilisateur connecté est le client

            $stats = [
                'total' => Expedition::pourUser($client->id)->count(),
                'en_attente' => Expedition::pourUser($client->id)->enAttente()->count(),
                'accepted' => Expedition::pourUser($client->id)->accepted()->count(),
                'refused' => Expedition::pourUser($client->id)->refused()->count(),
                'in_progress' => Expedition::pourUser($client->id)->inProgress()->count(),
                'shipped' => Expedition::pourUser($client->id)->shipped()->count(),
                'delivered' => Expedition::pourUser($client->id)->delivered()->count(),
                'cancelled' => Expedition::pourUser($client->id)->cancelled()->count(),
                'montant_total' => Expedition::pourUser($client->id)->sum('montant_expedition'),
                'montant_paye' => Expedition::pourUser($client->id)->where('statut_paiement', \App\Enums\StatutPaiement::PAYE)->sum('montant_expedition')
            ];

            return response()->json([
                'message' => 'Statistiques récupérées avec succès',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
