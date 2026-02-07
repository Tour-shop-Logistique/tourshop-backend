<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Agence;
use App\Models\TarifAgenceSimple;
use App\Models\TarifSimple;
use App\Enums\UserType;
use Illuminate\Support\Facades\Log;
use Exception;

class AgenceTarifSimpleController extends Controller
{
    /**
     * Liste des tarifs de l'agence.
     */
    public function list(Request $request)
    {
        try {
            $user = $request->user();
            // if ($user->type !== UserType::AGENCE) {
            //     return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            // }

            $query = TarifAgenceSimple::query();

            if ($user->type === UserType::AGENCE) {
                $agence = Agence::where('user_id', $user->id)->first();
                if (!$agence) {
                    return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
                }

                $query->where('agence_id', $agence->id);
            }

            // Vérification du pays pour les utilisateurs BACKOFFICE
            if ($user->type === UserType::BACKOFFICE) {
                $agence = Agence::where('id', $request->agence_id)->first();
                if (!$user->backoffice || $agence->pays !== $user->backoffice->pays) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès non autorisé : cette agence n\'appartient pas à votre pays.'
                    ], 403);
                }
                 if ($request->filled('agence_id')) {
                    $query->where('agence_id', $request->agence_id);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'L\'agence_id est obligatoire.'
                    ], 422);
                }
            }

            $tarifs = $query->with(['zone:id,nom'])->orderBy('indice')->get();

            return response()->json(['success' => true, 'tarifs' => $tarifs]);
        } catch (Exception $e) {
            Log::error('Erreur listing tarifs agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Créer un nouveau tarif pour l'agence.
     */
    public function add(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est de type agence et admin de son agence
            if ($user->type !== UserType::AGENCE || !$user->isAgenceAdmin()) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = $user->agence;
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            $request->validate([
                'tarif_simple_id' => ['required', 'uuid', 'exists:tarifs_simple,id'],
                'pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarifSimple = TarifSimple::actif()->find($request->tarif_simple_id);
            if (!$tarifSimple) {
                return response()->json(['success' => false, 'message' => 'Tarif de base introuvable ou inactif.'], 404);
            }

            // Vérifier si un tarif agence existe déjà pour ce tarif simple
            $exists = TarifAgenceSimple::where('agence_id', $agence->id)
                ->where('tarif_simple_id', $tarifSimple->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Un tarif existe déjà pour cette prestation."
                ], 422);
            }

            $tarif = TarifAgenceSimple::create([
                'agence_id' => $agence->id,
                'tarif_simple_id' => $tarifSimple->id,
                'pourcentage_prestation' => $request->pourcentage_prestation,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tarif agence créé avec succès.',
                'tarif' => $tarif
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur création tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur du serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour un tarif.
     */
    public function edit(Request $request, TarifAgenceSimple $tarif)
    {
        try {
            $user = $request->user();

            // if ($user->type !== UserType::AGENCE) {
            //     return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            // }

            // $agence = Agence::where('user_id', $user->id)->first();
            // if (!$agence) {
            //     return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            // }

            // Vérifier que l'utilisateur est de type agence et admin de son agence
            if ($user->type !== UserType::AGENCE || !$user->isAgenceAdmin()) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = $user->agence;
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            // Vérifier que le tarif appartient à cette agence
            if ($tarif->agence_id !== $agence->id) {
                return response()->json(['success' => false, 'message' => 'Tarif non trouvé.'], 404);
            }

            $request->validate([
                'pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarif->update($request->only(['pourcentage_prestation']));

            return response()->json([
                'success' => true,
                'message' => 'Tarif mis à jour avec succès.',
                'tarif' => $tarif //->load('tarifSimple')
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Afficher un tarif spécifique.
     */
    public function show(Request $request, TarifAgenceSimple $tarif)
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

            // Vérifier que le tarif appartient à cette agence
            if ($tarif->agence_id !== $agence->id) {
                return response()->json(['success' => false, 'message' => 'Tarif non trouvé.'], 404);
            }

            return response()->json([
                'success' => true,
                'tarif' => $tarif->load('tarifSimple')
            ]);
        } catch (Exception $e) {
            Log::error('Erreur affichage tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer un tarif.
     */
    public function delete(Request $request, TarifAgenceSimple $tarif)
    {
        try {
            $user = $request->user();

            // if ($user->type !== UserType::AGENCE) {
            //     return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            // }

            // $agence = Agence::where('user_id', $user->id)->first();
            // if (!$agence) {
            //     return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            // }

            // Vérifier que l'utilisateur est de type agence et admin de son agence
            if ($user->type !== UserType::AGENCE || !$user->isAgenceAdmin()) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = $user->agence;
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            // Vérifier que le tarif appartient à cette agence
            if ($tarif->agence_id !== $agence->id) {
                return response()->json(['success' => false, 'message' => 'Tarif non trouvé.'], 404);
            }

            $tarif->delete();

            return response()->json(['success' => true, 'message' => 'Tarif supprimé avec succès.']);
        } catch (Exception $e) {
            Log::error('Erreur suppression tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Activer/désactiver un tarif.
     */
    public function toggleStatus(Request $request, TarifAgenceSimple $tarif)
    {
        try {
            $user = $request->user();

            // if ($user->type !== UserType::AGENCE) {
            //     return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            // }

            // $agence = Agence::where('user_id', $user->id)->first();
            // if (!$agence) {
            //     return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            // }

            // Vérifier que l'utilisateur est de type agence et admin de son agence
            if ($user->type !== UserType::AGENCE || !$user->isAgenceAdmin()) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = $user->agence;
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            // Vérifier que le tarif appartient à cette agence
            if ($tarif->agence_id !== $agence->id) {
                return response()->json(['success' => false, 'message' => 'Tarif non trouvé.'], 404);
            }

            $tarif->actif = !$tarif->actif;
            $tarif->save();

            return response()->json([
                'success' => true,
                'message' => $tarif->actif ? 'Tarif activé.' : 'Tarif désactivé.',
                'tarif' => $tarif
            ]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
