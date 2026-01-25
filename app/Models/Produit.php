<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Produit extends Model
{
    use HasFactory;

    protected $table = 'produits';

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
        'category_id',
        'designation',
        'reference',
        'backoffice_id',
        'actif',
    ];

    protected $casts = [
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
