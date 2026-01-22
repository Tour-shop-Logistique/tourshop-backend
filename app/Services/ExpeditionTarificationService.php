<?php

namespace App\Services;

use App\Enums\TypeExpedition;
use App\Models\Expedition;
use App\Models\TarifAgenceGroupage;
use App\Models\TarifAgenceSimple;
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
            TypeExpedition::GROUPAGE_DHD => $this->calculerTarifGroupageDHD($expedition, $zoneDepart, $zoneDestination),
            default => [],
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
        $tarifGroupage = null;
        $prixMode = null;

        // Étape 3.1 : Chercher d'abord un tarif d'agence (si agence_id existe)
        if ($expedition->agence_id) {
            $tarifAgenceGroupage = TarifAgenceGroupage::pourAgence($expedition->agence_id)
                ->where('type_expedition', TypeExpedition::GROUPAGE_AFRIQUE)
                ->with('tarifGroupage')
                ->actif()
                ->get()
                ->filter(function ($tag) use ($paysDestination) {
                    // Utiliser directement le champ pays du tarif d'agence
                    $paysTarif = strtolower(trim($tag->pays ?? ''));
                    return !empty($paysTarif) &&
                        (str_contains($paysTarif, $paysDestination) || str_contains($paysDestination, $paysTarif));
                })
                ->first();
        }

        // Étape 3.2 : Si un tarif d'agence existe, utiliser son tarif groupage associé
        if ($tarifAgenceGroupage && $tarifAgenceGroupage->tarifGroupage) {
            $tarifGroupage = $tarifAgenceGroupage->tarifGroupage;
            // Priorité : utiliser le prix_modes de l'agence s'il existe
            $prixMode = $tarifAgenceGroupage->getPrixPourMode('afrique');
            // Si pas de prix_modes personnalisé pour l'agence, utiliser celui du tarif de base
            if (!$prixMode) {
                $prixMode = $tarifGroupage->getPrixPourMode('afrique');
            }
        } else {
            // Pas de tarif d'agence trouvé : retourner une erreur
            return [
                'success' => false,
                'message' => "Aucun tarif d'agence groupage trouvé pour le pays de destination : {$paysDestination}",
            ];
        }

        // ============================================
        // ÉTAPE 4 : Vérifier qu'un tarif a été trouvé
        // ============================================
        if (!$prixMode) {
            return [
                'success' => true,
                'message' => "Aucun prix trouvé pour le mode 'afrique' dans le tarif groupage",
            ];
        }

        // ============================================
        // ÉTAPE 5 : Calculer le montant (poids * montant_base du mode)
        // ============================================
        $montantBaseUnitaire = (float) ($prixMode['montant_base'] ?? 0);
        $pourcentagePrestation = (float) ($prixMode['pourcentage_prestation'] ?? 0);

        // Calculer les montants totaux
        $montantBase = $poidsTotal * $montantBaseUnitaire;
        $montantPrestation = ($montantBase * $pourcentagePrestation) / 100;
        $montantExpedition = $montantBase + $montantPrestation;
        $fraisEmballage = $expedition->getFraisEmballageTotal();

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
        $tarifGroupage = null;
        $prixMode = null;

        // Étape 2.1 : Chercher d'abord un tarif d'agence (si agence_id existe)
        if ($expedition->agence_id) {
            $tarifAgenceGroupage = TarifAgenceGroupage::pourAgence($expedition->agence_id)
                ->where('type_expedition', TypeExpedition::GROUPAGE_CA)
                ->with('tarifGroupage')
                ->actif()
                ->first();
        }

        // Étape 2.2 : Si un tarif d'agence existe, utiliser son tarif groupage associé
        if ($tarifAgenceGroupage && $tarifAgenceGroupage->tarifGroupage) {
            $tarifGroupage = $tarifAgenceGroupage->tarifGroupage;
            // Priorité : utiliser le prix_modes de l'agence s'il existe pour le mode "colis"
            $prixMode = $tarifAgenceGroupage->getPrixPourMode('colis');
            // Si pas de prix_modes personnalisé pour l'agence, utiliser celui du tarif de base
            if (!$prixMode) {
                $prixMode = $tarifGroupage->getPrixPourMode('colis');
            }
        } else {
            // Pas de tarif d'agence trouvé : retourner une erreur
            return [
                'success' => false,
                'message' => "Aucun tarif d'agence groupage CA trouvé",
            ];
        }

        // ============================================
        // ÉTAPE 3 : Vérifier qu'un tarif a été trouvé
        // ============================================
        if (!$prixMode) {
            return [
                'success' => false,
                'message' => "Aucun prix trouvé pour le mode 'colis' dans le tarif groupage CA",
            ];
        }

        // ============================================
        // ÉTAPE 4 : Calculer le montant (poids * montant_base du mode)
        // ============================================
        $montantBaseUnitaire = (float) ($prixMode['montant_base'] ?? 0);
        $pourcentagePrestation = (float) ($prixMode['pourcentage_prestation'] ?? 0);

        // Calculer les montants totaux
        $montantBase = $poidsTotal * $montantBaseUnitaire;
        $montantPrestation = ($montantBase * $pourcentagePrestation) / 100;
        $montantExpedition = $montantBase + $montantPrestation;
        $fraisEmballage = $expedition->getFraisEmballageTotal();

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
        try {
            // Récupérer les zones (pays fournis dans $donneesExpedition)
            $zoneDepart = $this->zoneService->getZoneByCountry($donneesExpedition['pays_depart'] ?? '');
            $zoneDestination = $this->zoneService->getZoneByCountry($donneesExpedition['pays_arrivee'] ?? ($donneesExpedition['pays_destination'] ?? ''));

            if (!$zoneDepart || !$zoneDestination) {
                return [
                    'success' => false,
                    'message' => 'Zone de départ ou de destination non identifiée'
                ];
            }

            // Calculer les totaux à partir des articles simulés
            $poidsTotal = 0;
            $volumeTotal = 0;
            foreach ($articles as $article) {
                $poids = (float) ($article['poids'] ?? 0) * (int) ($article['quantite'] ?? 1);
                $poidsTotal += $poids;

                if (isset($article['longueur'], $article['largeur'], $article['hauteur'])) {
                    $volumeTotal += (float) $article['longueur'] * (float) $article['largeur'] * (float) $article['hauteur'] * (int) ($article['quantite'] ?? 1);
                }
            }

            // Déterminer le mode (simple/LD ou groupage)
            $modeExpedition = $donneesExpedition['type_expedition'] ?? TypeExpedition::LD->value;

            if ($modeExpedition === TypeExpedition::LD->value || $modeExpedition === 'simple') {
                $indiceReference = (float) max($poidsTotal, $volumeTotal / 5000);
                $indiceArrondi = $this->tarificationService->arrondirIndice($indiceReference);

                // Chercher le tarif agence correspondant
                $tarifAgence = TarifAgenceSimple::where('agence_id', $donneesExpedition['agence_id'])
                    ->where('indice', $indiceArrondi)
                    ->where('actif', true)
                    ->first();

                if (!$tarifAgence) {
                    return [
                        'success' => false,
                        'message' => "Aucun tarif trouvé pour l'indice {$indiceArrondi} dans cette agence."
                    ];
                }

                $prixZone = $tarifAgence->getPrixPourZone($zoneDestination->id);
                if (!$prixZone) {
                    return [
                        'success' => false,
                        'message' => 'Aucun tarif trouvé pour la zone de destination dans cette agence.'
                    ];
                }

                return [
                    'success' => true,
                    'tarif' => $prixZone
                ];
            } else {
                // Pour le groupage, on simule article par article ou par catégorie
                // Note: Ici on simplifie en prenant la première catégorie trouvée ou en bouclant
                // Pour une simulation client, ils envoient souvent un mélange.
                // Ici on va renvoyer un succès global basé sur TarificationService::simulerTarification
                $resultat = $this->tarificationService->simulerTarification(array_merge($donneesExpedition, [
                    'poids' => $poidsTotal,
                    'type_expedition' => $modeExpedition,
                    'pays_arrivee' => $zoneDestination->pays[0] ?? '',
                    'category_id' => $articles[0]['category_id'] ?? ($articles[0]['produit_id'] ?? null)  // Fallback simpliste
                ]));

                return $resultat;
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur technique lors de la simulation : ' . $e->getMessage()
            ];
        }
    }
}
