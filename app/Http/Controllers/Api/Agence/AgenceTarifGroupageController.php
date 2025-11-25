<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\TarifAgenceGroupage;
use App\Models\TarifGroupage;
use App\Models\Agence;
use App\Enums\UserType;
use Exception;

class AgenceTarifGroupageController extends Controller
{
    public function list(Request $request)
    {
        try {
            $user = $request->user();

            $query = TarifAgenceGroupage::query();

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

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            $tarifs = $query->get();
            return response()->json(['success' => true, 'tarifs' => $tarifs]);
        } catch (Exception $e) {
            Log::error('Erreur listing tarifs agence groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function add(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE || !$user->isAgenceAdmin()) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = $user->agence;
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            $request->validate([
                'tarif_groupage_id' => ['required', 'uuid', 'exists:tarifs_groupage,id'],
                'prix_modes' => ['required', 'array', 'min:1'],
                'prix_modes.*.mode' => ['required', 'string'],
                'prix_modes.*.pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarifGroupage = TarifGroupage::find($request->tarif_groupage_id);
            if (!$tarifGroupage || !$tarifGroupage->actif) {
                return response()->json(['success' => false, 'message' => 'Tarif groupage backoffice introuvable ou inactif.'], 404);
            }

            // Vérifier que tous les modes fournis existent dans le tarif backoffice
            $modesGroupage = collect($tarifGroupage->prix_modes ?? [])->pluck('mode')->toArray();
            $modesAgence = collect($request->prix_modes)->pluck('mode')->toArray();
            $modesMissing = array_diff($modesAgence, $modesGroupage);
            if (!empty($modesMissing)) {
                return response()->json(['success' => false, 'message' => 'Modes non trouvés dans le tarif backoffice: ' . implode(', ', $modesMissing)], 422);
            }

            $tarif = TarifAgenceGroupage::create([
                'agence_id' => $agence->id,
                'tarif_groupage_id' => $tarifGroupage->id,
                'category_id' => $tarifGroupage->category_id,
                'prix_modes' => $request->prix_modes,
            ]);

            return response()->json(['success' => true, 'message' => 'Tarif agence groupage créé.', 'tarif' => $tarif], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur création tarif agence groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function edit(Request $request, TarifAgenceGroupage $tarif)
    {
        try {
            $user = $request->user();
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
                'prix_modes' => ['sometimes', 'array', 'min:1'],
                'prix_modes.*.mode' => ['required_with:prix_modes', 'string'],
                'prix_modes.*.pourcentage_prestation' => ['required_with:prix_modes', 'numeric', 'min:0', 'max:100'],
            ]);

            if ($request->has('prix_modes')) {
                // S'assurer que les modes existent dans le backoffice
                $tarifGroupage = $tarif->tarifGroupage;
                $modesGroupage = collect($tarifGroupage->prix_modes ?? [])->pluck('mode')->toArray();
                $modesAgence = collect($request->prix_modes)->pluck('mode')->toArray();
                $modesMissing = array_diff($modesAgence, $modesGroupage);
                if (!empty($modesMissing)) {
                    return response()->json(['success' => false, 'message' => 'Modes non trouvés dans le tarif backoffice: ' . implode(', ', $modesMissing)], 422);
                }
            }

            $tarif->update($request->only(['prix_modes']));

            return response()->json(['success' => true, 'message' => 'Tarif agence groupage mis à jour.', 'tarif' => $tarif]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour tarif agence groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, TarifAgenceGroupage $tarif)
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

            return response()->json(['success' => true, 'tarif' => $tarif->load('tarifGroupage')]);
        } catch (Exception $e) {
            Log::error('Erreur affichage tarif agence groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, TarifAgenceGroupage $tarif)
    {
        try {
            $user = $request->user();
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
            return response()->json(['success' => true, 'message' => 'Tarif agence groupage supprimé.']);
        } catch (Exception $e) {
            Log::error('Erreur suppression tarif agence groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function toggleStatus(Request $request, TarifAgenceGroupage $tarif)
    {
        try {
            $user = $request->user();
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

            return response()->json(['success' => true, 'message' => $tarif->actif ? 'Tarif activé.' : 'Tarif désactivé.', 'tarif' => $tarif]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut tarif agence groupage : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
