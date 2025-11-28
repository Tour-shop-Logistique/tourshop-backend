<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TarifAgenceSimple extends Model
{
    use HasFactory;

    protected $table = 'tarifs_agence_simple';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });

        // Calculer automatiquement les montants pour chaque zone lors de la sauvegarde
        static::saving(function ($model) {
            // Copier l'indice du tarif simple
            if ($model->tarif_simple_id && !$model->indice) {
                $tarifSimple = TarifSimple::find($model->tarif_simple_id);
                if ($tarifSimple) {
                    $model->indice = $tarifSimple->indice;
                }
            }

            if ($model->prix_zones) {
                $prixZones = $model->prix_zones;
                foreach ($prixZones as &$zone) {
                    if (isset($zone['pourcentage_prestation'])) {
                        // Récupérer le montant_expedition_base du tarif simple pour cette zone
                        if ($model->tarif_simple_id) {
                            $tarifSimple = $tarifSimple ?? TarifSimple::find($model->tarif_simple_id);
                            if ($tarifSimple) {
                                $zoneBase = $tarifSimple->getPrixPourZone($zone['zone_destination_id']);
                                if ($zoneBase) {
                                    $zone['montant_base'] = $zoneBase['montant_base'];
                                    $zone['montant_prestation'] = round(($zoneBase['montant_base'] * $zone['pourcentage_prestation']) / 100, 2, PHP_ROUND_HALF_UP);
                                    $zone['montant_expedition'] = round($zoneBase['montant_base'] + $zone['montant_prestation'], 2, PHP_ROUND_HALF_UP);
                                }
                            }
                        }
                    }
                }
                $model->prix_zones = $prixZones;
            }
        });
    }

    /**
     * Les attributs qui peuvent être massivement assignés
     */
    protected $fillable = [
        'agence_id',
        'tarif_simple_id',
        'indice',
        'prix_zones',
        'actif',
    ];

    /**
     * Les attributs qui doivent être castés
     */
    protected $casts = [
        'indice' => 'decimal:1',
        'prix_zones' => 'array',
        'actif' => 'boolean',
    ];

    /**
     * Un tarif d'agence appartient à une agence
     */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    /**
     * Un tarif d'agence est basé sur un tarif simple
     */
    public function tarifSimple(): BelongsTo
    {
        return $this->belongsTo(TarifSimple::class, 'tarif_simple_id');
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
     * Scope pour rechercher par critères d'agence
     */
    public function scopePourCriteres($query, $agenceId, $zoneDestination, $modeExpedition, $indiceTrancheArrondi)
    {
        return $query->where('agence_id', $agenceId)
            ->where('actif', true)
            ->whereHas('tarifSimple', function ($subQuery) use ($zoneDestination, $modeExpedition, $indiceTrancheArrondi) {
                $subQuery->where('indice', $indiceTrancheArrondi)
                    ->where('mode_expedition', $modeExpedition)
                    ->where('actif', true);

                // Vérifier que le tarif simple contient la zone demandée
                $subQuery->whereJsonContains('prix_zones', function ($zone) use ($zoneDestination) {
                    return $zone['zone_destination_id'] === $zoneDestination;
                });
            });
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
