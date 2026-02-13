<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Colis extends Model
{
    use HasFactory;

    protected $table = 'colis';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code_colis',
        'expedition_id',
        'category_id',
        'designation',
        'articles',
        'photo',
        'poids',
        'longueur',
        'largeur',
        'hauteur',
        'volume',
        'prix_emballage',
        'prix_unitaire',
        'montant_colis_base',
        'pourcentage_prestation',
        'montant_colis_prestation',
        'montant_colis_total',
        'is_controlled',
    ];

    protected $casts = [
        'articles' => 'array',
        'poids' => 'decimal:2',
        'longueur' => 'decimal:2',
        'largeur' => 'decimal:2',
        'hauteur' => 'decimal:2',
        'volume' => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
        'prix_emballage' => 'decimal:2',
        'montant_colis_base' => 'decimal:2',
        'pourcentage_prestation' => 'decimal:2',
        'montant_colis_prestation' => 'decimal:2',
        'montant_colis_total' => 'decimal:2',
        'is_controlled' => 'boolean',
    ];

    public function expedition(): BelongsTo
    {
        return $this->belongsTo(Expedition::class, 'expedition_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryProduct::class, 'category_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($colis) {
            $colis->id = (string) Str::uuid();

        });

        static::saving(function ($colis) {
            // Calculer automatiquement le volume si les dimensions sont fournies
            if ($colis->longueur && $colis->largeur && $colis->hauteur) {
                $colis->volume = round($colis->longueur * $colis->largeur * $colis->hauteur, 2);
            }

        });
    }
}
