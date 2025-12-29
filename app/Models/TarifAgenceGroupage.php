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
                $defaultBase = null;

                if ($tarifBack && is_array($tarifBack->prix_modes) && count($tarifBack->prix_modes) > 0) {
                    // Le premier élément sert de défaut si aucun mode n'est spécifié
                    $defaultBase = $tarifBack->prix_modes[0];

                    foreach ($tarifBack->prix_modes as $m) {
                        if (isset($m['mode'])) {
                            $baseByMode[$m['mode']] = $m;
                        }
                    }
                }

                foreach ($prixModes as &$mode) {
                    if (isset($mode['pourcentage_prestation'])) {
                        $base = null;

                        if (!empty($mode['mode']) && isset($baseByMode[$mode['mode']])) {
                            // Mode spécifié et trouvé dans le tarif parent
                            $base = $baseByMode[$mode['mode']];
                        } elseif (empty($mode['mode'])) {
                            // Mode non spécifié : utiliser le tarif par défaut (premier trouvé)
                            $base = $defaultBase;
                        }

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

    public function scopePourAgence($query, $agenceId)
    {
        return $query->where('agence_id', $agenceId);
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
