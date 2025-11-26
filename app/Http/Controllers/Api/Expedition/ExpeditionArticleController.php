<?php

namespace App\Http\Controllers\Api\Expedition;

use App\Http\Controllers\Controller;
use App\Models\Expedition;
use App\Models\ExpeditionArticle;
use App\Models\Produit;
use App\Services\ExpeditionTarificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExpeditionArticleController extends Controller
{
    protected ExpeditionTarificationService $tarificationService;

    public function __construct(ExpeditionTarificationService $tarificationService)
    {
        $this->tarificationService = $tarificationService;
    }

    /**
     * Lister les articles d'une expédition
     */
    public function list(string $expeditionId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $expedition = Expedition::where('id', $expeditionId)
                ->where(function($query) use ($user) {
                    if ($user->type === 'client') {
                        $query->where('client_id', $user->id);
                    } elseif ($user->type === 'agence') {
                        $query->whereHas('agence', function($q) use ($user) {
                            $q->where('user_id', $user->id);
                        });
                    }
                })
                ->with(['articles.produit.category'])
                ->first();

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            return response()->json([
                'message' => 'Articles récupérés avec succès',
                'data' => $expedition->articles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des articles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajouter un article à une expédition
     */
    public function add(Request $request, string $expeditionId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $expedition = Expedition::where('id', $expeditionId)
                ->where(function($query) use ($user) {
                    if ($user->type === 'client') {
                        $query->where('client_id', $user->id);
                    } elseif ($user->type === 'agence') {
                        $query->whereHas('agence', function($q) use ($user) {
                            $q->where('user_id', $user->id);
                        });
                    }
                })
                ->first();

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            // Vérifier que l'expédition peut encore être modifiée
            if (!in_array($expedition->statut, [Expedition::STATUT_EN_ATTENTE, Expedition::STATUT_ACCEPTED])) {
                return response()->json(['message' => 'Cette expédition ne peut plus être modifiée'], 422);
            }

            $validator = Validator::make($request->all(), [
                'produit_id' => 'nullable|uuid|exists:produits,id',
                'designation' => 'required|string|max:255',
                'reference' => 'nullable|string|max:100',
                'poids' => 'required|numeric|min:0.01',
                'longueur' => 'nullable|numeric|min:0.01',
                'largeur' => 'nullable|numeric|min:0.01',
                'hauteur' => 'nullable|numeric|min:0.01',
                'quantite' => 'required|integer|min:1',
                'valeur_declaree' => 'nullable|numeric|min:0',
                'description' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();

            // Si un produit est spécifié, récupérer ses informations
            if (!empty($validated['produit_id'])) {
                $produit = Produit::find($validated['produit_id']);
                if ($produit) {
                    // Utiliser les données du produit si non spécifiées
                    $validated['designation'] = $validated['designation'] ?: $produit->nom;
                    $validated['reference'] = $validated['reference'] ?: $produit->reference;
                }
            }

            $article = ExpeditionArticle::create(array_merge($validated, [
                'expedition_id' => $expeditionId
            ]));

            // Mettre à jour le tarif de l'expédition
            $this->tarificationService->mettreAJourTarifExpedition($expedition);

            // Recharger l'expédition avec les articles et les tarifs
            $expedition->load(['articles.produit.category']);

            return response()->json([
                'message' => 'Article ajouté avec succès',
                'data' => [
                    'article' => $article,
                    'expedition' => $expedition
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'ajout de l\'article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Modifier un article
     */
    public function edit(Request $request, string $expeditionId, string $articleId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $expedition = Expedition::where('id', $expeditionId)
                ->where(function($query) use ($user) {
                    if ($user->type === 'client') {
                        $query->where('client_id', $user->id);
                    } elseif ($user->type === 'agence') {
                        $query->whereHas('agence', function($q) use ($user) {
                            $q->where('user_id', $user->id);
                        });
                    }
                })
                ->first();

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            // Vérifier que l'expédition peut encore être modifiée
            if (!in_array($expedition->statut, [Expedition::STATUT_EN_ATTENTE, Expedition::STATUT_ACCEPTED])) {
                return response()->json(['message' => 'Cette expédition ne peut plus être modifiée'], 422);
            }

            $article = ExpeditionArticle::where('id', $articleId)
                ->where('expedition_id', $expeditionId)
                ->first();

            if (!$article) {
                return response()->json(['message' => 'Article non trouvé'], 404);
            }

            $validator = Validator::make($request->all(), [
                'designation' => 'sometimes|string|max:255',
                'reference' => 'sometimes|nullable|string|max:100',
                'poids' => 'sometimes|numeric|min:0.01',
                'longueur' => 'sometimes|nullable|numeric|min:0.01',
                'largeur' => 'sometimes|nullable|numeric|min:0.01',
                'hauteur' => 'sometimes|nullable|numeric|min:0.01',
                'quantite' => 'sometimes|integer|min:1',
                'valeur_declaree' => 'sometimes|nullable|numeric|min:0',
                'description' => 'sometimes|nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $article->update($validator->validated());

            // Mettre à jour le tarif de l'expédition
            $this->tarificationService->mettreAJourTarifExpedition($expedition);

            // Recharger l'expédition avec les articles
            $expedition->load(['articles.produit.category']);

            return response()->json([
                'message' => 'Article modifié avec succès',
                'data' => [
                    'article' => $article,
                    'expedition' => $expedition
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la modification de l\'article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un article
     */
    public function delete(string $expeditionId, string $articleId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $expedition = Expedition::where('id', $expeditionId)
                ->where(function($query) use ($user) {
                    if ($user->type === 'client') {
                        $query->where('client_id', $user->id);
                    } elseif ($user->type === 'agence') {
                        $query->whereHas('agence', function($q) use ($user) {
                            $q->where('user_id', $user->id);
                        });
                    }
                })
                ->first();

            if (!$expedition) {
                return response()->json(['message' => 'Expédition non trouvée'], 404);
            }

            // Vérifier que l'expédition peut encore être modifiée
            if (!in_array($expedition->statut, [Expedition::STATUT_EN_ATTENTE, Expedition::STATUT_ACCEPTED])) {
                return response()->json(['message' => 'Cette expédition ne peut plus être modifiée'], 422);
            }

            $article = ExpeditionArticle::where('id', $articleId)
                ->where('expedition_id', $expeditionId)
                ->first();

            if (!$article) {
                return response()->json(['message' => 'Article non trouvé'], 404);
            }

            $article->delete();

            // Mettre à jour le tarif de l'expédition
            $this->tarificationService->mettreAJourTarifExpedition($expedition);

            // Recharger l'expédition avec les articles
            $expedition->load(['articles.produit.category']);

            return response()->json([
                'message' => 'Article supprimé avec succès',
                'data' => $expedition
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simuler le tarif avec des articles
     */
    public function simulate(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $validator = Validator::make($request->all(), [
                'expedition' => 'required|array',
                'expedition.mode_expedition' => 'required|in:simple,groupage',
                'expedition.zone_depart_id' => 'required|uuid|exists:zones,id',
                'expedition.zone_destination_id' => 'required|uuid|exists:zones,id',
                'expedition.agence_id' => 'nullable|uuid|exists:agences,id',
                'articles' => 'required|array|min:1',
                'articles.*.designation' => 'required|string|max:255',
                'articles.*.poids' => 'required|numeric|min:0.01',
                'articles.*.longueur' => 'nullable|numeric|min:0.01',
                'articles.*.largeur' => 'nullable|numeric|min:0.01',
                'articles.*.hauteur' => 'nullable|numeric|min:0.01',
                'articles.*.quantite' => 'required|integer|min:1',
                'articles.*.produit_id' => 'nullable|uuid|exists:produits,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();

            $resultat = $this->tarificationService->simulerTarifExpedition(
                $validated['expedition'],
                $validated['articles']
            );

            return response()->json([
                'message' => 'Simulation de tarif réussie',
                'data' => $resultat
            ]);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la simulation du tarif',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
