<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Enums\ModeExpedition;

class TarifSimple extends Model
{
    use HasFactory;

    protected $table = 'tarifs_simple';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Génère un UUID avant la création
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });

        // Calculer automatiquement les montants lors de la sauvegarde
        static::saving(function ($model) {
            if ($model->prix_zones) {
                $prixZones = $model->prix_zones;
                foreach ($prixZones as &$zone) {
                    if (isset($zone['montant_base']) && isset($zone['pourcentage_prestation'])) {
                        $zone['montant_prestation'] = round(($zone['montant_base'] * $zone['pourcentage_prestation']) / 100, 2, PHP_ROUND_HALF_UP);
                        $zone['montant_expedition'] = round($zone['montant_base'] + $zone['montant_prestation'], 2, PHP_ROUND_HALF_UP);
                    }
                }
                $model->prix_zones = $prixZones;
            }
        });
    }

    /**
     * Les attributs qui peuvent être massivement assignés
     */
    protected $fillable = [
        'indice',
        'mode_expedition',
        'prix_zones',
        'actif',
        'pays',
        'backoffice_id',
    ];

    /**
     * Les attributs qui doivent être castés
     */
    protected $casts = [
        'indice' => 'decimal:1',
        'prix_zones' => 'array',
        'mode_expedition' => ModeExpedition::class,
        'actif' => 'boolean',
    ];

    /**
     * Un tarif de base peut être référencé par plusieurs tarifs d'agence
     */
    public function tarifsAgence(): HasMany
    {
        return $this->hasMany(TarifAgenceSimple::class, 'tarif_simple_id');
    }

    /**
     * Scope pour rechercher par critères
     */
    public function scopePourCriteres($query, $zoneDestination, $modeExpedition, $indiceTrancheArrondi)
    {
        $query->where('indice', $indiceTrancheArrondi)
            ->where('mode_expedition', $modeExpedition)
            ->where('actif', true);

        // Filtrer par zone dans le JSON prix_zones
        $query->whereJsonContains('prix_zones', function ($zone) use ($zoneDestination) {
            return $zone['zone_destination_id'] === $zoneDestination;
        });

        return $query;
    }

    /**
     * Obtenir le prix pour une zone spécifique
     */
    public function getPrixPourZone($zoneDestinationId)
    {
        if (!$this->prix_zones) {
            return null;
        }

        foreach ($this->prix_zones as $zone) {
            if ($zone['zone_destination_id'] === $zoneDestinationId) {
                return $zone;
            }
        }

        return null;
    }

    public function backoffice()
    {
        return $this->belongsTo(Backoffice::class, 'backoffice_id');
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
