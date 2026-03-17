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
        'agence_destination_id',
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
        'controlled_at',
        'is_received_by_backoffice',
        'received_at_backoffice',
        'is_received_by_agence_destination',
        'received_at_agence_destination',
        'is_received_by_agence_depart',
        'received_at_agence_depart',
        'is_expedie_vers_entrepot',
        'expedie_vers_entrepot_at',
        'is_collected_by_client',
        'collected_at',
        'code_validation_retrait',
        'code_validation_retrait_expires_at',
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
        'controlled_at' => 'datetime',
        'is_received_by_backoffice' => 'boolean',
        'received_at_backoffice' => 'datetime',
        'is_received_by_agence_destination' => 'boolean',
        'received_at_agence_destination' => 'datetime',
        'is_received_by_agence_depart' => 'boolean',
        'received_at_agence_depart' => 'datetime',
        'is_expedie_vers_entrepot' => 'boolean',
        'expedie_vers_entrepot_at' => 'datetime',
        'is_collected_by_client' => 'boolean',
        'collected_at' => 'datetime',
        'code_validation_retrait_expires_at' => 'datetime',
    ];

    public function expedition(): BelongsTo
    {
        return $this->belongsTo(Expedition::class, 'expedition_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryProduct::class, 'category_id');
    }

    public function agenceDestination(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_destination_id');
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
