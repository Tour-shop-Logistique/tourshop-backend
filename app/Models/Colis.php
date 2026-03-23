<?php

namespace App\Models;

use App\Models\Agence;
use App\Models\CategoryProduct;
use App\Models\Expedition;
use App\Enums\StatutPaiement;
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
    protected $appends = ['potential_frais_retard', 'total_a_payer_client'];
    protected $hidden = ['potential_frais_retard', 'total_a_payer_client'];

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
        'date_limite_retrait',
        'is_retard_retrait',
        'frais_retard_retrait',
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
        'date_limite_retrait' => 'datetime',
        'is_retard_retrait' => 'boolean',
        'frais_retard_retrait' => 'decimal:2',
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

    /**
     * Calculer en temps réel les frais de retard potentiels si le colis n'est pas encore collecté.
     */
    public function getPotentialFraisRetardAttribute(): float
    {
        if ($this->is_collected_by_client) {
            return (float) $this->frais_retard_retrait;
        }

        if (!$this->date_limite_retrait) {
            return 0;
        }

        $now = now();
        if ($now->isAfter($this->date_limite_retrait)) {
            $daysLate = $now->diffInDays($this->date_limite_retrait) + 1;
            return (float) ($daysLate * 500);
        }

        return 0;
    }

    /**
     * Calculer le montant total que le client doit payer (frais de retard inclus).
     * On prend en compte si le montant de l'expédition a déjà été réglé.
     */
    public function getTotalAPayerClientAttribute(): float
    {
        $shippingCost = (float) $this->montant_colis_total;

        // Si l'expédition liée est déjà payée, le client ne doit plus payer le prix du colis,
        // mais doit quand même payer les frais de retard s'il y en a.
        if ($this->expedition && $this->expedition->statut_paiement === StatutPaiement::PAYE) {
            $shippingCost = 0;
        }

        return (float) ($shippingCost + $this->potential_frais_retard);
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
