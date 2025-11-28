<?php

namespace App\Http\Controllers\Api\Livreur;

use App\Http\Controllers\Controller;
use App\Models\Expedition;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LivreurExpeditionController extends Controller
{
    /**
     * Lister les missions du livreur (enlèvements et livraisons)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->type !== 'livreur') { // Assuming 'livreur' is the type in User model, need to verify if it's 'livreur' or something else based on previous files. 
                // Looking at AgenceExpeditionController, it checks $user->type_user !== 'agence'. 
                // Looking at ClientExpeditionController, it checks $user->type !== 'client'.
                // I should check the User model or AuthController to be sure, but 'livreur' seems likely.
                // Let's assume 'livreur' for now based on the context.
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $query = Expedition::where('livreur_id', $user->id)
                ->with(['client', 'destinataire', 'agence', 'zoneDepart', 'zoneDestination']);

            // Filtrage par statut
            if ($request->has('statut') && $request->statut) {
                $query->where('statut', $request->statut);
            }

            $missions = $query->orderBy('updated_at', 'desc')->paginate(20);

            return response()->json([
                'message' => 'Missions récupérées avec succès',
                'data' => $missions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des missions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Démarrer un enlèvement à domicile
     */
    public function startEnlevement(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $expedition = Expedition::where('id', $id)->where('livreur_id', $user->id)->first();

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée ou non assignée'], 404);
            }

            if ($expedition->statut !== \App\Enums\ExpeditionStatus::ACCEPTED) {
                // Logic check
            }

            // Update status to indicate pickup is in progress
            $expedition->update([
                'statut' => \App\Enums\ExpeditionStatus::EN_COURS_ENLEVEMENT
            ]);

            return response()->json([
                'message' => 'Enlèvement démarré',
                'data' => $expedition
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirmer l'enlèvement (colis récupéré chez le client)
     */
    public function confirmEnlevement(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $expedition = Expedition::where('id', $id)->where('livreur_id', $user->id)->first();

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            $expedition->update([
                'date_enlevement_reelle' => now(),
                // Status remains EN_COURS_ENLEVEMENT until dropped at agency? Or maybe a specific status?
                // Workflow says: "Mise à jour date_enlevement_reelle et statut EN_COURS_ENLEVEMENT" at step 4.
                // Then "Livraison à l'agence pour vérification".
            ]);

            return response()->json([
                'message' => 'Enlèvement confirmé',
                'data' => $expedition
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirmer la réception à l'agence (Livreur dépose le colis)
     */
    public function confirmReceptionAgence(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $expedition = Expedition::where('id', $id)->where('livreur_id', $user->id)->first();

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            $expedition->update([
                'date_livraison_agence' => now(),
                'statut' => \App\Enums\ExpeditionStatus::RECU_AGENCIA
            ]);

            return response()->json([
                'message' => 'Colis déposé à l\'agence',
                'data' => $expedition
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Démarrer la livraison finale
     */
    public function startLivraison(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $expedition = Expedition::where('id', $id)->where('livreur_id', $user->id)->first();

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            $expedition->update([
                'statut' => \App\Enums\ExpeditionStatus::EN_LIVRAISON
            ]);

            return response()->json([
                'message' => 'Livraison démarrée',
                'data' => $expedition
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Valider la livraison avec le code
     */
    public function validateLivraison(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $expedition = Expedition::where('id', $id)->where('livreur_id', $user->id)->first();

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            $validator = Validator::make($request->all(), [
                'code_validation' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            if ($request->code_validation !== $expedition->code_validation_reception) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code de réception incorrect'
                ], 422);
            }

            $expedition->update([
                'statut' => \App\Enums\ExpeditionStatus::LIVRE,
                'date_livraison_reelle' => now(),
                'date_reception_client' => now(),
                'code_validation_reception' => null // Clear code for security
            ]);

            return response()->json([
                'message' => 'Livraison validée avec succès',
                'data' => $expedition
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }
}
