<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\ModeExpedition;
use App\Enums\ExpeditionStatus;
use Illuminate\Support\Str;
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
        'livreur_enlevement_id',
        'livreur_deplacement_id',
        'livreur_livraison_id',
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
        'adresse_postale_destination',
        'destinataire_id',
        'destinataire_nom',
        'destinataire_telephone',
        'destinataire_adresse',

        // Mode d'expédition
        'mode_expedition',
        'articles', // [{description, photo, longueur, largeur, hauteur, volume, poids, quantite, category_id, produit_id}]
        'poids_total',
        'volume_total',

        // Montant
        'montant_base', // pour backoffice
        'pourcentage_prestation',
        'montant_prestation', // pour agence
        'montant_expedition',

        // Frais
        'frais_enlevement_domicile', // pour livreur et agence
        'frais_livraison_domicile', // pour livreur et agence
        'frais_emballage', // pour agence
        'frais_enlevement_agence', // pour backoffice
        'frais_retard_retrait', // pour agence et backoffice

        // Enlevement Domicile
        'is_enlevement_domicile',
        'coord_enlevement',
        'instructions_enlevement',
        'distance_domicile_agence', // distance en km

        // Livraison Domicile
        'is_livraison_domicile',
        'coord_livraison',
        'instructions_livraison',

        'delai_retrait',
        'is_retard_retrait',
        'is_paiement_credit',
        'statut_expedition',
        'statut_paiement',

        // Dates [{}]
        'date_prevue_enlevement', // Date prevue pour l'enlevement du colis
        'date_enlevement_client', // Date d'enlevement du colis par le livreur
        'date_livraison_agence', // Date de reception par l'agence du colis enlevé 
        'date_deplacement_entrepot', // Date du deplacement du colis de l'agence au lieu d'expédition 
        'date_expedition_depart', // Date d'expedition du colis à l'étranger
        'date_expedition_arrivee', // Date d'arrivée du colis de l'étranger
        'date_reception_agence', // Date de reception par l'agence du colis expédié
        'date_limite_retrait', // Date limite pour le retrait de colis par le client
        'date_reception_client', // Date de reception du colis par le client
        'date_annulation', // Date d'annulation de l'expedition 

        'motif_annulation',
        'code_suivi_expedition',
        'code_validation_reception',

        // Commissions [{}]
        'commission_livreur_enlevement', // 85% sur les frais d'enlevement à domicile
        'commission_agence_enlevement', // 15% sur les frais d'enlevement à domicile
        'commission_livreur_livraison', // 90% sur les frais de livraison à domicile
        'commission_agence_livraison', // 10% sur les frais de livraison à domicile
        'commission_agence_retard', // 40% sur les frais de retard de retrait
        'commission_tourshop_retard', // 60% sur les frais de retard de retrait
    ];

    protected $casts = [
        'mode_expedition' => ModeExpedition::class,
        'articles' => 'array',
        'photos_articles' => 'array',
        'poids_total' => 'decimal:2',
        'volume_total' => 'decimal:2',

        'montant_base' => 'decimal:2',
        'pourcentage_prestation' => 'decimal:2',
        'montant_prestation' => 'decimal:2',
        'montant_expedition' => 'decimal:2',

        'is_enlevement_domicile' => 'boolean',
        'is_livraison_domicile' => 'boolean',
        'is_paiement_credit' => 'boolean',

        // Dates
        'date_enlevement' => 'datetime',
        'date_expedition' => 'datetime',
        'date_livraison_prevue' => 'datetime',
        'date_livraison_reelle' => 'datetime',

        // Casts des propriétés fusionnées de Colis
        'valeur_declaree' => 'decimal:2',
        'commission_livreur_enlevement' => 'decimal:2',
        'commission_livreur_livraison' => 'decimal:2',
        'commission_agence' => 'decimal:2',

        'livraison_express' => 'boolean',
        'date_livraison' => 'datetime',

        'statut_expedition' => ExpeditionStatus::class,
        'statut_paiement' => ExpeditionStatus::class,
    ];

    // Statuts de paiement
    const PAIEMENT_EN_ATTENTE = 'en_attente';
    const PAIEMENT_PAYE = 'paye';
    const PAIEMENT_PARTIEL = 'partiel';
    const PAIEMENT_REMBOURSE = 'rembourse';

    public function produits()
    {
        return $this->hasMany(ExpeditionArticle::class);
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id', 'user_id');
    }

    public function expediteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expediteur_id', 'user_id');
    }
    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id', 'user_id');
    }

    public function livreurEnlevement(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_enlevement_id', 'user_id');
    }

    public function livreurDeplacement(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_deplacement_id', 'user_id');
    }

    public function livreurLivraison(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_livraison_id', 'user_id');
    }

    public function zoneDepart(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_depart_id', 'zone_id');
    }

    public function zoneDestination(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_destination_id', 'zone_id');
    }


    // Méthodes de calcul des totaux à partir du tableau JSON articles
    public function getPoidsTotal(): float
    {
        $articles = $this->attributes['articles'] ?? $this->articles ?? [];
        if (is_string($articles)) {
            $articles = json_decode($articles, true) ?? [];
        }
        return (float) array_sum(array_column($articles, 'poids'));
    }

    public function getVolumeTotal(): float
    {
        $articles = $this->attributes['articles'] ?? $this->articles ?? [];
        if (is_string($articles)) {
            $articles = json_decode($articles, true) ?? [];
        }
        return (float) array_sum(array_column($articles, 'volume'));
    }


    // Scopes pour filtrer par statut
    public function scopeEnAttente($query)
    {
        return $query->where('statut_expedition', ExpeditionStatus::EN_ATTENTE);
    }

    public function scopeAccepted($query)
    {
        return $query->where('statut_expedition', ExpeditionStatus::ACCEPTED);
    }

    public function scopeRefused($query)
    {
        return $query->where('statut_expedition', ExpeditionStatus::REFUSED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('statut_expedition', ExpeditionStatus::IN_PROGRESS);
    }

    public function scopeShipped($query)
    {
        return $query->where('statut_expedition', ExpeditionStatus::SHIPPED);
    }

    public function scopeDelivered($query)
    {
        return $query->where('statut_expedition', ExpeditionStatus::DELIVERED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('statut_expedition', ExpeditionStatus::CANCELLED);
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

    // Scope pour les expéditions d'un destinataire
    public function scopePourDestinataire($query, $destinataireId)
    {
        return $query->where('destinataire_id', $destinataireId);
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

    // Méthode pour vérifier si l'expédition peut être acceptée
    public function peutEtreAcceptee(): bool
    {
        return in_array($this->statut_expedition, [ExpeditionStatus::EN_ATTENTE]);
    }

    // Méthode pour vérifier si l'expédition peut être refusée
    public function peutEtreRefusee(): bool
    {
        return in_array($this->statut_expedition, [ExpeditionStatus::EN_ATTENTE]);
    }

    // Méthode pour vérifier si l'expédition peut être mise en cours
    public function peutEtreEnCours(): bool
    {
        return in_array($this->statut_expedition, [ExpeditionStatus::ACCEPTED]);
    }

    // Méthode pour vérifier si l'expédition peut être expédiée
    public function peutEtreExpediee(): bool
    {
        return in_array($this->statut_expedition, [ExpeditionStatus::IN_PROGRESS]);
    }

    // Méthode pour vérifier si l'expédition peut être livrée
    public function peutEtreLivree(): bool
    {
        return in_array($this->statut_expedition, [ExpeditionStatus::SHIPPED]);
    }

    // Méthode pour vérifier si l'expédition peut être annulée
    public function peutEtreAnnulee(): bool
    {
        return in_array($this->statut_expedition, [ExpeditionStatus::EN_ATTENTE, ExpeditionStatus::ACCEPTED]);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($expedition) {

            $expedition->{$expedition->getKeyName()} = (string) Str::uuid();
            $expedition->code_validation_reception = strtoupper(Str::random(6));

            if (empty($expedition->reference)) {
                $expedition->reference = self::genererReference();
            }

            // if (empty($expedition->code_suivi)) {
            //     $expedition->code_suivi = self::genererCodeSuivi();
            // }

            if (empty($expedition->statut_expedition)) {
                $expedition->statut_expedition = ExpeditionStatus::EN_ATTENTE;
            }

            if (empty($expedition->statut_paiement)) {
                $expedition->statut_paiement = self::PAIEMENT_EN_ATTENTE;
            }
        });

        static::saving(function ($expedition) {

            if ($expedition->articles) {
                $articles = $expedition->articles;
                foreach ($articles as &$article) {
                    if (isset($article['longueur']) && isset($article['largeur']) && isset($article['hauteur'])) {
                        $volume = (float) ($article['longueur'] * $article['largeur'] * $article['hauteur']);
                        $article['volume'] = round($volume, 2, PHP_ROUND_HALF_UP);
                    }
                }
                $expedition->articles = $articles;
                $expedition->poids_total = self::getPoidsTotal();
                $expedition->volume_total = self::getVolumeTotal();

            }
        });


    }
}
