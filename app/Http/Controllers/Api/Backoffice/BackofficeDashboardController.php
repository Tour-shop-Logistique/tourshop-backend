<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Enums\ExpeditionStatus;
use App\Enums\StatutPaiement;
use App\Enums\TypeExpedition;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Colis;
use App\Models\Expedition;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackofficeDashboardController extends Controller
{
    /**
     * Tableau de bord backoffice logistique : KPIs opérationnels, santé financière,
     * flux logistique et alertes.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $country = $user->type === UserType::BACKOFFICE && $user->backoffice
                ? $user->backoffice->pays
                : null;

            $today = Carbon::today();
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $delayedControlDays = (int) $request->get('delayed_control_days', 3);

            // ─── 1. Indicateurs de performance opérationnelle ─────────────────
            $operational = $this->getOperationalKpis($country, $today);

            // ─── 2. Santé financière ─────────────────────────────────────────
            $financial = $this->getFinancialKpis($country, $startOfMonth, $endOfMonth);

            // ─── 3. Flux logistique & destinations ───────────────────────────
            $logistics = $this->getLogisticsFlow($country);

            return response()->json([
                'success' => true,
                'data' => [
                    'operational' => $operational,
                    'financial' => $financial,
                    'logistics' => $logistics,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Backoffice dashboard error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du tableau de bord.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * KPIs opérationnels : colis à contrôler, arrivages prévus, réceptions du jour, fluidité du transit.
     */
    private function getOperationalKpis(?string $country, Carbon $today): array
    {
        $baseColisDepart = Colis::query()->whereHas('expedition', function ($q) use ($country) {
            $q->whereNotIn('statut_expedition', [ExpeditionStatus::REFUSED, ExpeditionStatus::CANCELLED]);
            if ($country !== null) {
                $q->where('pays_depart', $country);
            }
        });

        $baseExpeditionDestination = Expedition::query()
            ->whereNotIn('statut_expedition', [ExpeditionStatus::REFUSED, ExpeditionStatus::CANCELLED]);
        if ($country !== null) {
            $baseExpeditionDestination->where('pays_destination', $country);
        }

        // Colis à contrôler : non contrôlés, expédition en pays de départ du backoffice
        $colisAControler = (clone $baseColisDepart)->where('is_controlled', false)->count();

        // Arrivages prévus : expéditions en route vers l'entrepôt (départ réussi, pas encore arrivées)
        $arrivagesPrevus = (clone $baseExpeditionDestination)
            ->where('statut_expedition', ExpeditionStatus::DEPART_EXPEDITION_SUCCES)
            ->count();

        // Réceptions du jour : colis marqués reçus par le backoffice aujourd'hui (pays destination)
        $receptionsDuJourQuery = Colis::query()
            ->where('is_received_by_backoffice', true)
            ->whereDate('received_at_backoffice', $today);
        if ($country !== null) {
            $receptionsDuJourQuery->whereHas('expedition', fn ($q) => $q->where('pays_destination', $country));
        }
        $receptionsDuJour = $receptionsDuJourQuery->count();

        // Colis expédiés du jour par le backoffice : colis dont l'expédition a été mise en transit aujourd'hui (statut DEPART_EXPEDITION_SUCCES + date_expedition_depart)
        $colisExpediesDuJourQuery = Colis::query()->whereHas('expedition', function ($q) use ($country, $today) {
            $q->where('statut_expedition', ExpeditionStatus::DEPART_EXPEDITION_SUCCES)
                ->whereDate('date_expedition_depart', $today);
            if ($country !== null) {
                $q->where('pays_depart', $country);
            }
        });
        $colisExpediesDuJour = $colisExpediesDuJourQuery->count();

        return [
            'colis_a_controler' => $colisAControler,
            'arrivages_prevus' => $arrivagesPrevus,
            'receptions_du_jour' => $receptionsDuJour,
            'colis_expedies_du_jour' => $colisExpediesDuJour,
        ];
    }

    /**
     * Santé financière : CA du mois, répartition payé/impayé, frais annexes, encours à recouvrer.
     */
    private function getFinancialKpis(?string $country, Carbon $startOfMonth, Carbon $endOfMonth): array
    {
        $base = Expedition::query()
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->whereNotIn('statut_expedition', [ExpeditionStatus::REFUSED, ExpeditionStatus::CANCELLED]);

        if ($country !== null) {
            $base->where('pays_depart', $country);
        }

        $chiffreAffairesMois = (clone $base)->sum('montant_expedition');

        $paye = (clone $base)->where('statut_paiement', StatutPaiement::PAYE)->sum('montant_expedition');
        $impaye = (clone $base)->where('statut_paiement', '!=', StatutPaiement::PAYE)->sum('montant_expedition');

        // Encours à recouvrer : impayés déjà en transit (DEPART ou ARRIVEE)
        $encoursQuery = Expedition::query()
            ->where('statut_paiement', '!=', StatutPaiement::PAYE)
            ->whereIn('statut_expedition', [
                ExpeditionStatus::EN_TRANSIT_ENTREPOT,
                ExpeditionStatus::DEPART_EXPEDITION_SUCCES,
                ExpeditionStatus::ARRIVEE_EXPEDITION_SUCCES,
                ExpeditionStatus::RECU_AGENCE_DESTINATION,
                ExpeditionStatus::EN_COURS_LIVRAISON,
            ]);
        if ($country !== null) {
            $encoursQuery->where('pays_depart', $country);
        }
        $encoursARecouvrer = $encoursQuery->sum('montant_expedition');

        return [
            'chiffre_affaires_mois' => (float) $chiffreAffairesMois,
            'statut_paiements' => [
                'paye' => (float) $paye,
                'impaye' => (float) $impaye,
            ],
            'encours_a_recouvrer' => (float) $encoursARecouvrer,
        ];
    }

    /**
     * Flux logistique : top destinations, volume par type (Maritime / Aérien), activité des agences.
     */
    private function getLogisticsFlow(?string $country): array
    {
        $base = Expedition::query()
            ->whereNotIn('statut_expedition', [ExpeditionStatus::REFUSED, ExpeditionStatus::CANCELLED]);
        if ($country !== null) {
            $base->where('pays_depart', $country);
        }

        // Top destinations (pays)
        $topDestinations = (clone $base)
            ->select('pays_destination', DB::raw('count(*) as total'))
            ->groupBy('pays_destination')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['pays' => $r->pays_destination, 'total' => (int) $r->total]);

        // Volume par type : Maritime vs Aérien (groupage_dhd_maritime / groupage_dhd_aerien)
        $typesAerien = [TypeExpedition::GROUPAGE_DHD_AERIEN->value];
        $typesMaritime = [TypeExpedition::GROUPAGE_DHD_MARITIME->value];
        $volumeAerien = (clone $base)->whereIn('type_expedition', $typesAerien)->count();
        $volumeMaritime = (clone $base)->whereIn('type_expedition', $typesMaritime)->count();
        $volumeParType = [
            ['type' => 'Aérien', 'total' => $volumeAerien],
            ['type' => 'Maritime', 'total' => $volumeMaritime],
        ];

        // Activité des agences de destination (qui reçoivent le plus de colis)
        $agencesQuery = Colis::query()
            ->whereNotNull('agence_destination_id')
            ->whereHas('expedition', function ($q) use ($country) {
                $q->whereNotIn('statut_expedition', [ExpeditionStatus::REFUSED, ExpeditionStatus::CANCELLED]);
                if ($country !== null) {
                    $q->where('pays_destination', $country);
                }
            });

        $activiteAgences = $agencesQuery
            ->select('agence_destination_id', DB::raw('count(*) as total'))
            ->groupBy('agence_destination_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('agenceDestination:id,nom_agence,code_agence,ville,pays')
            ->get()
            ->map(function ($r) {
                return [
                    'agence_id' => $r->agence_destination_id,
                    'nom_agence' => $r->agenceDestination?->nom_agence ?? 'N/A',
                    'code_agence' => $r->agenceDestination?->code_agence ?? null,
                    'ville' => $r->agenceDestination?->ville ?? null,
                    'total' => (int) $r->total,
                ];
            });

        return [
            'top_destinations' => $topDestinations,
            'volume_par_type' => $volumeParType,
            'activite_agences' => $activiteAgences,
        ];
    }

}
