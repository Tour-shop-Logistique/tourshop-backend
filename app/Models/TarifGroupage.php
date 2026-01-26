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
            if (isset($model->montant_base) && isset($model->pourcentage_prestation)) {
                $model->montant_prestation = round(($model->montant_base * $model->pourcentage_prestation) / 100, 2, PHP_ROUND_HALF_UP);
                $model->montant_expedition = round($model->montant_base + $model->montant_prestation, 2, PHP_ROUND_HALF_UP);
            }
        });
    }

    protected $fillable = [
        'category_id',
        'type_expedition',
        'mode',
        'ligne',
        'montant_base',
        'pourcentage_prestation',
        'montant_prestation',
        'montant_expedition',
        'actif',
        'pays',
        'backoffice_id',
    ];

    protected $casts = [
        'montant_base' => 'float',
        'pourcentage_prestation' => 'float',
        'montant_prestation' => 'float',
        'montant_expedition' => 'float',
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
}
