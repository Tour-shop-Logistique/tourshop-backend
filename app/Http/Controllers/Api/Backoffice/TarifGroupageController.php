<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\TarifGroupage;
use App\Models\CategoryProduct;
use App\Enums\UserType;
use Exception;

class TarifGroupageController extends Controller
{
    public function list(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $query = TarifGroupage::query()->with('category');

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            if ($user->type === UserType::BACKOFFICE) {
                $query->where('backoffice_id', $user->backoffice_id);
            }

            $tarifs = $query->get();
            return response()->json(['success' => true, 'tarifs' => $tarifs]);
        } catch (Exception $e) {
            Log::error('Erreur listing tarifs groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function add(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $request->validate([
                'category_id' => ['required', 'uuid', 'exists:category_products,id'],
                'tarif_minimum' => ['required', 'numeric', 'min:1'],
                'prix_modes' => ['required', 'array', 'min:1'],
                'prix_modes.*.mode' => ['required', 'string'],
                'prix_modes.*.montant_base' => ['required', 'numeric', 'min:0'],
                'prix_modes.*.pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $category = CategoryProduct::find($request->category_id);
            if ($user->type === UserType::BACKOFFICE && $category->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Cette catégorie n\'appartient pas à votre backoffice.'], 403);
            }

            $tarif = TarifGroupage::create([
                'category_id' => $request->category_id,
                'tarif_minimum' => $request->tarif_minimum,
                'mode_expedition' => 'groupage',
                'prix_modes' => $request->prix_modes,
                'pays' => $user->backoffice->pays,
                'backoffice_id' => $user->backoffice->id,
            ]);

            return response()->json(['success' => true, 'message' => 'Tarif groupage créé.', 'tarif' => $tarif], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur création tarif groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function edit(Request $request, TarifGroupage $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if ($user->type === UserType::BACKOFFICE && $tarif->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif n\'appartient pas à votre backoffice.'], 403);
            }

            $request->validate([
                'tarif_minimum' => ['sometimes', 'numeric', 'min:1'],
                'prix_modes' => ['sometimes', 'array', 'min:1'],
                'prix_modes.*.mode' => ['required', 'string'],
                'prix_modes.*.montant_base' => ['required', 'numeric', 'min:0'],
                'prix_modes.*.pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarif->update($request->only(['tarif_minimum', 'prix_modes']));

            return response()->json(['success' => true, 'message' => 'Tarif groupage mis à jour.', 'tarif' => $tarif]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour tarif groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, TarifGroupage $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            if ($user->type === UserType::BACKOFFICE && $tarif->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif n\'appartient pas à votre backoffice.'], 403);
            }

            return response()->json(['success' => true, 'tarif' => $tarif->load('category')]);
        } catch (Exception $e) {
            Log::error('Erreur affichage tarif groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, TarifGroupage $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            if ($user->type === UserType::BACKOFFICE && $tarif->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif n\'appartient pas à votre backoffice.'], 403);
            }

            $tarif->delete();
            return response()->json(['success' => true, 'message' => 'Tarif groupage supprimé.']);
        } catch (Exception $e) {
            Log::error('Erreur suppression tarif groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function toggleStatus(Request $request, TarifGroupage $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            if ($user->type === UserType::BACKOFFICE && $tarif->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif n\'appartient pas à votre backoffice.'], 403);
            }

            $tarif->actif = !$tarif->actif;
            $tarif->save();

            return response()->json(['success' => true, 'message' => $tarif->actif ? 'Tarif activé.' : 'Tarif désactivé.', 'tarif' => $tarif]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut tarif groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
