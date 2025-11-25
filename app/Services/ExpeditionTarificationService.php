<?php

namespace App\Services;

use App\Models\Expedition;
use App\Models\ExpeditionArticle;
use App\Models\Zone;
use App\Services\TarificationService;
use App\Enums\ModeExpedition;

class ExpeditionTarificationService
{
    protected TarificationService $tarificationService;

    public function __construct(TarificationService $tarificationService)
    {
        $this->tarificationService = $tarificationService;
    }

    /**
     * Calcule le tarif d'une expédition en fonction de ses articles
     */
    public function calculerTarifExpedition(Expedition $expedition): array
    {
        if ($expedition->articles->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Aucun article dans cette expédition'
            ];
        }

        // Récupérer les zones pour obtenir les pays
        $zoneDepart = Zone::find($expedition->zone_depart_id);
        $zoneDestination = Zone::find($expedition->zone_destination_id);
        
        if (!$zoneDepart || !$zoneDestination) {
            return [
                'success' => false,
                'message' => 'Zone(s) invalide(s)'
            ];
        }

        if ($expedition->mode_expedition === ModeExpedition::SIMPLE->value) {
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
        // Pour le mode simple, on additionne tous les volumes et poids
        $poidsTotal = $expedition->poids_total;
        $volumeTotal = $expedition->volume_total;
        
        // Déterminer l'indice de référence selon la logique du TarificationService
        $indiceReference = $this->tarificationService->determinerIndiceReference(
            $poidsTotal,
            $expedition->longueur_totale,
            $expedition->largeur_totale,
            $expedition->hauteur_totale
        );

        $donnees = [
            'pays_depart' => $zoneDepart->pays[0] ?? '',
            'pays_arrivee' => $zoneDestination->pays[0] ?? '',
            'mode_expedition' => 'simple',
            'poids' => $poidsTotal,
            'longueur' => $expedition->longueur_totale,
            'largeur' => $expedition->largeur_totale,
            'hauteur' => $expedition->hauteur_totale,
            'agence_id' => $expedition->agence_id,
            'category_id' => null,
            'livraison_domicile' => false
        ];

        return $this->tarificationService->simulerTarification($donnees);
    }

    /**
     * Calcule le tarif pour le mode groupage (basé sur les catégories de produits)
     */
    private function calculerTarifGroupage(Expedition $expedition, Zone $zoneDepart, Zone $zoneDestination): array
    {
        $tarifsParArticle = [];
        $montantTotal = 0;

        // Grouper les articles par catégorie de produit
        $articlesParCategorie = $expedition->articles
            ->where('produit_id', '!=', null)
            ->groupBy('produit.category_id');

        foreach ($articlesParCategorie as $categoryId => $articles) {
            $poidsTotalCategorie = $articles->sum('poids_total');
            
            $donnees = [
                'pays_depart' => $zoneDepart->pays[0] ?? '',
                'pays_arrivee' => $zoneDestination->pays[0] ?? '',
                'mode_expedition' => 'groupage',
                'poids' => $poidsTotalCategorie,
                'agence_id' => $expedition->agence_id,
                'category_id' => $categoryId,
                'livraison_domicile' => false
            ];

            $resultatTarif = $this->tarificationService->simulerTarification($donnees);
            
            if ($resultatTarif['success']) {
                $tarifsParArticle[] = [
                    'category_id' => $categoryId,
                    'category_nom' => $articles->first()->produit->category->nom ?? 'Catégorie inconnue',
                    'poids_total' => $poidsTotalCategorie,
                    'nombre_articles' => $articles->count(),
                    'tarif' => $resultatTarif['tarif']
                ];
                $montantTotal += $resultatTarif['simulation']['prix_total'];
            }
        }

        // Traiter les articles sans catégorie (fallback sur prix/kg par défaut ou tarif standard)
        $articlesSansCategorie = $expedition->articles->where('produit_id', null);
        if ($articlesSansCategorie->isNotEmpty()) {
            $poidsSansCategorie = $articlesSansCategorie->sum('poids_total');
            
            // Utiliser un tarif par défaut ou le premier tarif groupage disponible
            $donnees = [
                'pays_depart' => $zoneDepart->pays[0] ?? '',
                'pays_arrivee' => $zoneDestination->pays[0] ?? '',
                'mode_expedition' => 'groupage',
                'poids' => $poidsSansCategorie,
                'agence_id' => $expedition->agence_id,
                'category_id' => null,
                'livraison_domicile' => false
            ];

            $resultatTarif = $this->tarificationService->simulerTarification($donnees);
            
            if ($resultatTarif['success']) {
                $tarifsParArticle[] = [
                    'category_id' => null,
                    'category_nom' => 'Articles non catégorisés',
                    'poids_total' => $poidsSansCategorie,
                    'nombre_articles' => $articlesSansCategorie->count(),
                    'tarif' => $resultatTarif['tarif']
                ];
                $montantTotal += $resultatTarif['simulation']['prix_total'];
            }
        }

        if (empty($tarifsParArticle)) {
            return [
                'success' => false,
                'message' => 'Impossible de calculer le tarif pour cette expédition groupage'
            ];
        }

        return [
            'success' => true,
            'simulation' => [
                'prix_total' => round($montantTotal, 2, PHP_ROUND_HALF_UP),
                'devise' => 'FCFA',
                'mode_expedition' => ModeExpedition::GROUPAGE->label(),
                'poids_total_kg' => $expedition->poids_total,
                'nombre_total_articles' => $expedition->articles->count(),
                'details_par_categorie' => $tarifsParArticle
            ],
            'tarif' => [
                'source' => 'expedition_groupage',
                'montant_expedition' => round($montantTotal, 2, PHP_ROUND_HALF_UP)
            ]
        ];
    }

    /**
     * Met à jour le tarif d'une expédition après modification des articles
     */
    public function mettreAJourTarifExpedition(Expedition $expedition): bool
    {
        // Recalculer les totaux depuis les articles
        $expedition->recalculerTotaux();
        
        // Calculer le nouveau tarif
        $resultatTarif = $this->calculerTarifExpedition($expedition);
        
        if ($resultatTarif['success']) {
            $tarif = $resultatTarif['tarif'];
            $expedition->update([
                'montant_base' => $tarif['montant_base'] ?? 0,
                'pourcentage_prestation' => $tarif['pourcentage_prestation'] ?? 0,
                'montant_prestation' => $tarif['montant_prestation'] ?? 0,
                'montant_expedition' => $tarif['montant_expedition']
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Simule le tarif pour une expédition avec des articles temporaires
     */
    public function simulerTarifExpedition(array $donneesExpedition, array $articles): array
    {
        // Créer une expédition temporaire pour la simulation
        $expeditionTemporaire = new Expedition([
            'mode_expedition' => $donneesExpedition['mode_expedition'],
            'zone_depart_id' => $donneesExpedition['zone_depart_id'],
            'zone_destination_id' => $donneesExpedition['zone_destination_id'],
            'agence_id' => $donneesExpedition['agence_id'] ?? null
        ]);

        // Créer les articles temporaires
        $articlesTemporaires = collect();
        foreach ($articles as $articleData) {
            $article = new ExpeditionArticle($articleData);
            // Calculer les totaux pour cet article
            $article->volume_total = $article->getVolumeTotalAttribute();
            $article->poids_total = $article->getPoidsTotalAttribute();
            $articlesTemporaires->push($article);
        }

        // Associer les articles à l'expédition temporaire
        $expeditionTemporaire->setRelation('articles', $articlesTemporaires);

        return $this->calculerTarifExpedition($expeditionTemporaire);
    }
}
