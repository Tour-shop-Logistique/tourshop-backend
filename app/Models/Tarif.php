<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Tarif extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';  // Spécifie le nom de la clé primaire
    protected $keyType = 'string';  // Indique que la clé primaire est une chaîne de caractères
    public $incrementing = false;  // Désactive l'auto-incrémentation

    /**
     * Méthode utilisée pour générer un UUID avant la création d'un nouveau tarif.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

    /**
     * Les attributs qui peuvent être massivement assignés.
     */
    protected $fillable = [
        'agence_id',
        'nom',
        'type_colis',
        'prix_base',
        'prix_par_km',
        'prix_par_kg',
        'poids_max_kg',
        'distance_min_km',
        'distance_max_km',
        'supplement_domicile',
        'supplement_express',
        'actif',
    ];

    /**
     * Les attributs qui doivent être castés en types spécifiques.
     */
    protected $casts = [
        'prix_base' => 'decimal:2',
        'prix_par_km' => 'decimal:2',
        'prix_par_kg' => 'decimal:2',
        'poids_max_kg' => 'decimal:2',
        'supplement_domicile' => 'decimal:2',
        'supplement_express' => 'decimal:2',
        'actif' => 'boolean',
    ];

    /**
     * Un tarif appartient à une seule agence.
     */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }
}