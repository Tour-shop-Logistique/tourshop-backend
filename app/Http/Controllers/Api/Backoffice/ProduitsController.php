<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\Produit;
use App\Models\CategoryProduct;
use App\Enums\UserType;
use Exception;
use Illuminate\Validation\Rule;

class ProduitsController extends Controller
{
    public function add(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $request->validate([
                'category_id' => ['required', 'uuid', 'exists:category_products,id'],
                'designation' => ['required', 'string', 'max:150'],
                'reference' => [
                    'required', 'string', 'max:50',
                    Rule::unique('produits', 'reference')->where(function ($q) use ($user) {
                        return $q->where('backoffice_id', $user->backoffice_id);
                    }),
                ],
            ]);

            $category = CategoryProduct::find($request->category_id);
            if ($user->type === UserType::BACKOFFICE && $category->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Cette catégorie n\'appartient pas à votre backoffice.'], 403);
            }

            $product = Produit::create([
                'category_id' => $request->category_id,
                'designation' => $request->designation,
                'reference' => $request->reference,
                'backoffice_id' => $user->backoffice->id ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'Produit ajouté.', 'product' => $product], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur ajout produit groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function list(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            // $query = Produit::query()->with('category');
            $query = Produit::query();
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            $products = $query->orderBy('designation')->get();
            return response()->json(['success' => true, 'products' => $products]);
        } catch (Exception $e) {
            Log::error('Erreur listing produits groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function edit(Request $request, Produit $product)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if ($user->type === UserType::BACKOFFICE && $product->category->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce produit n\'appartient pas à votre backoffice.'], 403);
            }

            $request->validate([
                'designation' => ['sometimes', 'string', 'max:150'],
                'reference' => [
                    'sometimes', 'string', 'max:50',
                    Rule::unique('produits', 'reference')
                        ->ignore($product->id)
                        ->where(function ($q) use ($user) {
                            return $q->where('backoffice_id', $user->backoffice_id);
                        }),
                ],
                'actif' => ['sometimes', 'boolean'],
            ]);

            $product->update($request->only(['designation', 'reference', 'actif']));
            return response()->json(['success' => true, 'message' => 'Produit mis à jour.', 'product' => $product]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour produit groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, Produit $product)
    {
         try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            
             if ($user->type === UserType::BACKOFFICE && $product->category->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce produit n\'appartient pas à votre backoffice.'], 403);
            }

            return response()->json(['success' => true, 'product' => $product->load(['category'])]);
        } catch (Exception $e) {
            Log::error('Erreur affichage produit : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, Produit $product)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            if ($user->type === UserType::BACKOFFICE && $product->category->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce produit n\'appartient pas à votre backoffice.'], 403);
            }

            $product->delete();
            return response()->json(['success' => true, 'message' => 'Produit supprimé.']);
        } catch (Exception $e) {
            Log::error('Erreur suppression produit groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function toggleStatus(Request $request, Produit $product)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            if ($user->type === UserType::BACKOFFICE && $product->category->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce produit n\'appartient pas à votre backoffice.'], 403);
            }

            $product->actif = !$product->actif;
            $product->save();
            return response()->json(['success' => true, 'message' => $product->actif ? 'Produit activé.' : 'Produit désactivé.', 'product' => $product]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut produit groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }


}
