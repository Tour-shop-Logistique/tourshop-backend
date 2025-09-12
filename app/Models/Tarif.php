<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Enums\ModeExpedition;
use App\Enums\TypeColis;
use App\Models\Zone;

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
        'mode_expedition',
        'type_colis',
        'zone_depart_id',
        'zone_arrivee_id',
        'prix_base',
        'montant_base',
        'pourcentage_prestation',
        'prix_entrepot',
        'supplement_domicile_groupage',
        'prix_par_km',
        'prix_par_kg',
        'poids_max_kg',
        'poids_min_kg',
        'longueur_max_cm',
        'largeur_max_cm',
        'hauteur_max_cm',
        'indice_tranche',
        'facteur_division_volume',
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
        'mode_expedition' => ModeExpedition::class,
        'type_colis' => TypeColis::class,
        'prix_base' => 'decimal:2',
        'montant_base' => 'decimal:2',
        'pourcentage_prestation' => 'decimal:2',
        'prix_entrepot' => 'decimal:2',
        'supplement_domicile_groupage' => 'decimal:2',
        'prix_par_km' => 'decimal:2',
        'prix_par_kg' => 'decimal:2',
        'poids_max_kg' => 'decimal:2',
        'poids_min_kg' => 'decimal:2',
        'longueur_max_cm' => 'decimal:2',
        'largeur_max_cm' => 'decimal:2',
        'hauteur_max_cm' => 'decimal:2',
        'indice_tranche' => 'decimal:1',
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

    /**
     * Un tarif appartient à une zone de départ.
     */
    public function zoneDepart(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_depart_id');
    }

    /**
     * Un tarif appartient à une zone d'arrivée.
     */
    public function zoneArrivee(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_arrivee_id');
    }
}
