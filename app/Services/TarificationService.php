<?php

namespace App\Services;

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

}
