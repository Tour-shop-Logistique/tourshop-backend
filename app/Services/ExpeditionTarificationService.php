<?php

namespace App\Services;

use App\Models\Expedition;
use App\Models\Zone;
use App\Models\TarifAgenceSimple;
use App\Services\TarificationService;
use App\Enums\TypeExpedition;
use App\Services\CommissionService;
use App\Services\ZoneService;

class ExpeditionTarificationService
{
    protected TarificationService $tarificationService;
    protected CommissionService $commissionService;
    protected ZoneService $zoneService;

    public function __construct(
        TarificationService $tarificationService,
        CommissionService $commissionService,
        ZoneService $zoneService
    ) {
        $this->tarificationService = $tarificationService;
        $this->commissionService = $commissionService;
        $this->zoneService = $zoneService;
    }

    /**
     * Calcule le tarif d'une expédition en fonction de ses colis
     */
    public function calculerTarifExpedition(Expedition $expedition): array
    {
        if ($expedition->colis()->count() === 0) {
            return [
                'success' => false,
                'message' => 'Aucun colis dans cette expédition'
            ];
        }

        // Récupérer les zones pour obtenir les pays (avec cache)
        $zoneDepart = $this->zoneService->getZoneById($expedition->zone_depart_id);
        $zoneDestination = $this->zoneService->getZoneById($expedition->zone_destination_id);

        if ($expedition->type_expedition === TypeExpedition::LD->value) {
            if (!$zoneDepart || !$zoneDestination) {
                return [
                    'success' => false,
                    'message' => 'Zone(s) invalide(s)'
                ];
            }
            return $this->calculerTarifSimple($expedition, $zoneDepart, $zoneDestination);
        } else {
            return $this->calculerTarifGroupage($expedition, $zoneDepart, $zoneDestination);
        }
    }

    /**
     * Calcule le tarif pour le mode simple (basé sur le volume total)
     */
    private function calculerTarifSimple(Expedition $expedition, Zone $zoneDepart, Zone $zoneDestination): array
    {
        $poids_total_kg = $expedition->getPoidsTotal(); // poids total en kg
        $volume_total_cm3 = $expedition->getVolumeTotal(); // volume total en cm3

        $indiceReference = (float) max($poids_total_kg ?? 0, ($volume_total_cm3 ?? 0) / 5000);
        $indiceArrondi = $this->tarificationService->arrondirIndice($indiceReference);

        // Chercher le tarif agence correspondant
        $tarifAgence = TarifAgenceSimple::where('agence_id', $expedition->agence_id)
            ->where('indice', $indiceArrondi)
            ->where('actif', true)
            ->first();

        if (!$tarifAgence) {
            return [
                'success' => false,
                'message' => "Aucun tarif trouvé pour l'indice {$indiceArrondi} dans cette agence."
            ];
        }

        // Récupérer le prix pour la zone de destination
        $prixZone = $tarifAgence->getPrixPourZone($zoneDestination->id);

        if (!$prixZone) {
            return [
                'success' => false,
                'message' => "Aucun tarif trouvé pour la zone de destination dans cette agence."
            ];
        }

        return [
            'success' => true,
            'tarif' => [
                // 'indice_reference' => $indiceArrondi,
                // 'zone_depart_id' => $zoneDepart->id,
                // 'pays_depart' => $expedition->pays_depart ?? '',
                // 'zone_destination_id' => $zoneDestination->id,
                // 'pays_arrivee' => $expedition->pays_destination ?? '',
                'montant_base' => $prixZone['montant_base'],
                'pourcentage_prestation' => $prixZone['pourcentage_prestation'],
                'montant_prestation' => $prixZone['montant_prestation'],
                'montant_expedition' => $prixZone['montant_expedition']
            ]
        ];
    }

