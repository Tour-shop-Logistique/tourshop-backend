<?php

namespace App\Services;

use App\Models\Tarif;
use App\Models\Agence;

class TarificationService
{
    public function calculerTarif($donnees)
    {
        // Calcul de la distance en km
        $distance = $this->calculerDistance(
            $donnees['lat_enlevement'],
            $donnees['lng_enlevement'],
            $donnees['lat_livraison'],
            $donnees['lng_livraison']
        );

        // Récupération du tarif approprié
        $tarif = $this->obtenirTarif($donnees['agence_id'] ?? null, $donnees['poids']);
        
        if (!$tarif) {
            throw new \Exception('Aucun tarif disponible pour ce colis');
        }

        // Calcul du prix de base
        $prixTotal = $tarif->prix_base + ($distance * $tarif->prix_par_km);

        // Suppléments
        if ($donnees['enlevement_domicile'] ?? false) {
            $prixTotal += $tarif->supplement_domicile;
        }

        if ($donnees['livraison_express'] ?? false) {
            $prixTotal += $tarif->supplement_express;
        }

        return [
            'prix_total' => round($prixTotal, 2),
            'distance_km' => round($distance, 2),
            'tarif_utilise' => $tarif->nom,
            'commission_livreur' => round($prixTotal * 0.15, 2), // 15% pour le livreur
            'commission_agence' => round($prixTotal * 0.05, 2)   // 5% pour l'agence
        ];
    }

    private function calculerDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371; // Rayon de la Terre en km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    private function obtenirTarif($agenceId, $poids)
    {
        $query = Tarif::where('actif', true)->where('poids_max', '>=', $poids);

        if ($agenceId) {
            // Chercher d'abord un tarif spécifique à l'agence
            $tarifAgence = $query->where('agence_id', $agenceId)->first();
            if ($tarifAgence) {
                return $tarifAgence;
            }
        }

        // Sinon, prendre le tarif général (agence_id = null)
        return $query->whereNull('agence_id')->first();
    }
}