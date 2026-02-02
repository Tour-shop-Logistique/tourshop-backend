<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Enums\TypeExpedition;

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
            if (isset($model->montant_base) && isset($model->pourcentage_prestation)) {
                $model->montant_prestation = round(($model->montant_base * $model->pourcentage_prestation) / 100, 2, PHP_ROUND_HALF_UP);
                $model->montant_expedition = round($model->montant_base + $model->montant_prestation, 2, PHP_ROUND_HALF_UP);
            }
        });
    }

    /**
     * Les attributs qui peuvent être massivement assignés
     */
    protected $fillable = [
        'indice',
        'type_expedition',
        'zone_destination_id',
        'montant_base',
        'pourcentage_prestation',
        'montant_prestation',
        'montant_expedition',
        'actif',
        'pays',
        'backoffice_id',
    ];

    /**
     * Les attributs qui doivent être castés
     */
    protected $casts = [
        'indice' => 'decimal:1',
        'montant_base' => 'float',
        'pourcentage_prestation' => 'float',
        'montant_prestation' => 'float',
        'montant_expedition' => 'float',
        'type_expedition' => TypeExpedition::class,
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
    public function scopePourCriteres($query, $zoneDestinationId, $indiceTrancheArrondi)
    {
        return $query->where('indice', $indiceTrancheArrondi)
            ->where('zone_destination_id', $zoneDestinationId)
            ->where('actif', true);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_destination_id');
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
