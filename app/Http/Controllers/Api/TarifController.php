<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Tarif;
use App\Models\Agence;
use App\Enums\UserType;
use Illuminate\Support\Facades\Log;
use Exception;

class TarifController extends Controller
{
    /**
     * Affiche la liste des tarifs pour l'agence de l'utilisateur authentifié.
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
                return response()->json(['success' => false, 'message' => 'Agence non trouvée pour cet utilisateur.'], 404);
            }

            $tarifs = Tarif::where('agence_id', $agence->id)->get();

            return response()->json(['success' => true, 'tarifs' => $tarifs->toArray()]);

        } catch (Exception $e) {
            Log::error('Erreur index tarifs: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur inattendue lors de la récupération des tarifs.'], 500);
        }
    }

    /**
     * Crée un nouveau tarif pour l'agence de l'utilisateur authentifié.
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
                return response()->json(['success' => false, 'message' => 'Agence non trouvée pour cet utilisateur.'], 404);
            }

            // Validation des données du tarif (règle 'default' supprimée)
            $request->validate([
                'nom' => 'nullable|string|max:255',
                'type_colis' => 'nullable|string|max:255',
                'prix_base' => 'required|numeric|min:0',
                'prix_par_km' => 'required|numeric|min:0',
                'prix_par_kg' => 'required|numeric|min:0',
                'poids_max_kg' => 'nullable|numeric|min:0',
                'distance_min_km' => 'nullable|integer|min:0',
                'distance_max_km' => 'nullable|integer|min:0',
                'supplement_domicile' => 'nullable|numeric|min:0', // 'default:0' supprimé
                'supplement_express' => 'nullable|numeric|min:0', // 'default:0' supprimé
                'actif' => 'boolean', // 'default:true' supprimé
            ]);

            $tarif = Tarif::create([
                'agence_id' => $agence->id,
                'nom' => $request->nom,
                'type_colis' => $request->type_colis,
                'prix_base' => $request->prix_base,
                'prix_par_km' => $request->prix_par_km,
                'prix_par_kg' => $request->prix_par_kg,
                'poids_max_kg' => $request->poids_max_kg,
                'distance_min_km' => $request->distance_min_km,
                'distance_max_km' => $request->distance_max_km,
                'supplement_domicile' => $request->input('supplement_domicile', 0),
                'supplement_express' => $request->input('supplement_express', 0),
                'actif' => $request->input('actif', true),
            ]);

            return response()->json(['success' => true, 'message' => 'Tarif créé avec succès.', 'tarif' => $tarif->toArray()], 201);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur store tarif: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur inattendue lors de la création du tarif.'], 500);
        }
    }

    /**
     * Affiche les détails d'un tarif spécifique.
     */
    public function show(Request $request, Tarif $tarif)
    {
        try {
            $user = $request->user();
            $agence = Agence::where('user_id', $user->id)->first();

            if (!$agence || $tarif->agence_id !== $agence->id) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé ou tarif introuvable.'], 403);
            }

            return response()->json(['success' => true, 'tarif' => $tarif->toArray()]);

        } catch (Exception $e) {
            Log::error('Erreur show tarif: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur inattendue lors de l\'affichage du tarif.'], 500);
        }
    }

    /**
     * Met à jour un tarif existant pour l'agence de l'utilisateur authentifié.
     */
    public function update(Request $request, Tarif $tarif)
    {
        try {
            $user = $request->user();
            $agence = Agence::where('user_id', $user->id)->first();

            if (!$agence || $tarif->agence_id !== $agence->id) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé ou tarif introuvable.'], 403);
            }

            // Validation des données de mise à jour (règle 'default' supprimée)
            $request->validate([
                'nom' => 'sometimes|nullable|string|max:255',
                'type_colis' => 'sometimes|nullable|string|max:255',
                'prix_base' => 'sometimes|numeric|min:0', // 'required' pour s'assurer que si 'sometimes' il est bien fourni
                'prix_par_km' => 'sometimes|numeric|min:0',
                'prix_par_kg' => 'sometimes|numeric|min:0',
                'poids_max_kg' => 'sometimes|nullable|numeric|min:0',
                'distance_min_km' => 'sometimes|nullable|integer|min:0',
                'distance_max_km' => 'sometimes|nullable|integer|min:0',
                'supplement_domicile' => 'sometimes|nullable|numeric|min:0',
                'supplement_express' => 'sometimes|nullable|numeric|min:0',
                'actif' => 'sometimes|boolean',
            ]);

            $tarif->update($request->all());

            return response()->json(['success' => true, 'message' => 'Tarif mis à jour avec succès.', 'tarif' => $tarif->toArray()]);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur update tarif: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur inattendue lors de la mise à jour du tarif.'], 500);
        }
    }

    /**
     * Supprime un tarif pour l'agence de l'utilisateur authentifié.
     */
    public function destroy(Request $request, Tarif $tarif)
    {
        try {
            $user = $request->user();
            $agence = Agence::where('user_id', $user->id)->first();

            if (!$agence || $tarif->agence_id !== $agence->id) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé ou tarif introuvable.'], 403);
            }

            $tarif->delete();

            return response()->json(['success' => true, 'message' => 'Tarif supprimé avec succès.'], 200);

        } catch (Exception $e) {
            Log::error('Erreur destroy tarif: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur inattendue lors de la suppression du tarif.'], 500);
        }
    }
}