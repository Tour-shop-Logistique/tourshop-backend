<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Expedition;
use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class BackofficeAccountingController extends Controller
{
    /**
     * Rapport comptable pour le backoffice
     */
    public function report(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::BACKOFFICE && $user->type !== UserType::ADMIN) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if (!$user->backoffice && $user->type !== UserType::ADMIN) {
                return response()->json(['success' => false, 'message' => 'Aucun backoffice rattaché.'], 403);
            }

            $country = $user->backoffice ? $user->backoffice->pays : null;

            // Filtres de dates (défaut : mois en cours)
            $dateDebut = $request->filled('date_debut')
                ? Carbon::parse($request->date_debut)->startOfDay()
                : Carbon::now()->startOfMonth();

            $dateFin = $request->filled('date_fin')
                ? Carbon::parse($request->date_fin)->endOfDay()
                : Carbon::now()->endOfMonth();

            // Requête de base : expéditions liées au pays du backoffice
            $query = Expedition::query()
                ->where('created_at', '>=', $dateDebut)
                ->where('created_at', '<=', $dateFin);

            if ($country) {
                $mode = $request->query('mode'); // 'depart' ou 'reception'
                
                if ($mode === 'depart') {
                    $query->where('pays_depart', $country);
                } elseif ($mode === 'reception') {
                    $query->where('pays_destination', $country);
                } else {
                    $query->where(function ($q) use ($country) {
                        $q->where('pays_depart', $country)
                            ->orWhere('pays_destination', $country);
                    });
                }
            }

            // Récupérer les données avec l'agence mais sans les colis (trop volumineux)
            $expeditions = $query->latest()->get();

            // Calcul des KPIs
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

                // On active la visibilité des détails pour l'API
                $exp->makeVisible(['commission_details', 'accounting_details']);
            }

            return response()->json([
                'success' => true,
                'filters' => [
                    'date_debut' => $dateDebut->toDateTimeString(),
                    'date_fin' => $dateFin->toDateTimeString(),
                    'pays' => $country
                ],
                'summary' => $summary,
                'data' => $expeditions
            ]);

        } catch (Exception $e) {
            Log::error('Erreur rapport comptabilité backoffice : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }
}
