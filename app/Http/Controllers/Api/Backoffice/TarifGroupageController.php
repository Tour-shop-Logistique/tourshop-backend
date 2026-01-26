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
use App\Enums\TypeExpedition;

class TarifGroupageController extends Controller
{
    public function list(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN, UserType::AGENCE])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $query = TarifGroupage::query();

            if ($user->type === UserType::BACKOFFICE) {
                $query->where('backoffice_id', $user->backoffice_id);
            }

            if ($user->type === UserType::AGENCE) {
                $query->whereHas('backoffice', function ($qr) use ($user) {
                    $qr->where('pays', $user->agence->pays);
                });
            }

            $tarifs = $query->with('category:id,nom')->get();
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
            if (!in_array($user->type, [UserType::BACKOFFICE])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $request->validate([
                'category_id' => ['nullable', 'uuid', 'exists:category_products,id'],
                'type_expedition' => ['required', 'string', 'in:' . implode(',', array_map(fn($case) => $case->value, TypeExpedition::cases()))],
                'mode' => ['required', 'string'],
                'ligne' => ['nullable', 'string'],
                'montant_base' => ['required', 'numeric', 'min:0'],
                'pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
                'pays' => ['nullable', 'string'],
            ]);

            if ($request->category_id) {
                $category = CategoryProduct::find($request->category_id);
                if ($user->type === UserType::BACKOFFICE && $category->backoffice_id !== $user->backoffice_id) {
                    return response()->json(['success' => false, 'message' => 'Cette catégorie n\'appartient pas à votre backoffice.'], 403);
                }
            }

            $typeExpedition = TypeExpedition::tryFrom($request->type_expedition);

            // Vérifier la restriction pour le type groupage_ca (un seul par backoffice/mode/ligne)
            if ($typeExpedition === TypeExpedition::GROUPAGE_CA) {
                $exists = TarifGroupage::where('backoffice_id', $user->backoffice_id)
                    ->where('type_expedition', TypeExpedition::GROUPAGE_CA)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ce tarif groupage_ca existe déjà pour ce mode et cette ligne.'
                    ], 422);
                }
            }

            $tarif = TarifGroupage::create([
                'category_id' => $request->category_id,
                'type_expedition' => $request->type_expedition,
                'mode' => $request->mode,
                'ligne' => $request->ligne,
                'montant_base' => $request->montant_base,
                'pourcentage_prestation' => $request->pourcentage_prestation,
                'pays' => $request->pays ?? $user->backoffice->pays,
                'backoffice_id' => $user->backoffice_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tarif groupage créé avec succès.',
                'tarif' => $tarif
            ], 201);

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
                'ligne' => ['sometimes', 'string'],
                'mode' => ['sometimes', 'string'],
                'pays' => ['sometimes', 'string'],
                'category_id' => ['sometimes', 'uuid', 'exists:category_products,id'],
                'type_expedition' => ['sometimes', 'string', 'in:' . implode(',', array_map(fn($case) => $case->value, TypeExpedition::cases()))],
                'montant_base' => ['sometimes', 'numeric', 'min:0'],
                'pourcentage_prestation' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarif->update($request->only([
                'ligne',
                'mode',
                'pays',
                'category_id',
                'type_expedition',
                'montant_base',
                'pourcentage_prestation',
            ]));

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
