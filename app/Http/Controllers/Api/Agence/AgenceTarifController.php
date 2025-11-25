<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Agence;
use App\Models\TarifAgence;
use App\Models\TarifSimple;
use App\Enums\UserType;
use App\Enums\ModeExpedition;
use App\Enums\TypeColis;
use Illuminate\Support\Facades\Log;
use Exception;

class AgenceTarifController extends Controller
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

            $query = TarifAgence::query();

            if ($user->type === UserType::AGENCE) {
                $agence = Agence::where('user_id', $user->id)->first();
                if (!$agence) {
                    return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
                }

                $query->where('agence_id', $agence->id);
            }

            if ($request->filled('agence_id')) {
                $query->where('agence_id', $request->agence_id);
            }

            $tarifs = $query->get();

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
                'prix_zones' => ['required', 'array', 'min:1'],
                'prix_zones.*.zone_destination_id' => ['required', 'string', 'exists:zones,id'],
                'prix_zones.*.pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            // Vérifier l'existence du tarif de simple et actif
            $tarifSimple = TarifSimple::actif()->find($request->tarif_simple_id);
            if (!$tarifSimple) {
                return response()->json(['success' => false, 'message' => 'Tarif de simple introuvable ou inactif.'], 404);
            }

            // Vérifier que toutes les zones fournies existent dans le tarif de simple
            $zonesSimple = collect($tarifSimple->prix_zones)->pluck('zone_destination_id')->toArray();
            $zonesAgence = collect($request->prix_zones)->pluck('zone_destination_id')->toArray();
            $zonesMissing = array_diff($zonesAgence, $zonesSimple);
            if (!empty($zonesMissing)) {
                return response()->json(['success' => false, 'message' => 'Zones non trouvées dans le tarif de simple: ' . implode(', ', $zonesMissing)], 422);
            }

            $tarif = TarifAgence::create([
                'agence_id' => $agence->id,
                'tarif_simple_id' => $tarifSimple->id,
                'prix_zones' => $request->prix_zones,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tarif créé avec succès.',
                'tarif' => $tarif //->load('tarifSimple')
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
    public function edit(Request $request, TarifAgence $tarif)
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
                'prix_zones' => ['sometimes', 'array', 'min:1'],
                'prix_zones.*.zone_destination_id' => ['required_with:prix_zones', 'string', 'exists:zones,id'],
                'prix_zones.*.pourcentage_prestation' => ['required_with:prix_zones', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarif->update($request->only(['prix_zones']));

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
    public function show(Request $request, TarifAgence $tarif)
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
    public function delete(Request $request, TarifAgence $tarif)
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
    public function toggleStatus(Request $request, TarifAgence $tarif)
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
