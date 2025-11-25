<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use App\Models\Expedition;
use App\Models\Agence;
use App\Models\User;
use App\Models\Zone;
use App\Services\TarificationService;
use App\Services\ExpeditionTarificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AgenceExpeditionController extends Controller
{
    protected ExpeditionTarificationService $expeditionTarificationService;

    public function __construct(ExpeditionTarificationService $expeditionTarificationService)
    {
        $this->expeditionTarificationService = $expeditionTarificationService;
    }

    /**
     * Lister les expéditions de l'agence
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type_user !== 'agence') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['message' => 'Agence non trouvée'], 404);
            }

            $query = Expedition::pourAgence($agence->id)
                ->with(['client', 'destinataire', 'livreur', 'zoneDepart', 'zoneDestination']);

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
     * Créer une nouvelle expédition (côté agence)
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type_user !== 'agence') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['message' => 'Agence non trouvée'], 404);
            }

            $validator = Validator::make($request->all(), [
                'client_id' => 'required|uuid|exists:users,id',
                'zone_depart_id' => 'required|uuid|exists:zones,id',
                'zone_destination_id' => 'required|uuid|exists:zones,id',
                'mode_expedition' => 'required|in:simple,groupage',
                'type_colis' => 'required_if:mode_expedition,groupage|nullable|string',
                'description' => 'nullable|string|max:500',
                'date_expedition' => 'nullable|date|after_or_equal:today'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();

            // Vérifier que le client existe et est actif
            $client = User::where('id', $validated['client_id'])
                ->where('type', 'client')
                ->where('actif', true)
                ->first();
            if (!$client) {
                return response()->json(['message' => 'Client invalide ou inactif'], 422);
            }

            // Créer l'expédition sans articles pour l'instant
            $expedition = Expedition::create([
                'client_id' => $validated['client_id'],
                'agence_id' => $agence->id,
                'zone_depart_id' => $validated['zone_depart_id'],
                'zone_destination_id' => $validated['zone_destination_id'],
                'mode_expedition' => $validated['mode_expedition'],
                'type_colis' => $validated['type_colis'] ?? null,
                'statut' => Expedition::STATUT_ACCEPTED, // Création par agence = directement acceptée
                'description' => $validated['description'] ?? null,
                'date_expedition' => $validated['date_expedition'] ?? now()
            ]);

            // Charger les relations
            $expedition->load(['client', 'destinataire', 'zoneDepart', 'zoneDestination']);

            return response()->json([
                'message' => 'Expédition créée avec succès. Ajoutez maintenant les articles.',
                'data' => $expedition
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de l\'expédition',
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
            if (!$user || $user->type_user !== 'agence') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['message' => 'Agence non trouvée'], 404);
            }

            $expedition = Expedition::pourAgence($agence->id)
                ->with(['client', 'destinataire', 'livreur', 'zoneDepart', 'zoneDestination'])
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
     * Accepter une expédition initiée par un client
     */
    public function accept(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type_user !== 'agence') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['message' => 'Agence non trouvée'], 404);
            }

            $expedition = Expedition::pourAgence($agence->id)
                ->where('statut', Expedition::STATUT_EN_ATTENTE)
                ->find($id);

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée ou non éligible à l\'acceptation'], 404);
            }

            $expedition->update([
                'statut' => Expedition::STATUT_ACCEPTED,
                'date_expedition' => now()
            ]);

            $expedition->load(['client', 'zoneDepart', 'zoneDestination']);

            return response()->json([
                'message' => 'Expédition acceptée avec succès',
                'data' => $expedition
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'acceptation de l\'expédition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refuser une expédition initiée par un client
     */
    public function refuse(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type_user !== 'agence') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['message' => 'Agence non trouvée'], 404);
            }

            $validator = Validator::make($request->all(), [
                'motif_refus' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $expedition = Expedition::pourAgence($agence->id)
                ->where('statut', Expedition::STATUT_EN_ATTENTE)
                ->find($id);

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée ou non éligible au refus'], 404);
            }

            $expedition->update([
                'statut' => Expedition::STATUT_REFUSED,
                'description' => ($expedition->description ?? '') . ' | Refus: ' . $request->motif_refus
            ]);

            $expedition->load(['client', 'zoneDepart', 'zoneDestination']);

            return response()->json([
                'message' => 'Expédition refusée avec succès',
                'data' => $expedition
            ]);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du refus de l\'expédition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'une expédition
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type_user !== 'agence') {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['message' => 'Agence non trouvée'], 404);
            }

            $validator = Validator::make($request->all(), [
                'statut' => 'required|in:in_progress,shipped,delivered,cancelled',
                'date_livraison_reelle' => 'nullable|date|required_if:statut,delivered'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();

            $expedition = Expedition::pourAgence($agence->id)->find($id);
            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            // Vérifier que le changement de statut est valide
            $statutActuel = $expedition->statut;
            $nouveauStatut = $validated['statut'];

            $transitionsValidées = [
                Expedition::STATUT_ACCEPTED => [Expedition::STATUT_IN_PROGRESS, Expedition::STATUT_CANCELLED],
                Expedition::STATUT_IN_PROGRESS => [Expedition::STATUT_SHIPPED, Expedition::STATUT_CANCELLED],
                Expedition::STATUT_SHIPPED => [Expedition::STATUT_DELIVERED, Expedition::STATUT_CANCELLED],
            ];

            if (!isset($transitionsValidées[$statutActuel]) || !in_array($nouveauStatut, $transitionsValidées[$statutActuel])) {
                return response()->json(['message' => 'Transition de statut non valide'], 422);
            }

            $updateData = ['statut' => $nouveauStatut];

            if ($nouveauStatut === Expedition::STATUT_DELIVERED && isset($validated['date_livraison_reelle'])) {
                $updateData['date_livraison_reelle'] = $validated['date_livraison_reelle'];
            }

            $expedition->update($updateData);
            $expedition->load(['client', 'zoneDepart', 'zoneDestination']);

            return response()->json([
                'message' => 'Statut de l\'expédition mis à jour avec succès',
                'data' => $expedition
            ]);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
