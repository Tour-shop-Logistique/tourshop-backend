<?php

namespace App\Services;

use App\Models\CommissionSetting;
use Illuminate\Support\Facades\Cache;

class CommissionService
{
    /**
     * Cache statique en mémoire vive pour la durée de la requête actuelle.
     */
    private static $settingsCache = null;

    /**
     * Charge tous les réglages actifs d'un coup (Évite le N+1 en boucle).
     */
    private function getAllSettings()
    {
        if (self::$settingsCache === null) {
            // On récupère tout le tableau indexé par clé
            self::$settingsCache = CommissionSetting::where('is_active', true)->get()->keyBy('key');
        }
        return self::$settingsCache;
    }

    /**
     * Calculate the commission amount based on a base amount and a key.
     * Utilise le cache mémoire pour être ultra-rapide en boucle.
     */
    public function calculateCommission(float $amount, string $key, float $defaultRate = 0.0): float
    {
        $settings = $this->getAllSettings();
        $setting = $settings->get($key);

        if (!$setting) {
            // Fallback au calcul par défaut si pas en base
            return $amount * ($defaultRate / 100);
        }

        if ($setting->type === 'fixe') {
            return (float) $setting->value;
        }

        // Calcul en pourcentage
        return $amount * ((float) $setting->value / 100);
    }

    /**
     * Clear the cache for a specific key.
     */
    public function clearCache(string $key): void
    {
        Cache::forget("commission_setting_{$key}");
    }
}
