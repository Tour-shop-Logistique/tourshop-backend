<?php

namespace App\Models;

use App\Models\User;
use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\ModeExpedition;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expedition extends Model
{
    use HasFactory;

    protected $table = 'expeditions';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'agence_id',
        'client_id',
        'reference',

        // Expediteur
        'zone_depart_id',
        'pays_depart',
        'expediteur_id',
        'expediteur_nom',
        'expediteur_telephone',
        'expediteur_adresse',

        // Destinataire
        'zone_destination_id',
        'pays_destination',
        'destinataire_id',
        'destinataire_nom',
        'destinataire_telephone',
        'destinataire_adresse',

        // Mode d'expédition
        'mode_expedition',
        'articles',
        'photos_articles',
        'poids_total',
        'volume_total',

        // Montant
        'montant_base',
        'pourcentage_prestation',
        'montant_prestation',
        'montant_expedition',

        // Enlevement Domicile
        'enlevement_domicile',
        'coord_enlevement',
        'instructions_enlevement',
        'distance_domicile_agence',
        'frais_enlevement_domicile',

        // Livraison Domicile
        'livraison_domicile',
        'coord_livraison',
        'instructions_livraison',
        'frais_livraison_domicile',

        // Frais supplementaires
        'frais_emballage',
        'delai_retrait',
        'frais_retard_retrait',

        'paiement_credit',
        'statut',
        'statut_paiement',

        // Dates
        'date_enlevement',
        'date_expedition',
        'date_livraison',
        'date_livraison_reelle',

        // Propriétés fusionnées de Colis
        'code_suivi',

        'valeur_declaree',

        'livreur_id',
        'livraison_express',
        'photo_livraison',
        'signature_destinataire',
        'date_livraison',
        'commission_livreur',
        'commission_agence',

    ];

    protected $casts = [
        'mode_expedition' => ModeExpedition::class,
        'articles' => 'array',
        'poids_total' => 'decimal:2',
        'volume_total' => 'decimal:2',

        'montant_base' => 'decimal:2',
        'pourcentage_prestation' => 'decimal:2',
        'montant_prestation' => 'decimal:2',
        'montant_expedition' => 'decimal:2',

        'enlevement_domicile' => 'boolean',
        'livraison_domicile' => 'boolean',
        'paiement_credit' => 'boolean',

        'date_expedition' => 'datetime',
        'date_livraison_prevue' => 'datetime',
        'date_livraison_reelle' => 'datetime',
        // Casts des propriétés fusionnées de Colis
        'valeur_declaree' => 'decimal:2',
        'commission_livreur' => 'decimal:2',
        'commission_agence' => 'decimal:2',

        'livraison_express' => 'boolean',
        'date_livraison' => 'datetime'
    ];

    // Statuts possibles
    const STATUT_EN_ATTENTE = 'en_attente';
    const STATUT_ACCEPTED = 'accepted';
    const STATUT_REFUSED = 'refused';
    const STATUT_IN_PROGRESS = 'in_progress';
    const STATUT_SHIPPED = 'shipped';
    const STATUT_DELIVERED = 'delivered';
    const STATUT_CANCELLED = 'cancelled';

    // Statuts de paiement
    const PAIEMENT_EN_ATTENTE = 'en_attente';
    const PAIEMENT_PAYE = 'paye';
    const PAIEMENT_PARTIEL = 'partiel';
    const PAIEMENT_REMBOURSE = 'rembourse';

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }

    public function livreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_id');
    }

    public function zoneDepart(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_depart_id');
    }

    public function zoneDestination(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_destination_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(ExpeditionArticle::class, 'expedition_id');
    }

    // Méthodes de calcul des totaux à partir des articles
    public function getPoidsTotalAttribute(): float
    {
        return $this->articles->sum('poids_total');
    }

    public function getVolumeTotalAttribute(): float
    {
        return $this->articles->sum('volume_total');
    }

    public function getLongueurTotaleAttribute(): float
    {
        return $this->articles->max('longueur') ?? 0;
    }

    public function getLargeurTotaleAttribute(): float
    {
        return $this->articles->max('largeur') ?? 0;
    }

    public function getHauteurTotaleAttribute(): float
    {
        return $this->articles->max('hauteur') ?? 0;
    }

    public function getValeurTotaleDeclareeAttribute(): float
    {
        return $this->articles->sum('valeur_declaree');
    }

    // Méthode pour recalculer les totaux depuis les articles
    public function recalculerTotaux(): void
    {
        $this->poids = $this->poids_total;
        $this->volume = $this->volume_total;

        // Pour le mode simple, on utilise les dimensions maximales
        if ($this->mode_expedition === 'simple') {
            $this->longueur = $this->longueur_totale;
            $this->largeur = $this->largeur_totale;
            $this->hauteur = $this->hauteur_totale;
        }

        $this->save();
    }


    // Scopes pour filtrer par statut
    public function scopeEnAttente($query)
    {
        return $query->where('statut', self::STATUT_EN_ATTENTE);
    }

    public function scopeAccepted($query)
    {
        return $query->where('statut', self::STATUT_ACCEPTED);
    }

    public function scopeRefused($query)
    {
        return $query->where('statut', self::STATUT_REFUSED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('statut', self::STATUT_IN_PROGRESS);
    }

    public function scopeShipped($query)
    {
        return $query->where('statut', self::STATUT_SHIPPED);
    }

    public function scopeDelivered($query)
    {
        return $query->where('statut', self::STATUT_DELIVERED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('statut', self::STATUT_CANCELLED);
    }

    // Scope pour les expéditions d'une agence
    public function scopePourAgence($query, $agenceId)
    {
        return $query->where('agence_id', $agenceId);
    }

    // Scope pour les expéditions d'un client
    public function scopePourClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    // Méthode pour générer une référence unique
    public static function genererReference(): string
    {
        $prefix = 'EXP';
        $timestamp = date('YmdHis');
        $random = mt_rand(1000, 9999);
        return $prefix . $timestamp . $random;
    }

    // Méthode pour générer un code de suivi unique (héritée de Colis)
    public static function genererCodeSuivi(): string
    {
        return 'TS' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Méthodes utilitaires pour gérer les destinataires (héritées de Colis)
    public function isDestinataireUser(): bool
    {
        return !is_null($this->destinataire_id);
    }

    // Accesseur pour récupérer automatiquement le nom du destinataire
    public function getDestinataireNomAttribute($value): string
    {
        if ($this->isDestinataireUser()) {
            return $this->destinataire->name ?? 'Utilisateur inconnu';
        }
        return $value ?? 'Destinataire inconnu';
    }

    // Accesseur pour récupérer automatiquement le téléphone du destinataire
    public function getDestinataireTelephoneAttribute($value): string
    {
        if ($this->isDestinataireUser()) {
            return $this->destinataire->telephone ?? 'Téléphone non disponible';
        }
        return $value ?? 'Téléphone non disponible';
    }

    // Méthode pour calculer le volume
    public function calculerVolume(): void
    {
        if ($this->longueur && $this->largeur && $this->hauteur) {
            // Calculer le volume en m³ et s'assurer que c'est un nombre décimal
            $volume = (float) ($this->longueur * $this->largeur * $this->hauteur) / 1000000.0;
            $this->volume = round($volume, 6);
        }
    }

    // Méthode pour vérifier si l'expédition peut être acceptée
    public function peutEtreAcceptee(): bool
    {
        return in_array($this->statut, [self::STATUT_EN_ATTENTE]);
    }

    // Méthode pour vérifier si l'expédition peut être refusée
    public function peutEtreRefusee(): bool
    {
        return in_array($this->statut, [self::STATUT_EN_ATTENTE]);
    }

    // Méthode pour vérifier si l'expédition peut être mise en cours
    public function peutEtreEnCours(): bool
    {
        return in_array($this->statut, [self::STATUT_ACCEPTED]);
    }

    // Méthode pour vérifier si l'expédition peut être expédiée
    public function peutEtreExpediee(): bool
    {
        return in_array($this->statut, [self::STATUT_IN_PROGRESS]);
    }

    // Méthode pour vérifier si l'expédition peut être livrée
    public function peutEtreLivree(): bool
    {
        return in_array($this->statut, [self::STATUT_SHIPPED]);
    }

    // Méthode pour vérifier si l'expédition peut être annulée
    public function peutEtreAnnulee(): bool
    {
        return in_array($this->statut, [self::STATUT_EN_ATTENTE, self::STATUT_ACCEPTED]);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($expedition) {
            if (empty($expedition->id)) {
                $expedition->id = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($expedition->reference)) {
                $expedition->reference = self::genererReference();
            }
            if (empty($expedition->code_suivi)) {
                $expedition->code_suivi = self::genererCodeSuivi();
            }
            if (empty($expedition->statut)) {
                $expedition->statut = self::STATUT_EN_ATTENTE;
            }
            if (empty($expedition->statut_paiement)) {
                $expedition->statut_paiement = self::PAIEMENT_EN_ATTENTE;
            }
            // Calculer le volume automatiquement si les dimensions sont fournies
            $expedition->calculerVolume();
        });

        static::updating(function ($expedition) {
            // Recalculer le volume si les dimensions changent
            if ($expedition->isDirty(['longueur', 'largeur', 'hauteur'])) {
                $expedition->calculerVolume();
            }
        });
    }
}
