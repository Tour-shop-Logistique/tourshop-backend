<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Agence;
use App\Models\TarifPersonnalise;
use App\Enums\UserType;
use Illuminate\Support\Facades\Log;
use Exception;

class AgenceTarifController extends Controller
{
    /**
     * Obtenir le tarif actuel de l'agence
     */
    public function getTarifActuel(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = $user->agence;
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            if (!$agence->tarif_code) {
                return response()->json(['success' => false, 'message' => 'Aucun tarif assigné à votre agence.'], 404);
            }

            $tarif = TarifPersonnalise::actif()->parCode($agence->tarif_code)->with('tarifSimple')->first();
            if (!$tarif) {
                return response()->json(['success' => false, 'message' => 'Tarif introuvable ou inactif.'], 404);
            }

            return response()->json(['success' => true, 'tarif' => $tarif]);
        } catch (Exception $e) {
            Log::error('Erreur récupération tarif actuel : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Assigner un code tarif à l'agence
     */
    public function assignerCodeTarif(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE || !$user->isAgenceAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur de l\'agence peut modifier les tarifs.'
                ], 403);
            }

            $agence = $user->agence;
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            $request->validate([
                'code' => ['required', 'string', 'max:20', 'exists:tarifs_personnalises,code'],
            ]);

            // Vérifier que le code existe et est actif
            $tarif = TarifPersonnalise::parCode($request->code)->first();
            if (!$tarif) {
                return response()->json(['success' => false, 'message' => 'Code tarif introuvable ou inactif.'], 404);
            }

            $agence->tarif_code = $request->code;
            $agence->save();

            return response()->json([
                'success' => true,
                'message' => 'Code tarif assigné avec succès.',
                'agence' => $agence->load('tarifPersonnalise.tarifSimple')
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur assignation code tarif : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

}
