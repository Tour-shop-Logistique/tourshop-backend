<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Colis extends Model
{
    use HasFactory;

    protected $table = 'colis';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code_colis',
        'expedition_id',
        'designation',
        'photo',
        'poids',
        'longueur',
        'largeur',
        'hauteur',
        'volume',
        'prix_unitaire',
        'prix_emballage',
        'prix_total',
    ];

    protected $casts = [
        'poids' => 'decimal:2',
        'longueur' => 'decimal:2',
        'largeur' => 'decimal:2',
        'hauteur' => 'decimal:2',
        'volume' => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
        'prix_emballage' => 'decimal:2',
        'prix_total' => 'decimal:2',
    ];

    public function expedition(): BelongsTo
    {
        return $this->belongsTo(Expedition::class, 'expedition_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(ColisArticle::class, 'colis_id');
    }

    // Calcul du poids total pour ce colis (somme des poids des articles)
    public function getPoidsTotalAttribute(): float
    {
        return (float) $this->poids * ($this->quantite ?? 1);
    }

    // Calcul du volume total pour ce colis
    public function getVolumeTotalAttribute(): float
    {
        return $this->volume * ($this->quantite ?? 1);
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

            if ($colis->prix_unitaire && $colis->poids) {
                $colis->prix_total = ($colis->prix_unitaire * $colis->poids) + $colis->prix_emballage;
            }
        });
    }
}
