<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\Zone;
use App\Enums\UserType;
use Exception;

class ZoneController extends Controller
{
    /**
     * Lister les zones (avec filtres optionnels)
     */
    public function listZones(Request $request)
    {
        try {
            $query = Zone::query();
            if ($request->filled('actif')) {
                $query->where('actif', filter_var($request->actif, FILTER_VALIDATE_BOOLEAN));
            }
            if ($request->filled('search')) {
                $query->where('nom', 'like', '%' . $request->search . '%');
            }

            $zones = $query->orderBy('id')->get();
            return response()->json(['success' => true, 'zones' => $zones]);
        } catch (Exception $e) {
            Log::error('Erreur listing zones : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Créer une zone (id string: Z1..Z8)
     */
    public function addZone(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $request->validate([
                'id' => ['required', 'string', 'regex:/^Z[1-8]$/', 'unique:zones,id'],
                'nom' => ['required', 'string', 'max:255'],
                'pays' => ['required', 'array', 'min:1'],
                'pays.*' => ['string', 'max:120'],
            ]);

            $zone = Zone::create([
                'id' => $request->id,
                'nom' => $request->nom,
                'pays' => $request->pays,
            ]);

            return response()->json(['success' => true, 'message' => 'Zone créée avec succès.', 'zone' => $zone], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur création zone : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Afficher une zone
     */
    public function showZone(Request $request, Zone $zone)
    {
        try {
            return response()->json(['success' => true, 'zone' => $zone]);
        } catch (Exception $e) {
            Log::error('Erreur affichage zone : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour une zone
     */
    public function editZone(Request $request, Zone $zone)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $request->validate([
                'nom' => ['sometimes', 'string', 'max:255'],
                'pays' => ['sometimes', 'array', 'min:1'],
                'pays.*' => ['string', 'max:120'],
            ]);

            $zone->update($request->only(['nom', 'pays', 'actif']));
            return response()->json(['success' => true, 'message' => 'Zone mise à jour.', 'zone' => $zone]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour zone : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer une zone
     */
    public function deleteZone(Request $request, Zone $zone)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $zone->delete();
            return response()->json(['success' => true, 'message' => 'Zone supprimée avec succès.']);
        } catch (Exception $e) {
            Log::error('Erreur suppression zone : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Activer/désactiver une zone
     */
    public function toggleStatusZone(Request $request, Zone $zone)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $zone->actif = !$zone->actif;
            $zone->save();
            return response()->json(['success' => true, 'message' => $zone->actif ? 'Zone activée.' : 'Zone désactivée.', 'zone' => $zone]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut zone : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
