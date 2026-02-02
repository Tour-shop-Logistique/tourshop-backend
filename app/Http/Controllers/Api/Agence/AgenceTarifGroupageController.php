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
use App\Enums\TypeExpedition;
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

            $tarifs = $query->with(['category:id,nom'])->get();

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
                'pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarifGroupage = TarifGroupage::find($request->tarif_groupage_id);

            if (!$tarifGroupage || !$tarifGroupage->actif) {
                return response()->json(['success' => false, 'message' => 'Tarif groupage backoffice introuvable ou inactif.'], 404);
            }

            $typeExpedition = $tarifGroupage->type_expedition;
            $ligne = $tarifGroupage->ligne ? strtolower(trim($tarifGroupage->ligne)) : null;

            // Restriction spéciale pour GROUPAGE_CA : un seul par agence
            if ($typeExpedition === TypeExpedition::GROUPAGE_CA) {
                $existsCA = TarifAgenceGroupage::where('agence_id', $agence->id)
                    ->where('type_expedition', TypeExpedition::GROUPAGE_CA)
                    ->exists();

                if ($existsCA) {
                    return response()->json([
                        'success' => false,
                        'message' => "Un tarif d'agence pour le type groupage_ca existe déjà."
                    ], 422);
                }
            }

            if (!is_null($ligne)) {
                // Vérifier si un tarif pour cette ligne et ce type existe déjà pour cette agence
                $exists = TarifAgenceGroupage::where('agence_id', $agence->id)
                    ->where('type_expedition', $typeExpedition)
                    ->where('category_id', $tarifGroupage->category_id)
                    ->where('ligne', $ligne)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => "Un tarif d'agence pour ce type d'expédition, cette categorie et cette ligne ('" . ($ligne ?? 'vide') . "') existe déjà."
                    ], 422);
                }
            }

            $tarif = TarifAgenceGroupage::create([
                'agence_id' => $agence->id,
                'tarif_groupage_id' => $tarifGroupage->id,
                'type_expedition' => $typeExpedition,
                'mode' => $tarifGroupage->mode,
                'ligne' => $ligne,
                'category_id' => $tarifGroupage->category_id,
                'pourcentage_prestation' => $request->pourcentage_prestation,
                'pays' => $tarifGroupage->pays,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tarif agence groupage créé avec succès.',
                'tarif' => $tarif
            ], 201);

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
                'pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarif->update($request->only(['pourcentage_prestation']));

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

            return response()->json(['success' => true, 'tarif' => $tarif->load(['tarifGroupage', 'category'])]);
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
