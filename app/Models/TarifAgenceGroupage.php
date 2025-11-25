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

        // Recalculer les montants pour chaque mode avant sauvegarde
        static::saving(function ($model) {
            if ($model->prix_modes && $model->tarif_groupage_id) {
                $prixModes = $model->prix_modes;
                $tarifBack = TarifGroupage::find($model->tarif_groupage_id);
                $baseByMode = [];
                if ($tarifBack && is_array($tarifBack->prix_modes)) {
                    foreach ($tarifBack->prix_modes as $m) {
                        if (isset($m['mode'])) {
                            $baseByMode[$m['mode']] = $m;
                        }
                    }
                }
                foreach ($prixModes as &$mode) {
                    if (isset($mode['mode']) && isset($mode['pourcentage_prestation'])) {
                        $base = $baseByMode[$mode['mode']] ?? null;
                        if ($base && isset($base['montant_base'])) {
                            $mode['montant_base'] = $base['montant_base'];
                            $mode['montant_prestation'] = round(($mode['montant_base'] * $mode['pourcentage_prestation']) / 100, 2, PHP_ROUND_HALF_UP);
                            $mode['montant_expedition'] = round($mode['montant_base'] + $mode['montant_prestation'], 2, PHP_ROUND_HALF_UP);
                        }
                    }
                }
                $model->prix_modes = $prixModes;
            }
        });
    }

    protected $fillable = [
        'agence_id',
        'tarif_groupage_id',
        'category_id',
        'prix_modes',
        'actif',
    ];

    protected $casts = [
        'prix_modes' => 'array',
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

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
