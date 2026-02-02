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
    protected CommissionService $commissionService;
    protected ZoneService $zoneService;

    public function __construct(
        CommissionService $commissionService,
        ZoneService $zoneService
    ) {
        $this->commissionService = $commissionService;
        $this->zoneService = $zoneService;
    }

    /**
     * Calcule le volume en cm³ puis divise par le facteur (défaut 5000)
     */
    public function calculerVolumeDivise(float $longueur, float $largeur, float $hauteur, int $facteurDivision = 5000): float
    {
        $volume = $longueur * $largeur * $hauteur;
        return $volume / $facteurDivision;
    }

    /**
     * Détermine l'indice de référence (max entre poids et volume divisé)
     */
    public function determinerIndiceReference(float $poids, float $longueur = 0, float $largeur = 0, float $hauteur = 0, int $facteurDivision = 5000): float
    {
        $volumeDivise = 0;
        if ($longueur > 0 && $largeur > 0 && $hauteur > 0) {
            $volumeDivise = $this->calculerVolumeDivise($longueur, $largeur, $hauteur, $facteurDivision);
        }

        return max($poids, $volumeDivise);
    }

    /**
     * Arrondit l'indice (ex: 1.1 -> 1.5, 1.6 -> 2.0, etc.)
     */
    public function arrondirIndice(float $indice): float
    {
        $partieEntiere = floor($indice);
        $partieDecimale = $indice - $partieEntiere;

        if ($partieDecimale == 0) {
            return $partieEntiere;
        } elseif ($partieDecimale <= 0.5) {
            return $partieEntiere + 0.5;
        } else {
            return $partieEntiere + 1.0;
        }
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

        $expedition->loadMissing('colis');

        // Récupérer les zones pour obtenir les pays (avec cache)
        $zoneDepart = $this->zoneService->getZoneById($expedition->zone_depart_id);
        $zoneDestination = $this->zoneService->getZoneById($expedition->zone_destination_id);

        // Router vers la méthode spécifique selon le type de groupage
        return match ($expedition->type_expedition) {
            TypeExpedition::LD => $this->calculerTarifSimple($expedition, $zoneDepart, $zoneDestination),
            TypeExpedition::GROUPAGE_AFRIQUE => $this->calculerTarifGroupageAfrique($expedition),
            TypeExpedition::GROUPAGE_CA => $this->calculerTarifGroupageCA($expedition),
            TypeExpedition::GROUPAGE_DHD_AERIEN => $this->calculerTarifGroupageDHD($expedition),
            TypeExpedition::GROUPAGE_DHD_MARITIME => $this->calculerTarifGroupageDHD($expedition),
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
        $indiceArrondi = $this->arrondirIndice($indiceReference);

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

    private function calculerTarifGroupageAfrique(Expedition $expedition): array
    {
        $poidsTotal = $expedition->getPoidsTotal();
        if ($poidsTotal <= 0) {
            return [
                'success' => false,
                'message' => "Le poids total de l'expédition doit être supérieur à 0"
            ];
        }

        $paysDestination = strtolower(trim($expedition->pays_destination ?? ''));
        $villeDestination = strtolower(trim($expedition->destinataire_ville ?? ''));
        if (empty($paysDestination)) {
            return [
                'success' => false,
                'message' => 'Pays de destination non trouvé'
            ];
        }

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

        if ($tarifAgenceGroupage) {
            $montantBaseUnitaire = (float) ($tarifAgenceGroupage->montant_base ?? 0);
            $pourcentagePrestation = (float) ($tarifAgenceGroupage->pourcentage_prestation ?? 0);

            // Calculer les montants totaux
            $montantBase = $poidsTotal * $montantBaseUnitaire;
            $montantPrestation = ($montantBase * $pourcentagePrestation) / 100;
            $montantExpedition = $montantBase + $montantPrestation;
            $fraisEmballage = $expedition->getFraisEmballageTotal();
        }

        $colisList = $expedition->colis;
        foreach ($colisList as $colis) {
            $montantColisBase = $colis->poids * $montantBaseUnitaire;
            $montantColisPrestation = ($montantColisBase * $pourcentagePrestation) / 100;
            $montantColisTotal = $montantColisBase + $montantColisPrestation;
            $colis->update([
                'prix_unitaire' => $montantBaseUnitaire,
                'montant_colis_base' => $montantColisBase,
                'pourcentage_prestation' => $pourcentagePrestation,
                'montant_colis_prestation' => $montantColisPrestation,
                'montant_colis_total' => $montantColisTotal
            ]);
        }

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

    private function calculerTarifGroupageCA(Expedition $expedition): array
    {
        $poidsTotal = $expedition->getPoidsTotal();
        if ($poidsTotal <= 0) {
            return [
                'success' => false,
                'message' => "Le poids total de l'expédition doit être supérieur à 0"
            ];
        }

        $tarifAgenceGroupage = null;
        if ($expedition->agence_id) {
            $tarifAgenceGroupage = TarifAgenceGroupage::pourAgence($expedition->agence_id)
                ->where('type_expedition', TypeExpedition::GROUPAGE_CA)
                ->first();
        }

        if ($tarifAgenceGroupage) {
            $montantBaseUnitaire = (float) ($tarifAgenceGroupage->montant_base ?? 0);
            $pourcentagePrestation = (float) ($tarifAgenceGroupage->pourcentage_prestation ?? 0);

            // Calculer les montants totaux
            $montantBase = $poidsTotal * $montantBaseUnitaire;
            $montantPrestation = ($montantBase * $pourcentagePrestation) / 100;
            $montantExpedition = $montantBase + $montantPrestation;
            $fraisEmballage = $expedition->getFraisEmballageTotal();
        }

        $colisList = $expedition->colis;
        foreach ($colisList as $colis) {
            $montantColisBase = $colis->poids * $montantBaseUnitaire;
            $montantColisPrestation = ($montantColisBase * $pourcentagePrestation) / 100;
            $montantColisTotal = $montantColisBase + $montantColisPrestation;
            $colis->update([
                'prix_unitaire' => $montantBaseUnitaire,
                'montant_colis_base' => $montantColisBase,
                'pourcentage_prestation' => $pourcentagePrestation,
                'montant_colis_prestation' => $montantColisPrestation,
                'montant_colis_total' => $montantColisTotal
            ]);
        }

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

    private function calculerTarifGroupageDHD(Expedition $expedition): array
    {
        $expediteur = $expedition->expediteur;
        $destinataire = $expedition->destinataire;

        $villeDepart = strtolower(trim($expediteur['ville'] ?? ''));
        $villeDestination = strtolower(trim($destinataire['ville'] ?? ''));

        if (empty($villeDepart) || empty($villeDestination)) {
            return [
                'success' => false,
                'message' => 'Villes de départ ou de destination manquantes pour former la ligne.'
            ];
        }

        $ligneRoute = $villeDepart . '-' . $villeDestination;
        $montantBaseTotal = 0;
        $montantPrestationTotal = 0;
        $fraisEmballageTotal = 0;

        $colisList = $expedition->colis;

        if ($colisList->isEmpty()) {
            return [
                'success' => false,
                'message' => 'L\'expédition ne contient aucun colis.'
            ];
        }

        foreach ($colisList as $colis) {
            if (!$colis->category_id) {
                return [
                    'success' => false,
                    'message' => "Le colis {$colis->code_colis} n'a pas de catégorie associée."
                ];
            }

            $tarif = TarifAgenceGroupage::pourAgence($expedition->agence_id)
                ->where('type_expedition', $expedition->type_expedition)
                ->where('category_id', $colis->category_id)
                ->where('ligne', $ligneRoute)
                ->actif()
                ->first();

            if (!$tarif) {
                return [
                    'success' => false,
                    'message' => "Aucun tarif trouvé pour la catégorie du colis {$colis->code_colis} sur la ligne {$ligneRoute}."
                ];
            }

            $prixKg = (float) $tarif->montant_base;
            $pourcentagePrestation = (float) $tarif->pourcentage_prestation;

            $poidsColis = (float) $colis->poids;
            $montantColisBase = $prixKg * $poidsColis;
            $montantColisPrestation = ($montantColisBase * $pourcentagePrestation) / 100;

            $montantBaseTotal += $montantColisBase;
            $montantPrestationTotal += $montantColisPrestation;
            $fraisEmballageTotal += (float) $colis->prix_emballage;

            // Mettre à jour le colis avec son prix calculé
            $colis->update([
                'prix_unitaire' => $prixKg,
                'montant_colis_base' => $montantColisBase,
                'pourcentage_prestation' => $pourcentagePrestation,
                'montant_colis_prestation' => $montantColisPrestation,
                'montant_colis_total' => $montantColisBase + $montantColisPrestation
            ]);
        }

        $montantExpeditionTotal = $montantBaseTotal + $montantPrestationTotal;

        return [
            'success' => true,
            'tarif' => [
                'montant_base' => round($montantBaseTotal, 2, PHP_ROUND_HALF_UP),
                'pourcentage_prestation' => 0, // Individuel par colis, on met 0 au global ou une moyenne ? On laisse 0 car déjà calculé dans montant_prestation
                'montant_prestation' => round($montantPrestationTotal, 2, PHP_ROUND_HALF_UP),
                'frais_emballage' => round($fraisEmballageTotal, 2, PHP_ROUND_HALF_UP),
                'montant_expedition' => round($montantExpeditionTotal, 2, PHP_ROUND_HALF_UP)
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

}
