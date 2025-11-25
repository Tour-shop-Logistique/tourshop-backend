<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpeditionArticle extends Model
{
    use HasFactory;

    protected $table = 'expedition_articles';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'expedition_id',
        'produit_id',
        'designation',
        'reference',
        'poids',
        'longueur',
        'largeur',
        'hauteur',
        'quantite',
        'valeur_declaree',
        'description',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'poids' => 'decimal:2',
        'longueur' => 'decimal:2',
        'largeur' => 'decimal:2',
        'hauteur' => 'decimal:2',
        'quantite' => 'integer',
        'valeur_declaree' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function expedition(): BelongsTo
    {
        return $this->belongsTo(Expedition::class, 'expedition_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    // Calcul du volume de cet article
    public function getVolumeAttribute(): float
    {
        if ($this->longueur && $this->largeur && $this->hauteur) {
            return round(($this->longueur * $this->largeur * $this->hauteur) / 1000000, 6); // m³
        }
        return 0;
    }

    // Calcul du volume total pour cet article (quantité × volume unitaire)
    public function getVolumeTotalAttribute(): float
    {
        return $this->volume * $this->quantite;
    }

    // Calcul du poids total pour cet article
    public function getPoidsTotalAttribute(): float
    {
        return (float) $this->poids * $this->quantite;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($article) {
            if (empty($article->id)) {
                $article->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
