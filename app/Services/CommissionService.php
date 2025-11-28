<?php

namespace App\Services;

use App\Models\CommissionSetting;
use Illuminate\Support\Facades\Cache;

class CommissionService
{
    /**
     * Récupère un paramètre de commission depuis le cache.
     * Retourne l'objet complet pour éviter les requêtes multiples.
     */
    private function getSettingFromCache(string $key): ?CommissionSetting
    {
        return Cache::remember("commission_setting_{$key}", 3600, function () use ($key) {
            return CommissionSetting::where('key', $key)->where('is_active', true)->first();
        });
    }

    /**
     * Calculate the commission amount based on a base amount and a key.
     * Utilise le cache pour éviter les requêtes DB répétées.
     */
    public function calculateCommission(float $amount, string $key, float $defaultRate = 0.0): float
    {
        $setting = $this->getSettingFromCache($key);

        if (!$setting) {
            // Fallback to pourcentage calculation with default rate
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
