<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\TypeExpedition;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TarifGroupage extends Model
{
    use HasFactory;

    protected $table = 'tarifs_groupage';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });

        static::saving(function ($model) {
            if ($model->prix_modes) {
                $prixModes = $model->prix_modes;
                foreach ($prixModes as &$mode) {
                    if (isset($mode['montant_base']) && isset($mode['pourcentage_prestation'])) {
                        $mode['montant_prestation'] = round(($mode['montant_base'] * $mode['pourcentage_prestation']) / 100, 2, PHP_ROUND_HALF_UP);
                        $mode['montant_expedition'] = round($mode['montant_base'] + $mode['montant_prestation'], 2, PHP_ROUND_HALF_UP);
                    }
                }
                $model->prix_modes = $prixModes;
            }
        });
    }

    protected $fillable = [
        'category_id',
        'type_expedition',
        'prix_unitaire',
        'prix_modes',
        'actif',
        'pays',
        'backoffice_id',
    ];

    protected $casts = [
        'prix_modes' => 'array',
        'prix_unitaire' => 'decimal:2',
        'type_expedition' => TypeExpedition::class,
        'actif' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryProduct::class, 'category_id');
    }

    public function backoffice(): BelongsTo
    {
        return $this->belongsTo(Backoffice::class, 'backoffice_id');
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function getPrixPourMode(string $mode)
    {
        if (!$this->prix_modes) {
            return null;
        }

        foreach ($this->prix_modes as $prixMode) {
            if ($prixMode['mode'] === $mode) {
                return $prixMode;
            }
        }

        return null;
    }
}
