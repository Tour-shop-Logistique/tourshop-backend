<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TarifAgenceGroupage extends Model
{
    use HasFactory;

    protected $table = 'tarifs_agence_groupage';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });

        // Recalculer les montants avant sauvegarde
        static::saving(function ($model) {
            if ($model->tarif_groupage_id && isset($model->pourcentage_prestation)) {
                $tarifBack = TarifGroupage::find($model->tarif_groupage_id);
                if ($tarifBack && isset($tarifBack->montant_base)) {
                    $model->montant_base = $tarifBack->montant_base;
                    $model->montant_prestation = round(($model->montant_base * $model->pourcentage_prestation) / 100, 2, PHP_ROUND_HALF_UP);
                    $model->montant_expedition = round($model->montant_base + $model->montant_prestation, 2, PHP_ROUND_HALF_UP);
                }
            }
        });
    }

    protected $fillable = [
        'agence_id',
        'tarif_groupage_id',
        'type_expedition',
        'category_id',
        'mode',
        'ligne',
        'montant_base',
        'pourcentage_prestation',
        'montant_prestation',
        'montant_expedition',
        'pays',
        'actif',
    ];

    protected $casts = [
        'montant_base' => 'float',
        'pourcentage_prestation' => 'float',
        'montant_prestation' => 'float',
        'montant_expedition' => 'float',
        'actif' => 'boolean',
    ];

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryProduct::class, 'category_id');
    }

    public function tarifGroupage(): BelongsTo
    {
        return $this->belongsTo(TarifGroupage::class, 'tarif_groupage_id');
    }

    public function scopePourAgence($query, $agenceId)
    {
        return $query->where('agence_id', $agenceId);
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
