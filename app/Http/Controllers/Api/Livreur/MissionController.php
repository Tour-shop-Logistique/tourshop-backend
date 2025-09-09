<?php

namespace App\Http\Controllers\Api\Livreur;

use App\Http\Controllers\Controller;
use App\Models\Colis;
use App\Models\User;
use Illuminate\Http\Request;
use App\Enums\ColisStatus;

class MissionController extends Controller
{
    public function dashboard(Request $request)
    {
        $livreur = $request->user();
        
        $stats = [
            'missions_en_cours' => Colis::pourLivreur($livreur->id)->enCours()->count(),
            'missions_terminees_aujourdhui' => Colis::pourLivreur($livreur->id)
                ->where('status', ColisStatus::LIVRE)
                ->whereDate('date_livraison', today())
                ->count(),
            'revenus_du_jour' => Colis::pourLivreur($livreur->id)
                ->where('status', ColisStatus::LIVRE)
                ->whereDate('date_livraison', today())
                ->sum('commission_livreur'),
            'revenus_du_mois' => Colis::pourLivreur($livreur->id)
                ->where('status', ColisStatus::LIVRE)
                ->whereMonth('date_livraison', now()->month)
                ->sum('commission_livreur')
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'livreur' => $livreur
        ]);
    }

    public function missionsDisponibles(Request $request)
    {
        $latitude = $request->get('latitude');
        $longitude = $request->get('longitude');
        $rayon = $request->get('rayon', 15); // 15km par défaut

        $missionsQuery = Colis::whereIn('status', [ColisStatus::VALIDE, ColisStatus::EN_ENLEVEMENT])
                              ->whereNull('livreur_id')
                              ->with(['expediteur', 'agence']);

        // Si position fournie, filtrer par proximité
        if ($latitude && $longitude) {
            $missionsQuery->selectRaw(
                "*, (6371 * acos(cos(radians($latitude)) * cos(radians(lat_enlevement)) * cos(radians(lng_enlevement) - radians($longitude)) + sin(radians($latitude)) * sin(radians(lat_enlevement)))) AS distance"
            )->havingRaw("distance <= $rayon")->orderBy('distance');
        }

        $missions = $missionsQuery->get();

        return response()->json([
            'success' => true,
            'missions' => $missions
        ]);
    }

    public function accepterMission(Request $request, $colisId)
    {
        $colis = Colis::where('id', $colisId)
                     ->whereNull('livreur_id')
                     ->whereIn('status', [ColisStatus::VALIDE, ColisStatus::EN_ENLEVEMENT])
                     ->first();

        if (!$colis) {
            return response()->json([
                'success' => false,
                'message' => 'Mission non disponible'
            ], 404);
        }

        $colis->update([
            'livreur_id' => $request->user()->id,
            'status' => ColisStatus::EN_ENLEVEMENT
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mission acceptée avec succès',
            'colis' => $colis->load(['expediteur', 'agence'])
        ]);
    }

    public function mesMissions(Request $request)
    {
        $missions = Colis::pourLivreur($request->user()->id)
                        ->with(['expediteur', 'agence'])
                        ->orderByRaw("FIELD(status, 'en_enlevement', 'recupere', 'en_transit', 'en_livraison')")
                        ->orderBy('created_at', 'desc')
                        ->paginate(10);

        return response()->json([
            'success' => true,
            'missions' => $missions
        ]);
    }

    public function confirmerEnlevement(Request $request, $colisId)
    {
        $request->validate([
            'photo_enlevement' => 'nullable|image|max:2048',
            'notes' => 'nullable|string|max:500'
        ]);

        $colis = Colis::pourLivreur($request->user()->id)
                     ->where('id', $colisId)
                     ->where('status', ColisStatus::EN_ENLEVEMENT)
                     ->first();

        if (!$colis) {
            return response()->json([
                'success' => false,
                'message' => 'Colis non trouvé'
            ], 404);
        }

        $photoPath = null;
        if ($request->hasFile('photo_enlevement')) {
            $photoPath = $request->file('photo_enlevement')->store('enlevements', 'public');
        }

        $colis->update([
            'status' => ColisStatus::RECUPERE,
            'notes_livreur' => $request->notes
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Enlèvement confirmé avec succès'
        ]);
    }

    public function confirmerLivraison(Request $request, $colisId)
    {
        $request->validate([
            'photo_livraison' => 'required|image|max:2048',
            'signature_destinataire' => 'nullable|string'
        ]);

        $colis = Colis::pourLivreur($request->user()->id)
                     ->where('id', $colisId)
                     ->whereIn('status', [ColisStatus::EN_LIVRAISON, ColisStatus::EN_TRANSIT])
                     ->first();

        if (!$colis) {
            return response()->json([
                'success' => false,
                'message' => 'Colis non trouvé'
            ], 404);
        }

        $photoPath = $request->file('photo_livraison')->store('livraisons', 'public');

        $colis->update([
            'status' => ColisStatus::LIVRE,
            'photo_livraison' => $photoPath,
            'signature_destinataire' => $request->signature_destinataire,
            'date_livraison' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Livraison confirmée avec succès',
            'commission' => $colis->commission_livreur
        ]);
    }

    public function changerDisponibilite(Request $request)
    {
        $request->validate([
            'disponible' => 'required|boolean'
        ]);

        $request->user()->update([
            'disponible' => $request->disponible
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilité mise à jour'
        ]);
    }
}