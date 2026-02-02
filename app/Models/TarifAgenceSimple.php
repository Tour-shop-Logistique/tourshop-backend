<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TarifAgenceSimple extends Model
{
    use HasFactory;

    protected $table = 'tarifs_agence_simple';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });

        // Calculer automatiquement les montants lors de la sauvegarde
        static::saving(function ($model) {
            // Copier l'indice et la zone du tarif simple si non définis
            if ($model->tarif_simple_id && (!$model->indice || !$model->zone_destination_id)) {
                $tarifSimple = TarifSimple::find($model->tarif_simple_id);
                if ($tarifSimple) {
                    $model->indice = $model->indice ?? $tarifSimple->indice;
                    $model->zone_destination_id = $model->zone_destination_id ?? $tarifSimple->zone_destination_id;
                    $model->montant_base = $model->montant_base ?? $tarifSimple->montant_base;
                }
            }

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
        'agence_id',
        'tarif_simple_id',
        'indice',
        'zone_destination_id',
        'montant_base',
        'pourcentage_prestation',
        'montant_prestation',
        'montant_expedition',
        'actif',
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
        'actif' => 'boolean',
    ];

    /**
     * Un tarif d'agence appartient à une agence
     */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    /**
     * Un tarif d'agence est basé sur un tarif simple
     */
    public function tarifSimple(): BelongsTo
    {
        return $this->belongsTo(TarifSimple::class, 'tarif_simple_id');
    }


    public function scopePourCriteres($query, $agenceId, $zoneDestinationId, $indiceTrancheArrondi)
    {
        return $query->where('agence_id', $agenceId)
            ->where('indice', $indiceTrancheArrondi)
            ->where('zone_destination_id', $zoneDestinationId)
            ->where('actif', true);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_destination_id');
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
