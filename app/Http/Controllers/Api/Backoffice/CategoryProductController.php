<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\CategoryProduct;
use App\Enums\UserType;
use Exception;

class CategoryProductController extends Controller
{
    public function list(Request $request)
    {
        try {
            $user = $request->user();

            $query = CategoryProduct::query();

            if ($user->type === UserType::BACKOFFICE) {
                $query->where('backoffice_id', $user->backoffice_id);
            }

            if ($user->type === UserType::AGENCE) {
                $query->where('pays', $user->agence->pays);
            }

            if ($request->has('pays')) {
                $query->where('pays', $request->pays);
            }

            $categories = $query->orderBy('nom')->get();
            return response()->json(['success' => true, 'categories' => $categories]);
        } catch (Exception $e) {
            Log::error('Erreur listing catégories groupage : ' . $e->getMessage());
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
                'nom' => ['required', 'string', 'max:150'],
                // 'prix_kg' => ['required', 'array'],
                // 'prix_kg.*.ligne' => ['required', 'string'],
                // 'prix_kg.*.prix' => ['required', 'numeric', 'min:0'],
            ]);

            $category = CategoryProduct::create([
                'nom' => $request->nom,
                // 'prix_kg' => $request->prix_kg,
                'pays' => $user->backoffice->pays ?? null,
                'backoffice_id' => $user->backoffice->id ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'Catégorie créée.', 'category' => $category], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur création catégorie groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function edit(Request $request, CategoryProduct $category)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            if ($user->type === UserType::BACKOFFICE && $category->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Cette catégorie n\'appartient pas à votre backoffice.'], 403);
            }

            $request->validate([
                'nom' => ['sometimes', 'string', 'max:150'],
                // 'prix_kg' => ['sometimes', 'array'],
                // 'prix_kg.*.ligne' => ['required_with:prix_kg', 'string'],
                // 'prix_kg.*.prix' => ['required_with:prix_kg', 'numeric', 'min:0'],
            ]);

            $category->update($request->only(['nom']));
            return response()->json(['success' => true, 'message' => 'Catégorie mise à jour.', 'category' => $category]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour catégorie groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, CategoryProduct $category)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            if ($user->type === UserType::BACKOFFICE && $category->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Cette catégorie n\'appartient pas à votre backoffice.'], 403);
            }

            return response()->json(['success' => true, 'category' => $category->load(['produits'])]);
        } catch (Exception $e) {
            Log::error('Erreur affichage catégorie groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, CategoryProduct $category)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            if ($user->type === UserType::BACKOFFICE && $category->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Cette catégorie n\'appartient pas à votre backoffice.'], 403);
            }

            $category->delete();
            return response()->json(['success' => true, 'message' => 'Catégorie supprimée.']);
        } catch (Exception $e) {
            Log::error('Erreur suppression catégorie groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function toggleStatus(Request $request, CategoryProduct $category)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            if ($user->type === UserType::BACKOFFICE && $category->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Cette catégorie n\'appartient pas à votre backoffice.'], 403);
            }

            $category->actif = !$category->actif;
            $category->save();
            return response()->json(['success' => true, 'message' => $category->actif ? 'Catégorie activée.' : 'Catégorie désactivée.', 'category' => $category]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut catégorie groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
