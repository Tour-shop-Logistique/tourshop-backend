<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agence;
use App\Models\Colis;
use App\Models\User;
use App\Enums\UserType;
use App\Enums\ExpeditionStatus;
use Illuminate\Support\Facades\Log;
use Exception;

class AgenceColisController extends Controller
{
    /**
     * Liste tous les colis rattachés aux expéditions de l'agence.
     */
    public function colis(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if (!$user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Aucune agence rattachée à cet utilisateur.'], 404);
            }

            $query = Colis::whereHas('expedition', function ($q) use ($user) {
                $q->where('agence_id', $user->agence_id);
            })->with(['expedition:id,reference,statut_expedition,pays_depart,pays_destination', 'category:id,nom']);

            // Filtrage par statut de l'expédition
            if ($status = $request->get('status')) {
                $query->whereHas('expedition', function ($q) use ($status) {
                    $q->where('statut_expedition', $status);
                });
            }

            // Filtrage par date de création
            if ($request->filled('date_debut')) {
                $query->whereDate('created_at', '>=', $request->get('date_debut'));
            }
            if ($request->filled('date_fin')) {
                $query->whereDate('created_at', '<=', $request->get('date_fin'));
            }

            // Recherche par code colis ou référence expédition
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('code_colis', 'like', "%{$search}%")
                        ->orWhereHas('expedition', function ($sq) use ($search) {
                            $sq->where('reference', 'like', "%{$search}%");
                        });
                });
            }

            $colis = $query->latest()->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $colis->items(),
                'meta' => [
                    'current_page' => $colis->currentPage(),
                    'last_page' => $colis->lastPage(),
                    'per_page' => $colis->perPage(),
                    'total' => $colis->total(),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Erreur liste colis agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Détails d'un colis spécifique.
     */
    public function show(Request $request, string $id)
    {
        try {
            $user = $request->user();

            $colis = Colis::with(['expedition', 'category'])->find($id);

            if (!$colis) {
                return response()->json(['success' => false, 'message' => 'Colis introuvable.'], 404);
            }

            // Vérifier que le colis appartient à l'agence de l'utilisateur
            if ($user->type !== UserType::ADMIN && $colis->expedition->agence_id !== $user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            return response()->json([
                'success' => true,
                'colis' => $colis
            ]);
        } catch (Exception $e) {
            Log::error('Erreur détails colis agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }
}
