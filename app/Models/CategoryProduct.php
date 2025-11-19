<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CategoryProduct extends Model
{
    use HasFactory;

    protected $table = 'category_products';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

    protected $fillable = [
        'nom',
        'actif',
        'pays',
        'backoffice_id',
        'prix_kg',
    ];

    protected $casts = [
        'prix_kg' => 'decimal:2',
        'actif' => 'boolean',
    ];

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class, 'category_id');
    }

    public function tarifs(): HasMany
    {
        return $this->hasMany(TarifGroupage::class, 'category_id');
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
