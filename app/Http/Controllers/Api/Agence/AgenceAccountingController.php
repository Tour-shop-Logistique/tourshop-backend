<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use App\Models\Expedition;
use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class AgenceAccountingController extends Controller
{
    /**
     * Rapport comptable pour l'agence
     */
    public function report(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE && $user->type !== UserType::ADMIN && $user->type !== UserType::BACKOFFICE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agenceId = $user->agence_id;
            
            // Si admin/backoffice et agence_id fourni en paramètre
            if (($user->type === UserType::ADMIN || $user->type === UserType::BACKOFFICE) && $request->filled('agence_id')) {
                $agenceId = $request->agence_id;
            }

            if (!$agenceId) {
                return response()->json(['success' => false, 'message' => 'Aucune agence spécifiée ou rattachée.'], 400);
            }

            // Filtres de dates (défaut : mois en cours)
            $dateDebut = $request->filled('date_debut') 
                ? Carbon::parse($request->date_debut)->startOfDay() 
                : Carbon::now()->startOfMonth();
            
            $dateFin = $request->filled('date_fin') 
                ? Carbon::parse($request->date_fin)->endOfDay() 
                : Carbon::now()->endOfMonth();

            // Requête optimisée (Utilise l'index created_at)
            $query = Expedition::query()
                ->where('created_at', '>=', $dateDebut)
                ->where('created_at', '<=', $dateFin)
                ->where(function ($q) use ($agenceId) {
                    $q->where('agence_id', $agenceId)
                      ->orWhereHas('colis', function ($sq) use ($agenceId) {
                          $sq->where('agence_destination_id', $agenceId);
                      });
                });

            // Récupérer les données avec les colis (nécessaire pour la logique ici)
            $expeditions = $query->latest()->get();

            // Calcul des KPIs (Même structure que le Backoffice)
            $summary = [
                'count' => $expeditions->count(),
                'total_backoffice' => 0,
                'total_agence' => 0,
                'total_livreur' => 0,
                'total_client_due' => 0,
            ];

            foreach ($expeditions as $exp) {
                $acc = $exp->accounting_details;
                $summary['total_backoffice'] += $acc['backoffice'];
                $summary['total_agence'] += $acc['agence'];
                $summary['total_livreur'] += $acc['livreur'];
                $summary['total_client_due'] += $acc['total_client_due'];

                // On active la visibilité pour l'API
                $exp->makeVisible(['commission_details', 'accounting_details']);
            }

            return response()->json([
                'success' => true,
                'filters' => [
                    'date_debut' => $dateDebut->toDateTimeString(),
                    'date_fin' => $dateFin->toDateTimeString(),
                    'agence_id' => $agenceId
                ],
                'summary' => $summary,
                'data' => $expeditions
            ]);

        } catch (Exception $e) {
            Log::error('Erreur rapport comptabilité agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }
}
