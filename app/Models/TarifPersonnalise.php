<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TarifPersonnalise extends Model
{
    use HasFactory;

    protected $table = 'tarifs_personnalises';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Génère un UUID et un code unique avant la création
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();

            // Générer un code unique
            do {
                $code = 'TP-' . strtoupper(Str::random(6));
            } while (self::where('code', $code)->exists());

            $model->code = $code;

            // Les prix_zones sont fournis directement lors de la création
        });

        // Les prix_zones sont gérés manuellement lors des mises à jour
    }

    /**
     * Les attributs qui peuvent être massivement assignés
     */
    protected $fillable = [
        'tarif_simple_id',
        'prix_zones',
        'actif',
    ];

    /**
     * Les attributs qui doivent être castés
     */
    protected $casts = [
        'prix_zones' => 'array',
        'actif' => 'boolean',
    ];

    /**
     * Un tarif personnalisé appartient à un tarif simple
     */
    public function tarifSimple(): BelongsTo
    {
        return $this->belongsTo(TarifSimple::class, 'tarif_simple_id');
    }

    /**
     * Convertir le modèle en array sans les relations chargées automatiquement
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Supprimer les relations si elles sont chargées
        unset($array['tarif_simple']);

        return $array;
    }


    /**
     * Obtenir le prix pour une zone spécifique
     */
    public function getPrixPourZone($zoneDestinationId)
    {
        if (!$this->prix_zones) {
            return null;
        }

        foreach ($this->prix_zones as $zone) {
            if ($zone['zone_destination_id'] === $zoneDestinationId) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Scope pour les tarifs actifs
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope pour rechercher par code
     */
    public function scopeParCode($query, $code)
    {
        return $query->where('code', $code);
    }
}