    /**
     * Calcule le tarif pour le mode groupage (basé sur les catégories de produits)
     */
    private function calculerTarifGroupage(Expedition $expedition, Zone $zoneDepart, Zone $zoneDestination): array
    {
        $tarifsParArticle = [];
        $montantTotal = 0;

        // Charger les colis avec leurs relations
        $colisList = $expedition->colis()->with(['category', 'produit'])->get();

        // Grouper les colis par catégorie de produit
        $colisParCategorie = $colisList
            ->whereNotNull('category_id')
            ->groupBy('category_id');

        foreach ($colisParCategorie as $categoryId => $colis) {
            $poidsTotalCategorie = $colis->sum('poids');

            $donnees = [
                'pays_depart' => $zoneDepart->pays[0] ?? '',
                'pays_arrivee' => $zoneDestination->pays[0] ?? '',
                'poids_kg' => $poidsTotalCategorie,
                'category_id' => $categoryId,
            ];

            $resultatTarif = $this->tarificationService->simulerTarification($donnees);

            if ($resultatTarif['success']) {
                $tarifsParArticle[] = [
                    'category_id' => $categoryId,
                    'category_nom' => $colis->first()->category->nom ?? 'Catégorie inconnue',
                    'poids_total_kg' => $poidsTotalCategorie,
                    'nombre_articles' => $colis->count(),
                    'tarif' => $resultatTarif['tarif']
                ];
                $montantTotal += $resultatTarif['simulation']['prix_total'];
            }
        }

        // Traiter les colis sans catégorie
        $colisSansCategorie = $colisList->whereNull('category_id');
        if ($colisSansCategorie->isNotEmpty()) {
            $poidsSansCategorie = $colisSansCategorie->sum('poids');

            $donnees = [
                'pays_depart' => $zoneDepart->pays[0] ?? '',
                'pays_arrivee' => $zoneDestination->pays[0] ?? '',
                'poids_kg' => $poidsSansCategorie,
                'category_id' => null,
            ];

            $resultatTarif = $this->tarificationService->simulerTarification($donnees);

            if ($resultatTarif['success']) {
                $tarifsParArticle[] = [
                    'category_id' => null,
                    'category_nom' => 'Sans catégorie',
                    'poids_total_kg' => $poidsSansCategorie,
                    'nombre_articles' => $colisSansCategorie->count(),
                    'tarif' => $resultatTarif['tarif']
                ];
                $montantTotal += $resultatTarif['simulation']['prix_total'];
            }
        }

        return [
            'success' => true,
            'simulation' => [
                'pays_depart' => $zoneDepart->pays[0] ?? '',
                'pays_arrivee' => $zoneDestination->pays[0] ?? '',
                'devise' => 'FCFA',
                'type_expedition' => $expedition->type_expedition,
                'poids_total_kg' => $expedition->getPoidsTotal(),
                'nombre_total_articles' => $colisList->count(),
                'details_par_categorie' => $tarifsParArticle
            ],
            'tarif' => [
                'montant_base' => $montantTotal,
                'pourcentage_prestation' => 0,
                'montant_prestation' => 0,
                'montant_expedition' => round($montantTotal, 2, PHP_ROUND_HALF_UP)
            ]
        ];
    }

    /**
     * Calcule les commissions pour une expédition
     */
    public function calculerCommissions(Expedition $expedition): array
    {
        $commissions = [];

        // Commission Livreur Enlèvement (si applicable)
        if ($expedition->frais_enlevement_domicile > 0) {
            $commissions['livreur_enlevement'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_enlevement_domicile,
                'commission_livreur_enlevement',
                85.0 // Default 85%
            );

            $commissions['agence_enlevement'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_enlevement_domicile,
                'commission_agence_enlevement',
                15.0 // Default 15%
            );
        }

        // Commission Livreur Livraison (si applicable)
        if ($expedition->frais_livraison_domicile > 0) {
            $commissions['livreur_livraison'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_livraison_domicile,
                'commission_livreur_livraison',
                90.0 // Default 90%
            );

            $commissions['agence_livraison'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_livraison_domicile,
                'commission_agence_livraison',
                10.0 // Default 10%
            );
        }

        // Commission Retard (si applicable)
        if ($expedition->frais_retard_retrait > 0) {
            $commissions['agence_retard'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_retard_retrait,
                'commission_agence_retard',
                40.0 // Default 40%
            );

            $commissions['tourshop_retard'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_retard_retrait,
                'commission_tourshop_retard',
                60.0 // Default 60%
            );
        }

        return $commissions;
    }
}
