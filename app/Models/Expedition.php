<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\TypeExpedition;
use App\Enums\ExpeditionStatus;
use App\Enums\StatutPaiement;
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
        'agence_id',
        'user_id',
        'livreur_enlevement_id',
        'livreur_deplacement_id',
        'livreur_livraison_id',
        'reference',
        'is_demande_client',

        // Expediteur
        'pays_depart',
        'expediteur', // JSON

        // Destinataire
        'pays_destination',
        'destinataire', // JSON

        // Mode d'expédition
        'type_expedition', // TypeExpedition::class

        // Montant
        'montant_base', // pour backoffice
        'pourcentage_prestation', // pour agence
        'montant_prestation', // pour agence
        'montant_expedition', // montant de l'expedition

        // Frais
        'frais_enlevement_domicile', // pour livreur et agence
        'frais_livraison_domicile', // pour livreur et agence
        'frais_emballage', // pour agence
        'frais_retard_retrait', // pour agence et backoffice
        'frais_annexes', // pour client

        // Enlevement Domicile
        'is_enlevement_domicile',
        'coord_enlevement', // coordinates
        'instructions_enlevement',
        'distance_domicile_agence', // distance en km

        // Livraison Domicile
        'is_livraison_domicile',
        'coord_livraison', // coordinates
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
        'date_limite_retrait', // Date limite pour le retrait de colis par le client (72h apres reception en agence)
        'date_reception_client', // Date de reception du colis par le client
        'date_annulation', // Date d'annulation de l'expedition 

        'motif_annulation',
        'code_suivi_expedition',

        // Commissions [{}]
        'commission_livreur_enlevement', // 85% sur les frais d'enlevement à domicile
        'commission_agence_enlevement', // 15% sur les frais d'enlevement à domicile
        'commission_emballage_agence', // 15% sur les frais d'emballage
        'commission_emballage_backoffice', // 85% sur les frais d'emballage
        'commission_livreur_livraison', // 90% sur les frais de livraison à domicile
        'commission_agence_livraison', // 10% sur les frais de livraison à domicile
        'commission_agence_retard', // 40% sur les frais de retard de retrait
        'commission_tourshop_retard', // 60% sur les frais de retard de retrait
    ];

    protected $casts = [
        // Mode et type
        'type_expedition' => TypeExpedition::class,

        // Montants
        'montant_base' => 'decimal:2',
        'pourcentage_prestation' => 'decimal:2',
        'montant_prestation' => 'decimal:2',
        'montant_expedition' => 'decimal:2',

        // Frais
        'frais_enlevement_domicile' => 'decimal:2',
        'frais_livraison_domicile' => 'decimal:2',
        'frais_emballage' => 'decimal:2',
        'frais_retard_retrait' => 'decimal:2',
        'frais_annexes' => 'decimal:2',

        // Booléens et Coordonnées
        'is_enlevement_domicile' => 'boolean',
        'distance_domicile_agence' => 'decimal:2',
        'is_livraison_domicile' => 'boolean',
        'is_retard_retrait' => 'boolean',
        'is_paiement_credit' => 'boolean',
        'is_demande_client' => 'boolean',

        // Statuts
        'statut_expedition' => ExpeditionStatus::class,
        'statut_paiement' => StatutPaiement::class,

        // Dates
        'date_prevue_enlevement' => 'datetime',
        'date_enlevement_client' => 'datetime',
        'date_livraison_agence' => 'datetime',
        'date_deplacement_entrepot' => 'datetime',
        'date_expedition_depart' => 'datetime',
        'date_expedition_arrivee' => 'datetime',
        'date_reception_agence' => 'datetime',
        'date_limite_retrait' => 'datetime',
        'date_reception_client' => 'datetime',
        'date_annulation' => 'datetime',

        // Commissions
        'commission_livreur_enlevement' => 'decimal:2',
        'commission_agence_enlevement' => 'decimal:2',
        'commission_emballage_agence' => 'decimal:2',
        'commission_emballage_backoffice' => 'decimal:2',
        'commission_livreur_livraison' => 'decimal:2',
        'commission_agence_livraison' => 'decimal:2',
        'commission_agence_retard' => 'decimal:2',
        'commission_tourshop_retard' => 'decimal:2',

        // Contacts JSON
        'expediteur' => 'array',
        'destinataire' => 'array',
    ];


    public function colis(): HasMany
    {
        return $this->hasMany(Colis::class, 'expedition_id');
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function livreurEnlevement(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_enlevement_id', 'id');
    }

    public function livreurDeplacement(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_deplacement_id', 'id');
    }

    public function livreurLivraison(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_livraison_id', 'id');
    }


    // Méthodes de calcul des totaux à partir de la relation colis
    public function getPoidsTotal(): float
    {
        return (float) $this->colis()->sum('poids');
    }

    public function getVolumeTotal(): float
    {
        return (float) $this->colis()->sum('volume');
    }

    public function getFraisEmballageTotal(): float
    {
        return (float) $this->colis()->sum('prix_emballage');
    }

    /**
     * Met à jour le statut de l'expédition selon la réception des colis.
     *
     * Processus (ordre chronologique) :
     * 1. Le backoffice indique qu'il a reçu les colis → tous is_received_by_backoffice = true
     *    → statut = ARRIVEE_EXPEDITION_SUCCES (les colis sont arrivés à destination pays).
     * 2. L'agence de destination réceptionne les colis → tous is_received_by_agence = true
     *    → statut = RECU_AGENCE_DESTINATION (disponible pour retrait client).
     *
     * On ne rétrograde jamais : si on est déjà RECU_AGENCE_DESTINATION, on ne repasse pas à ARRIVEE_EXPEDITION_SUCCES.
     */
    public function syncStatutFromColis(): bool
    {
        $colis = $this->colis()->get();
        if ($colis->isEmpty()) {
            return false;
        }

        $allReceivedByAgenceDestination = $colis->every(fn (Colis $c) => (bool) $c->is_received_by_agence_destination);
        $allReceivedByBackoffice = $colis->every(fn (Colis $c) => (bool) $c->is_received_by_backoffice);
        $allReceivedByAgenceDepart = $colis->every(fn (Colis $c) => (bool) $c->is_received_by_agence_depart);
        $allExpediesVersEntrepot = $colis->every(fn (Colis $c) => (bool) $c->is_expedie_vers_entrepot);
        $allCollectedByClient = $colis->every(fn (Colis $c) => (bool) $c->is_collected_by_client);

        // Statuts les plus avancés en premier (sans rétrograder)

        // Tous les colis récupérés par le client -> TERMINED
        if ($allCollectedByClient) {
            $this->update([
                'statut_expedition' => ExpeditionStatus::TERMINED,
                'date_reception_client' => $this->date_reception_client ?? now(),
            ]);
            return true;
        }

        // Tous les colis reçus par l'agence de destination → RECU_AGENCE_DESTINATION
        if ($allReceivedByAgenceDestination) {
            $this->update([
                'statut_expedition' => ExpeditionStatus::RECU_AGENCE_DESTINATION,
                'date_reception_agence' => $this->date_reception_agence ?? now(),
            ]);
            return true;
        }

        // Tous les colis reçus par le backoffice → ARRIVEE_EXPEDITION_SUCCES
        $statutsApresArrivee = [
            ExpeditionStatus::RECU_AGENCE_DESTINATION,
            ExpeditionStatus::EN_COURS_LIVRAISON,
            ExpeditionStatus::TERMINED,
        ];
        if ($allReceivedByBackoffice && !in_array($this->statut_expedition, $statutsApresArrivee, true)) {
            $this->update([
                'statut_expedition' => ExpeditionStatus::ARRIVEE_EXPEDITION_SUCCES,
                'date_expedition_arrivee' => $this->date_expedition_arrivee ?? now(),
            ]);
            return true;
        }

        // Tous les colis reçus par l'agence de départ (colis enlevés, arrivés en agence) → RECU_AGENCE_DEPART
        $statutsApresRecuDepart = [
            ExpeditionStatus::EN_TRANSIT_ENTREPOT,
            ExpeditionStatus::DEPART_EXPEDITION_SUCCES,
            ExpeditionStatus::ARRIVEE_EXPEDITION_SUCCES,
            ExpeditionStatus::RECU_AGENCE_DESTINATION,
            ExpeditionStatus::EN_COURS_LIVRAISON,
            ExpeditionStatus::TERMINED,
        ];
        if ($allReceivedByAgenceDepart && !in_array($this->statut_expedition, $statutsApresRecuDepart, true)) {
            $this->update([
                'statut_expedition' => ExpeditionStatus::RECU_AGENCE_DEPART,
                'date_livraison_agence' => $this->date_livraison_agence ?? now(),
            ]);
            return true;
        }

        // Tous les colis expédiés vers l'entrepôt → EN_TRANSIT_ENTREPOT
        $statutsApresTransitEntrepot = [
            ExpeditionStatus::DEPART_EXPEDITION_SUCCES,
            ExpeditionStatus::ARRIVEE_EXPEDITION_SUCCES,
            ExpeditionStatus::RECU_AGENCE_DESTINATION,
            ExpeditionStatus::EN_COURS_LIVRAISON,
            ExpeditionStatus::TERMINED,
        ];
        if ($allExpediesVersEntrepot && !in_array($this->statut_expedition, $statutsApresTransitEntrepot, true)) {
            $this->update([
                'statut_expedition' => ExpeditionStatus::EN_TRANSIT_ENTREPOT,
                'date_deplacement_entrepot' => $this->date_deplacement_entrepot ?? now(),
            ]);
            return true;
        }

        return false;
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


    public function scopeTerminated($query)
    {
        return $query->where('statut_expedition', ExpeditionStatus::TERMINED);
    }

    // Scope pour les expéditions d'une agence
    public function scopePourAgence($query, $agenceId)
    {
        return $query->where('agence_id', $agenceId);
    }

    // Scope pour les expéditions d'un utilisateur (celui qui a enregistré)
    public function scopePourUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Scope pour les expéditions d'un destinataire
    public function scopePourDestinataire($query, $destinataireId)
    {
        return $query->where('destinataire_id', $destinataireId);
    }

    // Scope pour charger les relations livreur
    public function scopeWithLivreurs($query)
    {
        return $query->with(['livreurEnlevement', 'livreurDeplacement', 'livreurLivraison']);
    }

    // Méthode pour charger les relations livreur (méthode d'instance)
    public function chargerLivreurs()
    {
        return $this->load(['livreurEnlevement', 'livreurDeplacement', 'livreurLivraison']);
    }

    // Méthode pour masquer les IDs des livreurs (car les relations sont chargées)
    public function masquerIdsLivreurs()
    {
        return $this->makeHidden([
            'livreur_enlevement_id',
            'livreur_deplacement_id',
            'livreur_livraison_id'
        ]);
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
                $expedition->statut_paiement = StatutPaiement::EN_ATTENTE;
            }
        });


    }
}
