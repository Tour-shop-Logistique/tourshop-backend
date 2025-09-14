<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TarifAgence extends Model
{
    use HasFactory;

    protected $table = 'tarifs_agence';

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
            // Copier l'indice du tarif de base
            if ($model->tarif_base_id && !$model->indice) {
                $tarifBase = TarifBase::find($model->tarif_base_id);
                if ($tarifBase) {
                    $model->indice = $tarifBase->indice;
                }
            }

            if ($model->prix_zones) {
                $prixZones = $model->prix_zones;
                foreach ($prixZones as &$zone) {
                    if (isset($zone['pourcentage_prestation'])) {
                        // Récupérer le montant_expedition_base du tarif de base pour cette zone
                        if ($model->tarif_base_id) {
                            $tarifBase = $tarifBase ?? TarifBase::find($model->tarif_base_id);
                            if ($tarifBase) {
                                $zoneBase = $tarifBase->getPrixPourZone($zone['zone_destination_id']);
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
        'tarif_base_id',
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
     * Un tarif d'agence est basé sur un tarif de base
     */
    public function tarifBase(): BelongsTo
    {
        return $this->belongsTo(TarifBase::class, 'tarif_base_id');
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
    public function scopePourCriteres($query, $agenceId, $zoneDestination, $modeExpedition, $indiceTrancheArrondi, $typeColis = null)
    {
        return $query->where('agence_id', $agenceId)
            ->where('actif', true)
            ->whereHas('tarifBase', function ($subQuery) use ($zoneDestination, $modeExpedition, $indiceTrancheArrondi, $typeColis) {
                $subQuery->where('indice', $indiceTrancheArrondi)
                    ->where('mode_expedition', $modeExpedition)
                    ->where('actif', true);

                // Le type de colis n'influe que sur le mode groupage
                if ($modeExpedition === 'groupage' && $typeColis) {
                    $subQuery->where('type_colis', $typeColis);
                } elseif ($modeExpedition === 'simple') {
                    $subQuery->whereNull('type_colis');
                }

                // Vérifier que le tarif de base contient la zone demandée
                $subQuery->whereJsonContains('prix_zones', function ($zone) use ($zoneDestination) {
                    return $zone['zone_destination_id'] === $zoneDestination;
                });
            });
    }

    /**
     * Scope pour les tarifs actifs
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
