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

            $query = Expedition::pourClient($client->id)
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
                'client_id' => $client->id,
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

            $expedition = Expedition::pourClient($client->id)
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

            $expedition = Expedition::pourClient($client->id)
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
                'mode_expedition' => 'required|in:simple,groupage',
                'type_colis' => 'required_if:mode_expedition,groupage|nullable|string',
                'articles' => 'required|array|min:1',
                'articles.*.designation' => 'required|string|max:255',
                'articles.*.poids' => 'required|numeric|min:0.01',
                'articles.*.longueur' => 'nullable|numeric|min:0.01',
                'articles.*.largeur' => 'nullable|numeric|min:0.01',
                'articles.*.hauteur' => 'nullable|numeric|min:0.01',
                'articles.*.quantite' => 'required|integer|min:1',
                'articles.*.produit_id' => 'nullable|uuid|exists:produits,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();

            // Vérifier que l'agence est active
            $agence = Agence::find($validated['agence_id']);
            if (!$agence || !$agence->actif) {
                return response()->json(['message' => 'Agence invalide ou inactive'], 422);
            }

            // Simuler avec les articles
            $donneesExpedition = [
                'mode_expedition' => $validated['mode_expedition'],
                'zone_depart_id' => $validated['zone_depart_id'],
                'zone_destination_id' => $validated['zone_destination_id'],
                'agence_id' => $validated['agence_id']
            ];

            $resultat = $this->expeditionTarificationService->simulerTarifExpedition(
                $donneesExpedition,
                $validated['articles']
            );

            if (!$resultat['success']) {
                return response()->json([
                    'message' => 'Erreur lors de la simulation du tarif',
                    'error' => $resultat['message'] ?? 'Tarification indisponible'
                ], 422);
            }

            return response()->json([
                'message' => 'Simulation de tarif réussie',
                'data' => $resultat
            ]);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
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
                'total' => Expedition::pourClient($client->id)->count(),
                'en_attente' => Expedition::pourClient($client->id)->enAttente()->count(),
                'accepted' => Expedition::pourClient($client->id)->accepted()->count(),
                'refused' => Expedition::pourClient($client->id)->refused()->count(),
                'in_progress' => Expedition::pourClient($client->id)->inProgress()->count(),
                'shipped' => Expedition::pourClient($client->id)->shipped()->count(),
                'delivered' => Expedition::pourClient($client->id)->delivered()->count(),
                'cancelled' => Expedition::pourClient($client->id)->cancelled()->count(),
                'montant_total' => Expedition::pourClient($client->id)->sum('montant_expedition'),
                'montant_paye' => Expedition::pourClient($client->id)->where('statut_paiement', \App\Enums\StatutPaiement::PAYE)->sum('montant_expedition')
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
