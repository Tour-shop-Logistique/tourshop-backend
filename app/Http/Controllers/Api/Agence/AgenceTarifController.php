<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Agence;
use App\Models\TarifAgence;
use App\Models\TarifBase;
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
    public function listTarifs(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            $tarifs = TarifAgence::with(['tarifBase'])
                ->where('agence_id', $agence->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'tarifs' => $tarifs
            ]);
        } catch (Exception $e) {
            Log::error('Erreur listing tarifs agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Créer un nouveau tarif pour l'agence.
     */
    public function addTarif(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            $request->validate([
                'tarif_base_id' => ['required', 'uuid', 'exists:tarifs_base,id'],
                'prix_zones' => ['required', 'array', 'min:1'],
                'prix_zones.*.zone_destination_id' => ['required', 'string', 'exists:zones,id'],
                'prix_zones.*.pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            // Vérifier l'existence du tarif de base et actif
            $tarifBase = TarifBase::actif()->find($request->tarif_base_id);
            if (!$tarifBase) {
                return response()->json(['success' => false, 'message' => 'Tarif de base introuvable ou inactif.'], 404);
            }

            // Vérifier que toutes les zones fournies existent dans le tarif de base
            $zonesBase = collect($tarifBase->prix_zones)->pluck('zone_destination_id')->toArray();
            $zonesAgence = collect($request->prix_zones)->pluck('zone_destination_id')->toArray();
            $zonesMissing = array_diff($zonesAgence, $zonesBase);
            if (!empty($zonesMissing)) {
                return response()->json(['success' => false, 'message' => 'Zones non trouvées dans le tarif de base: ' . implode(', ', $zonesMissing)], 422);
            }

            $tarif = TarifAgence::create([
                'agence_id' => $agence->id,
                'tarif_base_id' => $tarifBase->id,
                'prix_zones' => $request->prix_zones,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tarif créé avec succès.',
                'tarif' => $tarif->load('tarifBase')
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur création tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Afficher un tarif spécifique.
     */
    public function showTarif(Request $request, TarifAgence $tarif)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            // Vérifier que le tarif appartient à cette agence
            if ($tarif->agence_id !== $agence->id) {
                return response()->json(['success' => false, 'message' => 'Tarif non trouvé.'], 404);
            }

            return response()->json([
                'success' => true,
                'tarif' => $tarif->load('tarifBase')
            ]);
        } catch (Exception $e) {
            Log::error('Erreur affichage tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour un tarif.
     */
    public function updateTarif(Request $request, TarifAgence $tarif)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
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
                'tarif' => $tarif->load('tarifBase')
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
     * Supprimer un tarif.
     */
    public function deleteTarif(Request $request, TarifAgence $tarif)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
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
    public function toggleStatusTarif(Request $request, TarifAgence $tarif)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            // Vérifier que le tarif appartient à cette agence
            if ($tarif->agence_id !== $agence->id) {
                return response()->json(['success' => false, 'message' => 'Tarif non trouvé.'], 404);
            }

            $tarif->update(['actif' => !$tarif->actif]);

            return response()->json([
                'success' => true,
                'message' => $tarif->actif ? 'Tarif activé.' : 'Tarif désactivé.',
                'tarif' => $tarif->load('tarifBase')
            ]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
