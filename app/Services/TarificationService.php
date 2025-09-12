<?php

namespace App\Services;

use App\Models\Tarif;
use App\Models\Zone;
use App\Enums\ModeExpedition;
use App\Enums\TypeColis;

class TarificationService
{
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
     * Arrondit l'indice selon les règles : .0 ou .5 uniquement
     * Exemples: 2.6 → 3.0, 1.34 → 1.5, 1.25 → 1.5
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
     * Trouve le tarif approprié selon les critères
     */
    public function trouverTarifPourColis(
        string $zoneDepart,
        string $zoneArrivee,
        string $typeColis,
        string $modeExpedition,
        float $poids,
        float $longueur = 0,
        float $largeur = 0,
        float $hauteur = 0
    ): ?Tarif {
        // Calculer l'indice de référence
        $indiceReference = $this->determinerIndiceReference($poids, $longueur, $largeur, $hauteur);
        $indiceArrondi = $this->arrondirIndice($indiceReference);

        // Chercher le tarif correspondant
        return Tarif::where('zone_depart_id', $zoneDepart)
            ->where('zone_arrivee_id', $zoneArrivee)
            ->where('type_colis', $typeColis)
            ->where('mode_expedition', $modeExpedition)
            ->where('indice_tranche', $indiceArrondi)
            ->where('actif', true)
            ->first();
    }

    /**
     * Calcule le prix pour un mode simple
     */
    public function calculerPrixSimple(Tarif $tarif, float $poids): float
    {
        if ($tarif->mode_expedition !== ModeExpedition::SIMPLE) {
            return 0;
        }

        $montantBase = $tarif->montant_base ?? 0;
        $pourcentagePrestation = $tarif->pourcentage_prestation ?? 0;

        $supplementPrestation = ($montantBase * $pourcentagePrestation) / 100;

        return $montantBase + $supplementPrestation;
    }

    /**
     * Calcule le prix pour un mode groupage
     */
    public function calculerPrixGroupage(Tarif $tarif, float $poids, bool $livraisonDomicile = false): float
    {
        if ($tarif->mode_expedition !== ModeExpedition::GROUPAGE) {
            return 0;
        }

        $prixBase = $tarif->prix_entrepot ?? 0;

        if ($livraisonDomicile) {
            $prixBase += $tarif->supplement_domicile_groupage ?? 0;
        }

        return $prixBase;
    }

    /**
     * Simulation complète de tarification
     */
    public function simulerTarification(array $donnees): array
    {
        // Trouver les zones correspondantes
        $zoneDepart = Zone::whereJsonContains('pays', $donnees['pays_depart'])->first();
        $zoneArrivee = Zone::whereJsonContains('pays', $donnees['pays_arrivee'])->first();

        if (!$zoneDepart || !$zoneArrivee) {
            throw new \Exception('Zone non trouvée pour un ou plusieurs pays.');
        }

        // Calculer l'indice de référence
        $indiceReference = $this->determinerIndiceReference(
            $donnees['poids'],
            $donnees['longueur'] ?? 0,
            $donnees['largeur'] ?? 0,
            $donnees['hauteur'] ?? 0
        );

        $indiceArrondi = $this->arrondirIndice($indiceReference);

        // Chercher le tarif correspondant
        $tarif = $this->trouverTarifPourColis(
            $zoneDepart->id,
            $zoneArrivee->id,
            $donnees['type_colis'],
            $donnees['mode_expedition'],
            $donnees['poids'],
            $donnees['longueur'] ?? 0,
            $donnees['largeur'] ?? 0,
            $donnees['hauteur'] ?? 0
        );

        if (!$tarif) {
            throw new \Exception('Aucun tarif trouvé pour ces critères.');
        }

        // Calculer le prix selon le mode
        $prix = 0;
        $detailsCalcul = [];

        if ($donnees['mode_expedition'] === 'simple') {
            $prix = $this->calculerPrixSimple($tarif, $donnees['poids']);
            $detailsCalcul = [
                'montant_base' => $tarif->montant_base,
                'pourcentage_prestation' => $tarif->pourcentage_prestation,
                'supplement_prestation' => ($tarif->montant_base * $tarif->pourcentage_prestation) / 100,
                'volume_cm3' => ($donnees['longueur'] ?? 0) * ($donnees['largeur'] ?? 0) * ($donnees['hauteur'] ?? 0),
                'volume_divise' => $this->calculerVolumeDivise($donnees['longueur'] ?? 0, $donnees['largeur'] ?? 0, $donnees['hauteur'] ?? 0),
                'indice_reference' => $indiceReference,
                'indice_arrondi' => $indiceArrondi
            ];
        } else {
            $livraisonDomicile = $donnees['livraison_domicile'] ?? false;
            $prix = $this->calculerPrixGroupage($tarif, $donnees['poids'], $livraisonDomicile);
            $detailsCalcul = [
                'prix_entrepot' => $tarif->prix_entrepot,
                'supplement_domicile' => $livraisonDomicile ? $tarif->supplement_domicile_groupage : 0,
                'livraison_domicile' => $livraisonDomicile,
                'indice_reference' => $indiceReference,
                'indice_arrondi' => $indiceArrondi
            ];
        }

        return [
            'prix_total' => $prix,
            'devise' => 'FCFA',
            'zone_depart' => $zoneDepart->nom,
            'zone_arrivee' => $zoneArrivee->nom,
            'type_colis' => TypeColis::from($donnees['type_colis'])->label(),
            'mode_expedition' => ModeExpedition::from($donnees['mode_expedition'])->label(),
            'poids_kg' => $donnees['poids'],
            'dimensions_cm' => $donnees['mode_expedition'] === 'simple' ? [
                'longueur' => $donnees['longueur'] ?? 0,
                'largeur' => $donnees['largeur'] ?? 0,
                'hauteur' => $donnees['hauteur'] ?? 0
            ] : null,
            'delai_livraison_heures' => $tarif->delai_livraison,
            'details_calcul' => $detailsCalcul,
            'tarif_utilise' => [
                'id' => $tarif->id,
                'nom' => $tarif->nom,
                'agence' => $tarif->agence->nom ?? 'N/A'
            ]
        ];
    }

    /**
     * Calcul de distance (méthode existante conservée)
     */
    private function calculerDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371; // Rayon de la Terre en km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
