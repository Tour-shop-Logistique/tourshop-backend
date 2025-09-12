<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Agence;
use App\Models\Tarif;
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

            $tarifs = Tarif::where('agence_id', $agence->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'tarifs' => $tarifs
            ]);
        } catch (Exception $e) {
            Log::error('Erreur liste tarifs agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Créer un nouveau tarif pour l'agence.
     */
    public function createTarif(Request $request)
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
                'mode_expedition' => ['required', 'in:simple,groupage'],
                'type_colis' => ['required', 'in:document,colis_standard,colis_fragile,colis_volumineux,produit_alimentaire,electronique,vetement,autre'],
                'zone_depart_id' => ['required', 'uuid', 'exists:zones,id'],
                'zone_arrivee_id' => ['required', 'uuid', 'exists:zones,id'],
                'description' => ['nullable', 'string', 'max:1000'],
                'montant_base' => ['required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'pourcentage_prestation' => ['required_if:mode_expedition,simple', 'numeric', 'min:0', 'max:100'],
                'longueur_max_cm' => ['required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'largeur_max_cm' => ['required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'hauteur_max_cm' => ['required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'prix_entrepot' => ['required_if:mode_expedition,groupage', 'numeric', 'min:0'],
                'supplement_domicile_groupage' => ['nullable', 'numeric', 'min:0'],
                'indice_tranche' => ['required', 'numeric', 'min:0'],
                'facteur_division_volume' => ['sometimes', 'integer', 'min:1'],
                'prix_base' => ['nullable', 'numeric', 'min:0'],
                'prix_par_kg' => ['nullable', 'numeric', 'min:0'],
                'prix_par_km' => ['nullable', 'numeric', 'min:0'],
                'poids_min_kg' => ['nullable', 'numeric', 'min:0'],
                'poids_max_kg' => ['nullable', 'numeric', 'min:0'],
                'delai_livraison' => ['nullable', 'integer', 'min:1'],
                'actif' => ['sometimes', 'boolean']
            ]);

            $tarif = Tarif::create([
                'agence_id' => $agence->id,
                'nom' => $request->nom,
                'mode_expedition' => $request->mode_expedition,
                'type_colis' => $request->type_colis,
                'zone_depart_id' => $request->zone_depart_id,
                'zone_arrivee_id' => $request->zone_arrivee_id,
                'description' => $request->description,
                'montant_base' => $request->montant_base,
                'pourcentage_prestation' => $request->pourcentage_prestation,
                'longueur_max_cm' => $request->longueur_max_cm,
                'largeur_max_cm' => $request->largeur_max_cm,
                'hauteur_max_cm' => $request->hauteur_max_cm,
                'indice_tranche' => $request->indice_tranche,
                'facteur_division_volume' => $request->get('facteur_division_volume', 5000),
                'prix_entrepot' => $request->prix_entrepot,
                'supplement_domicile_groupage' => $request->supplement_domicile_groupage,
                'prix_base' => $request->prix_base,
                'prix_par_kg' => $request->prix_par_kg,
                'prix_par_km' => $request->prix_par_km,
                'poids_min_kg' => $request->poids_min_kg,
                'poids_max_kg' => $request->poids_max_kg,
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
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Afficher un tarif spécifique.
     */
    public function showTarif(Request $request, Tarif $tarif)
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
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour un tarif.
     */
    public function updateTarif(Request $request, Tarif $tarif)
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
                'mode_expedition' => ['sometimes', 'in:simple,groupage'],
                'type_colis' => ['sometimes', 'in:document,colis_standard,colis_fragile,colis_volumineux,produit_alimentaire,electronique,vetement,autre'],
                'zone_depart_id' => ['sometimes', 'uuid', 'exists:zones,id'],
                'zone_arrivee_id' => ['sometimes', 'uuid', 'exists:zones,id'],
                'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'montant_base' => ['sometimes', 'required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'pourcentage_prestation' => ['sometimes', 'required_if:mode_expedition,simple', 'numeric', 'min:0', 'max:100'],
                'longueur_max_cm' => ['sometimes', 'required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'largeur_max_cm' => ['sometimes', 'required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'hauteur_max_cm' => ['sometimes', 'required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'prix_entrepot' => ['sometimes', 'required_if:mode_expedition,groupage', 'numeric', 'min:0'],
                'supplement_domicile_groupage' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'indice_tranche' => ['sometimes', 'numeric', 'min:0'],
                'facteur_division_volume' => ['sometimes', 'integer', 'min:1'],
                'prix_base' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'prix_par_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'prix_par_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'poids_min_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'poids_max_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
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
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer un tarif.
     */
    public function deleteTarif(Request $request, Tarif $tarif)
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
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Activer/désactiver un tarif.
     */
    public function toggleStatusTarif(Request $request, Tarif $tarif)
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
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
