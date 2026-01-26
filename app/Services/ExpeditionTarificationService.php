<?php

namespace App\Services;

use App\Enums\TypeExpedition;
use App\Models\Expedition;
use App\Models\TarifAgenceGroupage;
use App\Models\TarifAgenceSimple;
use App\Models\TarifGroupage;
use App\Models\Zone;
use App\Services\CommissionService;
use App\Services\TarificationService;
use App\Services\ZoneService;
use Illuminate\Support\Facades\Log;

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

        // Router vers la méthode spécifique selon le type de groupage
        return match ($expedition->type_expedition) {
            TypeExpedition::LD => $this->calculerTarifSimple($expedition, $zoneDepart, $zoneDestination),
            TypeExpedition::GROUPAGE_AFRIQUE => $this->calculerTarifGroupageAfrique($expedition),
            TypeExpedition::GROUPAGE_CA => $this->calculerTarifGroupageCA($expedition),
            TypeExpedition::GROUPAGE_DHD_AERIEN => $this->calculerTarifGroupageDHD($expedition, $zoneDepart, $zoneDestination),
            TypeExpedition::GROUPAGE_DHD_MARITIME => $this->calculerTarifGroupageDHD($expedition, $zoneDepart, $zoneDestination),
            default => [
                'success' => false,
                'message' => 'Type d\'expédition non pris en charge pour la tarification'
            ],
        };
    }

    /**
     * Calcule le tarif pour le mode simple (livraison directe)
     *
     * La logique de calcul suit ces étapes :
     * 1. Calculer le poids total et le volume total de tous les colis
     * 2. Déterminer l'indice de référence (max entre poids et volume/5000)
     * 3. Arrondir l'indice selon les règles métier
     * 4. Rechercher le tarif correspondant à l'indice arrondi
     * 5. Calculer les montants finaux (base, prestation, total)
     * 6. Ajouter les frais d'emballage
     */
    private function calculerTarifSimple(Expedition $expedition, Zone $zoneDepart, Zone $zoneDestination): array
    {
        if (!$zoneDepart || !$zoneDestination) {
            return [
                'success' => false,
                'message' => 'Zone de départ ou de destination invalide'
            ];
        }

        $poidsTotalKg = $expedition->getPoidsTotal();
        $volumeTotalCm3 = $expedition->getVolumeTotal();

        if ($poidsTotalKg <= 0) {
            return [
                'success' => false,
                'message' => "Le poids total de l'expédition doit être supérieur à 0"
            ];
        }

        $volumeDivise = $volumeTotalCm3 > 0 ? ($volumeTotalCm3 / 5000) : 0;
        $indiceReference = max($poidsTotalKg, $volumeDivise);
        $indiceArrondi = $this->tarificationService->arrondirIndice($indiceReference);

        $tarifAgence = TarifAgenceSimple::where('agence_id', $expedition->agence_id)
            ->where('indice', $indiceArrondi)
            ->actif()
            ->first();

        if (!$tarifAgence) {
            return [
                'success' => false,
                'message' => "Aucun tarif trouvé pour l'indice {$indiceArrondi} dans cette agence."
            ];
        }

        $tarif = $tarifAgence->getPrixPourZone($zoneDestination->id);

        if (!$tarif) {
            return [
                'success' => false,
                'message' => "Aucun tarif trouvé pour l'indice {$indiceArrondi} dans la zone de destination.",
            ];
        }

        $montantBase = (float) ($tarif['montant_base'] ?? 0);
        $pourcentagePrestation = (float) ($tarif['pourcentage_prestation'] ?? 0);
        $montantPrestation = (float) ($tarif['montant_prestation'] ?? ($montantBase * $pourcentagePrestation / 100));
        $montantExpedition = (float) ($tarif['montant_expedition'] ?? ($montantBase + $montantPrestation));
        $fraisEmballage = $expedition->getFraisEmballageTotal();

        return [
            'success' => true,
            'tarif' => [
                'montant_base' => round($montantBase, 2, PHP_ROUND_HALF_UP),
                'pourcentage_prestation' => round($pourcentagePrestation, 2, PHP_ROUND_HALF_UP),
                'montant_prestation' => round($montantPrestation, 2, PHP_ROUND_HALF_UP),
                'montant_expedition' => round($montantExpedition, 2, PHP_ROUND_HALF_UP),
                'frais_emballage' => round($fraisEmballage, 2, PHP_ROUND_HALF_UP)
            ],
        ];
    }

    /**
     * Calcule le tarif pour le mode groupage Afrique
     *
     * LOGIQUE SPÉCIFIQUE AU GROUPAGE AFRIQUE :
     * 1. Calculer le poids total de tous les colis
     * 2. Trouver le tarif groupage pour le pays de destination
     * 3. Multiplier : montant = poids_total * prix_unitaire
     */
    private function calculerTarifGroupageAfrique(Expedition $expedition): array
    {
        // ============================================
        // ÉTAPE 1 : Calculer le poids total
        // ============================================
        $poidsTotal = $expedition->getPoidsTotal();
        if ($poidsTotal <= 0) {
            return [
                'success' => false,
                'message' => "Le poids total de l'expédition doit être supérieur à 0"
            ];
        }

        // ============================================
        // ÉTAPE 2 : Récupérer le pays de destination
        // ============================================
        $paysDestination = strtolower(trim($expedition->pays_destination ?? ''));
        $villeDestination = strtolower(trim($expedition->destinataire_ville ?? ''));
        if (empty($paysDestination)) {
            return [
                'success' => false,
                'message' => 'Pays de destination non trouvé'
            ];
        }

        // ============================================
        // ÉTAPE 3 : Rechercher le tarif groupage pour le pays de destination
        // ============================================
        $tarifAgenceGroupage = null;
        if ($expedition->agence_id) {
            $tarifAgenceGroupage = TarifAgenceGroupage::pourAgence($expedition->agence_id)
                ->where('type_expedition', TypeExpedition::GROUPAGE_AFRIQUE)
                ->get()
                ->filter(function ($tag) use ($paysDestination, $villeDestination) {
                    $paysTarif = strtolower(trim($tag->pays . " " . $tag->ville ?? ''));
                    return !empty($paysTarif) &&
                        (str_contains($paysTarif, $paysDestination . " " . $villeDestination) || str_contains($paysDestination . " " . $villeDestination, $paysTarif));
                })
                ->first();
        }

        // ============================================
        // ÉTAPE 4 : Calculer le montant (poids * montant_base du mode)
        // ============================================
        if ($tarifAgenceGroupage) {
            $montantBaseUnitaire = (float) ($tarifAgenceGroupage->montant_base ?? 0);
            $pourcentagePrestation = (float) ($tarifAgenceGroupage->pourcentage_prestation ?? 0);

            // Calculer les montants totaux
            $montantBase = $poidsTotal * $montantBaseUnitaire;
            $montantPrestation = ($montantBase * $pourcentagePrestation) / 100;
            $montantExpedition = $montantBase + $montantPrestation;
            $fraisEmballage = $expedition->getFraisEmballageTotal();
        }
        // ============================================
        // ÉTAPE 6 : Retour du résultat structuré
        // ============================================
        return [
            'success' => true,
            'tarif' => [
                'montant_base' => round($montantBase, 2, PHP_ROUND_HALF_UP),
                'pourcentage_prestation' => round($pourcentagePrestation, 2, PHP_ROUND_HALF_UP),
                'montant_prestation' => round($montantPrestation, 2, PHP_ROUND_HALF_UP),
                'frais_emballage' => round($fraisEmballage, 2, PHP_ROUND_HALF_UP),
                'montant_expedition' => round($montantExpedition, 2, PHP_ROUND_HALF_UP)
            ]
        ];
    }

    /**
     * Calcule le tarif pour le mode groupage CA (Côte d'Ivoire)
     *
     * LOGIQUE SPÉCIFIQUE AU GROUPAGE CA :
     * - Utilise les tarifs groupage basés sur les catégories de produits
     * - Un seul tarif groupage_ca par backoffice/agence
     * - Recherche les tarifs du mode "colis"
     */
    private function calculerTarifGroupageCA(Expedition $expedition): array
    {
        // ============================================
        // ÉTAPE 1 : Calculer le poids total
        // ============================================
        $poidsTotal = $expedition->getPoidsTotal();
        if ($poidsTotal <= 0) {
            return [
                'success' => false,
                'message' => "Le poids total de l'expédition doit être supérieur à 0"
            ];
        }

        // ============================================
        // ÉTAPE 2 : Rechercher le tarif groupage CA (sans filtre par pays)
        // ============================================
        $tarifAgenceGroupage = null;
        if ($expedition->agence_id) {
            $tarifAgenceGroupage = TarifAgenceGroupage::pourAgence($expedition->agence_id)
                ->where('type_expedition', TypeExpedition::GROUPAGE_CA)
                ->first();
        }

        // ============================================
        // ÉTAPE 4 : Calculer le montant (poids * montant_base du mode)
        // ============================================
        if ($tarifAgenceGroupage) {
            $montantBaseUnitaire = (float) ($tarifAgenceGroupage->montant_base ?? 0);
            $pourcentagePrestation = (float) ($tarifAgenceGroupage->pourcentage_prestation ?? 0);

            // Calculer les montants totaux
            $montantBase = $poidsTotal * $montantBaseUnitaire;
            $montantPrestation = ($montantBase * $pourcentagePrestation) / 100;
            $montantExpedition = $montantBase + $montantPrestation;
            $fraisEmballage = $expedition->getFraisEmballageTotal();
        }
        // ============================================
        // ÉTAPE 5 : Retour du résultat structuré
        // ============================================
        return [
            'success' => true,
            'tarif' => [
                'montant_base' => round($montantBase, 2, PHP_ROUND_HALF_UP),
                'pourcentage_prestation' => round($pourcentagePrestation, 2, PHP_ROUND_HALF_UP),
                'montant_prestation' => round($montantPrestation, 2, PHP_ROUND_HALF_UP),
                'frais_emballage' => round($fraisEmballage, 2, PHP_ROUND_HALF_UP),
                'montant_expedition' => round($montantExpedition, 2, PHP_ROUND_HALF_UP)
            ]
        ];
    }

    /**
     * Calcule le tarif pour le mode groupage DHD
     *
     * LOGIQUE SPÉCIFIQUE AU GROUPAGE DHD :
     * - Utilise les tarifs groupage basés sur les catégories de produits
     * - Calcule par catégorie puis additionne
     */
    private function calculerTarifGroupageDHD(Expedition $expedition, Zone $zoneDepart, Zone $zoneDestination): array
    {
        // Pour l'instant, même logique que le groupage générique
        // Peut être personnalisée selon les besoins spécifiques du groupage DHD
        return [];
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
                85.0  // Default 85%
            );

            $commissions['agence_enlevement'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_enlevement_domicile,
                'commission_agence_enlevement',
                15.0  // Default 15%
            );
        }

        // Commission Livreur Livraison (si applicable)
        if ($expedition->frais_livraison_domicile > 0) {
            $commissions['livreur_livraison'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_livraison_domicile,
                'commission_livreur_livraison',
                90.0  // Default 90%
            );

            $commissions['agence_livraison'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_livraison_domicile,
                'commission_agence_livraison',
                10.0  // Default 10%
            );
        }

        // Commission Retard (si applicable)
        if ($expedition->frais_retard_retrait > 0) {
            $commissions['agence_retard'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_retard_retrait,
                'commission_agence_retard',
                40.0  // Default 40%
            );

            $commissions['tourshop_retard'] = $this->commissionService->calculateCommission(
                (float) $expedition->frais_retard_retrait,
                'commission_tourshop_retard',
                60.0  // Default 60%
            );
        }

        return $commissions;
    }

    /**
     * Simule le tarif d'une expédition à partir de données brutes
     */
    public function simulerTarifExpedition(array $donneesExpedition, array $articles): array
    {
        return $this->tarificationService->simulerTarification(array_merge($donneesExpedition, [
            'poids' => collect($articles)->sum(fn($a) => ($a['poids'] ?? 0) * ($a['quantite'] ?? 1)),
            'category_id' => $articles[0]['category_id'] ?? null
        ]));
    }
}
