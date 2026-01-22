<?php

namespace App\Services;

use App\Enums\TypeColis;
use App\Enums\TypeExpedition;
use App\Models\CategoryProduct;
use App\Models\TarifAgenceGroupage;
use App\Models\TarifAgenceSimple;
use App\Models\TarifSimple;
use App\Models\Zone;
use App\Services\ZoneService;

class TarificationService
{
    protected ZoneService $zoneService;

    public function __construct(ZoneService $zoneService)
    {
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
     * Trouve un tarif normalisé (base ou agence) selon les critères
     * Retourne un tableau normalisé avec: source, nom, montant_base, pourcentage_prestation, montant_prestation, montant_expedition
     */
    public function trouverTarifPourColis(
        string $zoneDestination,
        string $typeExpedition,
        float $poids,
        ?string $agenceId = null,
        ?string $categoryId = null,
        bool $livraisonDomicile = false,
        ?float $indiceArrondi = null
    ): ?array {
        // 1) Si une agence est fournie, on cherche d'abord un tarif d'agence
        if ($agenceId) {
            // 1.a) Priorité: tarif d'agence GROUPAGE spécifique à la catégorie et au mode (agence/domicile)
            if ($typeExpedition !== TypeExpedition::LD && !empty($categoryId)) {
                $modeLivraison = $livraisonDomicile ? 'domicile' : 'agence';
                $tag = TarifAgenceGroupage::where('agence_id', $agenceId)
                    ->where('category_id', $categoryId)
                    ->actif()
                    ->first();

                if ($tag) {
                    $prixModes = $tag->prix_modes ?? [];
                    foreach ($prixModes as $m) {
                        if (($m['mode'] ?? null) === $modeLivraison) {
                            return [
                                'montant_base' => round((float) $m['montant_base'], 2, PHP_ROUND_HALF_UP),
                                'pourcentage_prestation' => round((float) $m['pourcentage_prestation'], 2, PHP_ROUND_HALF_UP),
                                'montant_prestation' => round((float) $m['montant_prestation'], 2, PHP_ROUND_HALF_UP),
                                'montant_expedition' => round((float) $m['montant_expedition'], 2, PHP_ROUND_HALF_UP),
                            ];
                        }
                    }
                }
            }

            // 1.b) Sinon, tenter le tarif d'agence SIMPLE existant basé sur un tarif de base correspondant
            $tarifAgence = TarifAgenceSimple::where('agence_id', $agenceId)
                ->where('indice', $indiceArrondi)
                ->actif()
                ->first();

            if ($tarifAgence) {
                $prixZoneAgence = $tarifAgence->getPrixPourZone($zoneDestination);
                if ($prixZoneAgence) {
                    return [
                        'montant_base' => round((float) ($prixZoneAgence['montant_base'] ?? 0), 2, PHP_ROUND_HALF_UP),
                        'pourcentage_prestation' => round((float) ($prixZoneAgence['pourcentage_prestation'] ?? 0), 2, PHP_ROUND_HALF_UP),
                        'montant_prestation' => round((float) ($prixZoneAgence['montant_prestation'] ?? 0), 2, PHP_ROUND_HALF_UP),
                        'montant_expedition' => round((float) ($prixZoneAgence['montant_expedition'] ?? 0), 2, PHP_ROUND_HALF_UP),
                    ];
                }
            }
        }

        // // 2) Sinon on cherche un tarif de base
        // $tarifBase = TarifSimple::pourCriteres($zoneDestination, $typeExpedition, $indiceArrondi)->first();
        // if ($tarifBase) {
        //     $prixZone = $tarifBase->getPrixPourZone($zoneDestination);
        //     if ($prixZone) {
        //         return [
        //             'source' => 'base',
        //             'id' => $tarifBase->id,
        //             'indice' => $indiceArrondi,
        //             'agence_nom' => null,
        //             'montant_base' => round((float) $prixZone['montant_base'], 2, PHP_ROUND_HALF_UP),
        //             'pourcentage_prestation' => round((float) $prixZone['pourcentage_prestation'], 2, PHP_ROUND_HALF_UP),
        //             'montant_prestation' => round((float) $prixZone['montant_prestation'], 2, PHP_ROUND_HALF_UP),
        //             'montant_expedition' => round((float) $prixZone['montant_expedition'], 2, PHP_ROUND_HALF_UP),
        //         ];
        //     }
        // }

        return null;
    }

    /**
     * Simulation principale basée sur zone de destination uniquement
     */
    public function simulerTarification(array $donnees): array
    {
        // Trouver la zone de destination à partir du pays d'arrivée (avec cache)
        $zoneDestination = $this->zoneService->getZoneByCountry($donnees['pays_arrivee']);
        if (!$zoneDestination) {
            throw new \Exception('Zone non trouvée pour un ou plusieurs pays.');
        }

        // Chercher le tarif (agence si agence_id fourni, sinon base)
        $tarif = $this->trouverTarifPourColis(
            $zoneDestination->id,
            $donnees['type_expedition'],
            $donnees['poids'],
            $donnees['longueur'] ?? 0,
            $donnees['largeur'] ?? 0,
            $donnees['hauteur'] ?? 0,
            $donnees['agence_id'] ?? null,
            $donnees['category_id'] ?? null,
            $donnees['is_livraison_domicile'] ?? false,
        );

        // Aucun tarif trouvé
        $indiceReference = $this->determinerIndiceReference($donnees['poids'], $donnees['longueur'] ?? 0, $donnees['largeur'] ?? 0, $donnees['hauteur'] ?? 0);
        $indiceArrondi = $this->arrondirIndice($indiceReference);
        if (!$tarif) {
            // Fallback groupage: utiliser le prix/kg de la catégorie si fourni
            if (($donnees['type_expedition'] ?? null) !== TypeExpedition::LD->value && !empty($donnees['category_id'])) {
                $category = CategoryProduct::find($donnees['category_id']);
                if ($category && $category->prix_kg !== null) {
                    $prixBase = (float) $category->prix_kg * (float) $donnees['poids'];
                    $tarif = [
                        'source' => 'category_prix_kg',
                        'id' => $category->id,
                        'indice' => $indiceArrondi,
                        'agence_nom' => null,
                        'montant_base' => round($prixBase, 2, PHP_ROUND_HALF_UP),
                        'pourcentage_prestation' => 0.0,
                        'montant_prestation' => 0.0,
                        'montant_expedition' => round($prixBase, 2, PHP_ROUND_HALF_UP),
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Aucun tarif par intervalle et aucun prix/kg de catégorie disponible.',
                        'details' => [
                            'zone_destination' => $zoneDestination->nom,
                            'category_id' => $donnees['category_id'] ?? null,
                            'type_expedition' => $donnees['type_expedition'],
                            'indice_calcule' => $indiceArrondi
                        ]
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Aucun tarif trouvé pour ces critères.',
                    'details' => [
                        'zone_destination' => $zoneDestination->nom,
                        'type_colis' => $donnees['type_colis'] ?? null,
                        'type_expedition' => $donnees['type_expedition'],
                        'indice_calcule' => $indiceArrondi
                    ]
                ];
            }
        }

        // Prix final = montant_expedition fourni par le tarif normalisé
        $prix = $tarif['montant_expedition'];
        $detailsCalcul = [
            'montant_base' => $tarif['montant_base'],
            'pourcentage_prestation' => $tarif['pourcentage_prestation'],
            'montant_prestation' => $tarif['montant_prestation'],
            'indice_reference' => $indiceReference,
            'indice_arrondi' => $indiceArrondi
        ];

        // Ajouter les dimensions uniquement pour mode simple
        if (($donnees['type_expedition'] ?? 'simple') === 'simple') {
            $detailsCalcul = array_merge($detailsCalcul, [
                'volume_cm3' => ($donnees['longueur'] ?? 0) * ($donnees['largeur'] ?? 0) * ($donnees['hauteur'] ?? 0),
                'volume_divise' => $this->calculerVolumeDivise($donnees['longueur'] ?? 0, $donnees['largeur'] ?? 0, $donnees['hauteur'] ?? 0),
            ]);
        }

        return [
            'success' => true,
            'simulation' => [
                'prix_total' => round($prix, 2, PHP_ROUND_HALF_UP),
                'devise' => 'FCFA',
                'zone_destination' => $zoneDestination->nom,
                'type_colis' => isset($donnees['type_colis']) && $donnees['type_colis'] ? TypeColis::from($donnees['type_colis'])->label() : null,
                'type_expedition' => TypeExpedition::from($donnees['type_expedition'])->label(),
                'poids_kg' => $donnees['poids'],
                'dimensions_cm' => ($donnees['type_expedition'] === 'simple') ? [
                    'longueur' => $donnees['longueur'] ?? 0,
                    'largeur' => $donnees['largeur'] ?? 0,
                    'hauteur' => $donnees['hauteur'] ?? 0,
                ] : null,
                'details_calcul' => $detailsCalcul,
            ],
            'tarif_utilise' => [
                'source' => $tarif['source'],
                'id' => $tarif['id'],
                'indice' => $tarif['indice'],
                'agence' => $tarif['agence_nom'] ?? ($tarif['source'] === 'base' ? 'Tarif de base' : null)
            ]
        ];
    }
}
