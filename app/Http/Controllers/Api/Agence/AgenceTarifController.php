<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Agence;
use App\Models\Tarif;
use App\Enums\UserType;
use Illuminate\Support\Facades\Log;
use Exception;

class AgenceTarifController extends Controller
{
    /**
     * Liste des tarifs de l'agence.
     */
    public function index(Request $request)
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

            $tarifs = Tarif::where('agence_id', $agence->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'tarifs' => $tarifs
            ]);
        } catch (Exception $e) {
            Log::error('Erreur liste tarifs agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Créer un nouveau tarif pour l'agence.
     */
    public function store(Request $request)
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
                'nom' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:1000'],
                'prix_base' => ['required', 'numeric', 'min:0'],
                'prix_par_kg' => ['nullable', 'numeric', 'min:0'],
                'prix_par_km' => ['nullable', 'numeric', 'min:0'],
                'zone_couverture' => ['nullable', 'numeric', 'min:0'],
                'delai_livraison' => ['nullable', 'integer', 'min:1'],
                'actif' => ['sometimes', 'boolean']
            ]);

            $tarif = Tarif::create([
                'agence_id' => $agence->id,
                'nom' => $request->nom,
                'description' => $request->description,
                'prix_base' => $request->prix_base,
                'prix_par_kg' => $request->prix_par_kg,
                'prix_par_km' => $request->prix_par_km,
                'zone_couverture' => $request->zone_couverture,
                'delai_livraison' => $request->delai_livraison,
                'actif' => $request->get('actif', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tarif créé avec succès.',
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
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Afficher un tarif spécifique.
     */
    public function show(Request $request, Tarif $tarif)
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
                'tarif' => $tarif
            ]);
        } catch (Exception $e) {
            Log::error('Erreur affichage tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Mettre à jour un tarif.
     */
    public function update(Request $request, Tarif $tarif)
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
                'nom' => ['sometimes', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'prix_base' => ['sometimes', 'numeric', 'min:0'],
                'prix_par_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'prix_par_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'zone_couverture' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'delai_livraison' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'actif' => ['sometimes', 'boolean']
            ]);

            $tarif->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tarif mis à jour avec succès.',
                'tarif' => $tarif
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Supprimer un tarif.
     */
    public function destroy(Request $request, Tarif $tarif)
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

            return response()->json([
                'success' => true,
                'message' => 'Tarif supprimé avec succès.'
            ]);
        } catch (Exception $e) {
            Log::error('Erreur suppression tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Activer/désactiver un tarif.
     */
    public function toggleStatus(Request $request, Tarif $tarif)
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
                'tarif' => $tarif
            ]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut tarif agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }
}
