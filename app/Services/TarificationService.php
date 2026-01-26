<?php

namespace App\Services;

use App\Enums\TypeColis;
use App\Enums\TypeExpedition;
use App\Models\CategoryProduct;
use App\Models\TarifAgenceGroupage;
use App\Models\TarifAgenceSimple;
use App\Models\TarifSimple;
use App\Models\TarifGroupage;
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
     */
    public function trouverTarifPourColis(
        string $zoneDestination,
        string $typeExpedition,
        float $poids,
        ?string $agenceId = null,
        ?string $categoryId = null,
        bool $livraisonDomicile = false,
        ?float $indiceArrondi = null,
        ?string $ligne = null
    ): ?array {
        // 1) Si une agence est fournie, on cherche d'abord un tarif d'agence
        if ($agenceId) {
            // 1.a) Priorité: tarif d'agence GROUPAGE
            if ($typeExpedition !== TypeExpedition::LD && !empty($categoryId)) {
                $modeLivraison = $livraisonDomicile ? 'domicile' : 'agence';

                $tag = TarifAgenceGroupage::where('agence_id', $agenceId)
                    ->where('category_id', $categoryId)
                    ->whereHas('tarifGroupage', function ($q) use ($modeLivraison, $ligne) {
                        $q->where('mode', $modeLivraison);
                        if ($ligne) {
                            $q->where(function ($qr) use ($ligne) {
                                $qr->where('ligne', strtolower($ligne))
                                    ->orWhere('ligne', 'autres');
                            });
                        }
                    })
                    ->actif()
                    ->first();

                if ($tag) {
                    return [
                        'source' => 'agence_groupage',
                        'id' => $tag->id,
                        'montant_base' => round((float) $tag->montant_base, 2, PHP_ROUND_HALF_UP),
                        'pourcentage_prestation' => round((float) $tag->pourcentage_prestation, 2, PHP_ROUND_HALF_UP),
                        'montant_prestation' => round((float) $tag->montant_prestation, 2, PHP_ROUND_HALF_UP),
                        'montant_expedition' => round((float) $tag->montant_expedition, 2, PHP_ROUND_HALF_UP),
                    ];
                }
            }

            // 1.b) Sinon, tenter le tarif d'agence SIMPLE
            $tarifAgence = TarifAgenceSimple::where('agence_id', $agenceId)
                ->where('indice', $indiceArrondi)
                ->actif()
                ->first();

            if ($tarifAgence) {
                $prixZoneAgence = $tarifAgence->getPrixPourZone($zoneDestination);
                if ($prixZoneAgence) {
                    return [
                        'source' => 'agence_simple',
                        'id' => $tarifAgence->id,
                        'montant_base' => round((float) ($prixZoneAgence['montant_base'] ?? 0), 2, PHP_ROUND_HALF_UP),
                        'pourcentage_prestation' => round((float) ($prixZoneAgence['pourcentage_prestation'] ?? 0), 2, PHP_ROUND_HALF_UP),
                        'montant_prestation' => round((float) ($prixZoneAgence['montant_prestation'] ?? 0), 2, PHP_ROUND_HALF_UP),
                        'montant_expedition' => round((float) ($prixZoneAgence['montant_expedition'] ?? 0), 2, PHP_ROUND_HALF_UP),
                    ];
                }
            }
        }

        // 2) Sinon on cherche un tarif de base BACKOFFICE pour le groupage si categoryId fourni
        if ($typeExpedition !== TypeExpedition::LD && !empty($categoryId)) {
            $modeLivraison = $livraisonDomicile ? 'domicile' : 'agence';

            $tg = TarifGroupage::where('category_id', $categoryId)
                ->where('mode', $modeLivraison)
                ->actif();

            if ($ligne) {
                $tg->where(function ($q) use ($ligne) {
                    $q->where('ligne', strtolower($ligne))
                        ->orWhere('ligne', 'autres');
                })->orderByRaw("CASE WHEN ligne = ? THEN 0 ELSE 1 END", [strtolower($ligne)]);
            }

            $tarifBase = $tg->first();

            if ($tarifBase) {
                return [
                    'source' => 'base_groupage',
                    'id' => $tarifBase->id,
                    'montant_base' => round((float) $tarifBase->montant_base, 2, PHP_ROUND_HALF_UP),
                    'pourcentage_prestation' => round((float) $tarifBase->pourcentage_prestation, 2, PHP_ROUND_HALF_UP),
                    'montant_prestation' => round((float) $tarifBase->montant_prestation, 2, PHP_ROUND_HALF_UP),
                    'montant_expedition' => round((float) $tarifBase->montant_expedition, 2, PHP_ROUND_HALF_UP),
                ];
            }
        }

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

        // Déterminer la ligne (ville d'arrivée)
        $ligne = !empty($donnees['ville_arrivee']) ? strtolower(trim($donnees['ville_arrivee'])) : strtolower(trim($donnees['pays_arrivee'] ?? ''));

        // Calculer l'indice de référence (max entre poids et volume/5000)
        $indiceReference = $this->determinerIndiceReference(
            (float) $donnees['poids'],
            (float) ($donnees['longueur'] ?? 0),
            (float) ($donnees['largeur'] ?? 0),
            (float) ($donnees['hauteur'] ?? 0)
        );
        $indiceArrondi = $this->arrondirIndice($indiceReference);

        // Chercher le tarif
        $tarif = $this->trouverTarifPourColis(
            $zoneDestination->id,
            $donnees['type_expedition'],
            (float) $donnees['poids'],
            $donnees['agence_id'] ?? null,
            $donnees['category_id'] ?? null,
            $donnees['is_livraison_domicile'] ?? false,
            $indiceArrondi,
            $ligne
        );

        if (!$tarif) {
            // Fallback groupage: utiliser le prix_kg de la catégorie si fourni
            if (($donnees['type_expedition'] ?? null) !== TypeExpedition::LD->value && !empty($donnees['category_id'])) {
                $category = CategoryProduct::find($donnees['category_id']);
                if ($category && !empty($category->prix_kg)) {
                    $prixKg = 0;
                    $prixTrouve = false;

                    if (is_array($category->prix_kg)) {
                        // Match exact sur la ligne
                        foreach ($category->prix_kg as $item) {
                            if (strtolower(trim($item['ligne'] ?? '')) === $ligne) {
                                $prixKg = (float) ($item['prix'] ?? 0);
                                $prixTrouve = true;
                                break;
                            }
                        }

                        // Fallback sur "autres"
                        if (!$prixTrouve) {
                            foreach ($category->prix_kg as $item) {
                                if (strtolower(trim($item['ligne'] ?? '')) === 'autres') {
                                    $prixKg = (float) ($item['prix'] ?? 0);
                                    $prixTrouve = true;
                                    break;
                                }
                            }
                        }
                    }

                    if ($prixTrouve) {
                        $prixBase = $prixKg * (float) $donnees['poids'];
                        $tarif = [
                            'source' => 'category_prix_kg',
                            'id' => $category->id,
                            'montant_base' => round($prixBase, 2, PHP_ROUND_HALF_UP),
                            'pourcentage_prestation' => 0.0,
                            'montant_prestation' => 0.0,
                            'montant_expedition' => round($prixBase, 2, PHP_ROUND_HALF_UP),
                        ];
                    }
                }

                if (!$tarif) {
                    return [
                        'success' => false,
                        'message' => 'Aucun tarif disponible pour cette catégorie et cette destination.',
                        'details' => [
                            'zone' => $zoneDestination->nom,
                            'category' => $donnees['category_id'] ?? null,
                            'ligne' => $ligne
                        ]
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Aucun tarif trouvé pour ces critères.',
                ];
            }
        }

        // Construction du résultat
        $prix = $tarif['montant_expedition'];
        $detailsCalcul = [
            'montant_base' => $tarif['montant_base'],
            'pourcentage_prestation' => $tarif['pourcentage_prestation'],
            'montant_prestation' => $tarif['montant_prestation'],
            'indice_reference' => $indiceReference,
            'indice_arrondi' => $indiceArrondi
        ];

        return [
            'success' => true,
            'simulation' => [
                'prix_total' => round($prix, 2, PHP_ROUND_HALF_UP),
                'devise' => 'FCFA',
                'zone_destination' => $zoneDestination->nom,
                'poids_kg' => $donnees['poids'],
                'details_calcul' => $detailsCalcul,
            ],
            'tarif_utilise' => [
                'source' => $tarif['source'],
                'id' => $tarif['id'],
                'ligne' => $ligne
            ]
        ];
    }
}
