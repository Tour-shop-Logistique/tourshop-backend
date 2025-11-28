<?php

namespace App\Http\Controllers\Api\Entrepot;

use App\Http\Controllers\Controller;
use App\Models\Expedition;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EntrepotController extends Controller
{
    /**
     * Recevoir un colis à l'entrepôt
     */
    public function receive(string $id): JsonResponse
    {
        try {
            // Check permissions (assuming there is an 'entrepot' role or similar)
            // For now, let's assume basic auth check is done in route middleware

            $expedition = Expedition::find($id);

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            if ($expedition->statut !== \App\Enums\ExpeditionStatus::EN_TRANSIT_ENTREPOT) {
                // Logic check
            }

            $expedition->update([
                'statut' => \App\Enums\ExpeditionStatus::IN_PROGRESS
            ]);

            return response()->json([
                'message' => 'Colis reçu à l\'entrepôt',
                'data' => $expedition
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirmer le chargement et le départ (Expédier)
     */
    public function ship(Request $request, string $id): JsonResponse
    {
        try {
            $expedition = Expedition::find($id);

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            $expedition->update([
                'statut' => \App\Enums\ExpeditionStatus::EXPEDITION_DEPART,
                'date_expedition_depart' => now()
            ]);

            return response()->json([
                'message' => 'Expédition partie de l\'entrepôt',
                'data' => $expedition
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirmer l'arrivée à destination
     */
    public function confirmArrival(string $id): JsonResponse
    {
        try {
            $expedition = Expedition::find($id);

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            $expedition->update([
                'statut' => \App\Enums\ExpeditionStatus::EXPEDITION_ARRIVEE,
                'date_expedition_arrivee' => now()
            ]);

            return response()->json([
                'message' => 'Expédition arrivée à destination',
                'data' => $expedition
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }
}
