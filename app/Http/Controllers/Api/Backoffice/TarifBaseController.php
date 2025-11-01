<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\TarifBase;
use App\Enums\UserType;
use App\Enums\ModeExpedition;
use App\Enums\TypeColis;
use Exception;

class TarifBaseController extends Controller
{
    /**
     * Lister les tarifs de base
     */
    public function listTarifBase(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN, UserType::AGENCE])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $query = TarifBase::query();

            if ($request->filled('indice')) {
                $query->where('indice', $request->indice);
            }
            if ($request->filled('mode_expedition')) {
                $query->where('mode_expedition', $request->mode_expedition);
            }
            if ($request->filled('pays')) {
                $query->where('pays', $request->pays);
            }

            if ($user->type === UserType::BACKOFFICE) {
                $query->where('backoffice_id', $user->backoffice_id);
            }


            $tarifs = $query->orderBy('indice')->orderBy('mode_expedition')->get();

            return response()->json(['success' => true, 'tarifs' => $tarifs]);
        } catch (Exception $e) {
            Log::error('Erreur listing tarifs base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Créer un tarif de base
     */
    public function addTarifBaseSimple(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $request->validate([
                'indice' => ['required', 'numeric', 'min:0'],
                'mode_expedition' => ['required', 'in:simple,groupage'],
                'prix_zones' => ['required', 'array', 'min:1'],
                'prix_zones.*.zone_destination_id' => ['required', 'string', 'exists:zones,id'],
                'prix_zones.*.montant_base' => ['required', 'numeric', 'min:0'],
                'prix_zones.*.pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarif = TarifBase::create([
                'indice' => $request->indice,
                'mode_expedition' => ModeExpedition::from($request->mode_expedition)->value,
                'prix_zones' => $request->prix_zones,
                'pays' => $user->backoffice->pays,
                'backoffice_id' => $user->backoffice->id
            ]);

            return response()->json(['success' => true, 'message' => 'Tarif de base créé avec succès.', 'tarif' => $tarif], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur création tarif base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Afficher un tarif de base
     */
    public function showTarifBase(Request $request, TarifBase $tarifBase)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN, UserType::AGENCE])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $backoffice = $user->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable.'
                ], 404);
            }

            // Vérifier que le tarif à modifier appartient au même backoffice
            if ($tarifBase->backoffice_id !== $backoffice->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce tarif n\'appartient pas à votre backoffice.'
                ], 403);
            }

            return response()->json(['success' => true, 'tarif' => $tarifBase]);
        } catch (Exception $e) {
            Log::error('Erreur affichage tarif base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour un tarif de base
     */
    public function editTarifBaseSimple(Request $request, TarifBase $tarifBase)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $backoffice = $user->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable.'
                ], 404);
            }

            // Vérifier que le tarif à modifier appartient au même backoffice
            if ($tarifBase->backoffice_id !== $backoffice->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce tarif n\'appartient pas à votre backoffice.'
                ], 403);
            }

            $request->validate([
                'indice' => ['sometimes', 'numeric', 'min:0'],
                'prix_zones' => ['sometimes', 'array', 'min:1'],
                'prix_zones.*.zone_destination_id' => ['required', 'string', 'exists:zones,id'],
                'prix_zones.*.montant_base' => ['required', 'numeric', 'min:0'],
                'prix_zones.*.pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarifBase->update($request->only(['indice', 'prix_zones']));

            return response()->json(['success' => true, 'message' => 'Tarif de base mis à jour.', 'tarif' => $tarifBase]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour tarif base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer un tarif de base
     */
    public function deleteTarifBase(Request $request, TarifBase $tarifBase)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $backoffice = $user->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable.'
                ], 404);
            }

            // Vérifier que le tarif à modifier appartient au même backoffice
            if ($tarifBase->backoffice_id !== $backoffice->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce tarif n\'appartient pas à votre backoffice.'
                ], 403);
            }

            $tarifBase->delete();

            return response()->json(['success' => true, 'message' => 'Tarif de base supprimé avec succès.']);
        } catch (Exception $e) {
            Log::error('Erreur suppression tarif base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Activer/Désactiver un tarif de base
     */
    public function toggleStatusTarifBase(Request $request, TarifBase $tarifBase)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $backoffice = $user->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable.'
                ], 404);
            }

            // Vérifier que le tarif à modifier appartient au même backoffice
            if ($tarifBase->backoffice_id !== $backoffice->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce tarif n\'appartient pas à votre backoffice.'
                ], 403);
            }

            $tarifBase->actif = !$tarifBase->actif;
            $tarifBase->save();

            return response()->json(['success' => true, 'message' => $tarifBase->actif ? 'Tarif activé.' : 'Tarif désactivé.', 'tarif' => $tarifBase]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut tarif base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
