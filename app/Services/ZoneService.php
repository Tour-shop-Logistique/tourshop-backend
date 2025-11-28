<?php

namespace App\Services;

use App\Models\Zone;
use Illuminate\Support\Facades\Cache;

class ZoneService
{
    /**
     * Récupère une zone par pays depuis le cache.
     * Cache pendant 24h car les zones changent rarement.
     */
    public function getZoneByCountry(string $country): ?Zone
    {
        return Cache::remember("zone_country_{$country}", 86400, function () use ($country) {
            return Zone::whereJsonContains('pays', $country)->first();
        });
    }

    /**
     * Récupère une zone par ID depuis le cache.
     */
    public function getZoneById(string $id): ?Zone
    {
        return Cache::remember("zone_id_{$id}", 86400, function () use ($id) {
            return Zone::find($id);
        });
    }

    /**
     * Récupère toutes les zones actives depuis le cache.
     */
    public function getAllActiveZones()
    {
        return Cache::remember("zones_active", 86400, function () {
            return Zone::where('actif', true)->orderBy('nom')->get();
        });
    }

    /**
     * Vide le cache pour une zone spécifique.
     * À appeler après modification/suppression d'une zone.
     */
    public function clearZoneCache(?string $zoneId = null): void
    {
        if ($zoneId) {
            $zone = Zone::find($zoneId);
            if ($zone && $zone->pays) {
                // Vider le cache pour chaque pays de cette zone
                foreach ($zone->pays as $country) {
                    Cache::forget("zone_country_{$country}");
                }
            }
            Cache::forget("zone_id_{$zoneId}");
        }

        // Vider le cache de toutes les zones actives
        Cache::forget("zones_active");
    }

    /**
     * Vide tout le cache des zones.
     * Utile après des modifications en masse.
     */
    public function clearAllZonesCache(): void
    {
        // Récupérer toutes les zones pour vider leur cache
        $zones = Zone::all();
        foreach ($zones as $zone) {
            $this->clearZoneCache($zone->id);
        }
    }
}
