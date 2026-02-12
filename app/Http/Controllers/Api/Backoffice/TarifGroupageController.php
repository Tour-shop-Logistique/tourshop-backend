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

            $tarifs = $query->with('category:id,nom')->orderBy('type_expedition')->get();
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
            $ligne = ($request->has('ligne') && !is_null($request->ligne) && $request->ligne !== '')
                ? strtolower(trim($request->ligne))
                : null;

            // Restriction spéciale pour GROUPAGE_CA : un seul par backoffice
            if ($typeExpedition === TypeExpedition::GROUPAGE_CA) {
                $existsCA = TarifGroupage::where('backoffice_id', $user->backoffice_id)
                    ->where('type_expedition', TypeExpedition::GROUPAGE_CA)
                    ->exists();

                if ($existsCA) {
                    return response()->json([
                        'success' => false,
                        'message' => "Un tarif de type groupage_ca existe déjà pour votre backoffice (limite : un seul par backoffice)."
                    ], 422);
                }
            }

            // Vérifier si un tarif pour cette ligne et ce type existe déjà pour ce backoffice (uniquement si ligne non nulle)
            if (!is_null($ligne)) {
                $exists = TarifGroupage::where('backoffice_id', $user->backoffice_id)
                    ->where('type_expedition', $request->type_expedition)
                    ->where('category_id', $request->category_id)
                    ->where('ligne', $ligne)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => "Un tarif pour ce type d'expédition et cette ligne ('" . $ligne . "') existe déjà."
                    ], 422);
                }
            }


            $tarif = TarifGroupage::create([
                'category_id' => $request->category_id,
                'type_expedition' => $request->type_expedition,
                'mode' => $request->mode,
                'ligne' => $ligne,
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

            $ancienMontantBase = $tarif->montant_base;
            $ancienPourcentage = $tarif->pourcentage_prestation;

            $data = $request->only([
                'mode',
                'pays',
                'category_id',
                'type_expedition',
                'montant_base',
                'pourcentage_prestation',
            ]);

            if ($request->has('ligne') || $request->has('type_expedition')) {
                $ligne = $request->has('ligne')
                    ? ((!is_null($request->ligne) && $request->ligne !== '') ? strtolower(trim($request->ligne)) : null)
                    : $tarif->ligne;
                $typeExpeditionRaw = $request->type_expedition ?? $tarif->type_expedition;
                $typeExpedition = $typeExpeditionRaw instanceof TypeExpedition ? $typeExpeditionRaw : TypeExpedition::tryFrom($typeExpeditionRaw);

                // Restriction spéciale pour GROUPAGE_CA : un seul par backoffice
                if ($typeExpedition === TypeExpedition::GROUPAGE_CA) {
                    $existsCA = TarifGroupage::where('backoffice_id', $tarif->backoffice_id)
                        ->where('type_expedition', TypeExpedition::GROUPAGE_CA)
                        ->where('id', '!=', $tarif->id)
                        ->exists();

                    if ($existsCA) {
                        return response()->json([
                            'success' => false,
                            'message' => "Un autre tarif de type groupage_ca existe déjà pour votre backoffice."
                        ], 422);
                    }
                }

                // Vérifier l'unicité par ligne (uniquement si ligne non nulle)
                if (!is_null($ligne)) {
                    $exists = TarifGroupage::where('backoffice_id', $tarif->backoffice_id)
                        ->where('type_expedition', $typeExpeditionRaw)
                        ->where('ligne', $ligne)
                        ->where('id', '!=', $tarif->id)
                        ->exists();

                    if ($exists) {
                        return response()->json([
                            'success' => false,
                            'message' => "Un autre tarif pour ce type d'expédition et cette ligne ('" . $ligne . "') existe déjà."
                        ], 422);
                    }
                }

                if ($request->has('ligne')) {
                    $data['ligne'] = $ligne;
                }
            }

            $tarif->update($data);

            // Si le montant de base ou le pourcentage a changé, on répercute sur les agences
            if ($tarif->montant_base != $ancienMontantBase || $tarif->pourcentage_prestation != $ancienPourcentage) {
                $this->mettreAJourTarifsAgence($tarif, $ancienMontantBase, $ancienPourcentage);
            }

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

    /**
     * Mettre à jour les tarifs d'agence liés en cas de changement du tarif de base
     */
    private function mettreAJourTarifsAgence(TarifGroupage $tarif, $ancienMontantBase, $ancienPourcentage)
    {
        // Différence de pourcentage à appliquer aux agences
        $deltaPourcentage = $tarif->pourcentage_prestation - $ancienPourcentage;

        // Récupérer tous les tarifs d'agence liés à ce tarif de groupage
        $tarifsAgence = $tarif->tarifsAgence;

        foreach ($tarifsAgence as $tarifAgence) {
            // Si le pourcentage du backoffice a changé, on applique le delta au pourcentage de l'agence
            if ($deltaPourcentage != 0) {
                $tarifAgence->pourcentage_prestation = max(0, min(100, $tarifAgence->pourcentage_prestation + $deltaPourcentage));
            }

            // Note: le montant_base est déjà géré par le boot saving() de TarifAgenceGroupage 
            // qui va chercher le montant_base du parent si tarif_groupage_id est présent.
            // Mais pour forcer l'exécution de ce boot saving() et être sûr que tout est recalculé, 
            // on appelle save(). On peut aussi affecter explicitement pour plus de clarté.

            if ($tarif->montant_base != $ancienMontantBase) {
                $tarifAgence->montant_base = $tarif->montant_base;
            }

            $tarifAgence->save();
        }
    }
}
