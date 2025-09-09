<?php

namespace App\Models;

use App\Enums\ColisStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Modèle Colis - Gestion des colis de livraison
 * Supporte les destinataires utilisateurs et externes
 */
class Colis extends Model
{
    use HasFactory;
    use HasFactory;

    protected $primaryKey = 'id';  // Spécifie le nom de la clé primaire
    protected $keyType = 'string';  // Indique que la clé primaire est une chaîne de caractères
    public $incrementing = false;  // Désactive l'auto-incrémentation

    /**
     * Méthode utilisée pour générer un UUID avant la création d'un nouveau colis.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
            if (empty($model->code_suivi)) {
                $model->code_suivi = 'TS' . date('Ymd') . str_pad(static::count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    protected $fillable = [
        'code_suivi',
        'expediteur_id',
        'destinataire_id',
        'destinataire_nom',
        'destinataire_telephone',
        'adresse_destinataire',
        'description',
        'poids',
        'photo_colis',
        'valeur_declaree',
        'adresse_enlevement',
        'lat_enlevement',
        'lng_enlevement',
        'lat_livraison',
        'lng_livraison',
        'agence_id',
        'livreur_id',
        'status',
        'prix_total',
        'commission_livreur',
        'commission_agence',
        'enlevement_domicile',
        'livraison_express',
        'paiement_livraison',
        'instructions_enlevement',
        'instructions_livraison',
        'notes_livreur',
        'photo_livraison',
        'signature_destinataire',
        'date_livraison',
    ];

    protected $casts = [
        'status' => ColisStatus::class,
        'poids' => 'decimal:2',
        'valeur_declaree' => 'decimal:2',
        'lat_enlevement' => 'decimal:8',
        'lng_enlevement' => 'decimal:8',
        'lat_livraison' => 'decimal:8',
        'lng_livraison' => 'decimal:8',
        'prix_total' => 'decimal:2',
        'commission_livreur' => 'decimal:2',
        'commission_agence' => 'decimal:2',
        'enlevement_domicile' => 'boolean',
        'livraison_express' => 'boolean',
        'paiement_livraison' => 'boolean',
        'date_livraison' => 'datetime',
    ];

    // Relations avec les autres modèles
    public function expediteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expediteur_id');
    }

    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function livreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_id');
    }

    // Relation avec l'historique des colis
    public function historiqueStatuts()
    {
        return $this->hasMany(HistoriqueStatutColis::class, 'colis_id');
    }

    // Génération automatique du code de suivi lors de la création

    // Méthodes utilitaires pour gérer les destinataires
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

    // Scopes pour filtrer les colis par statut et utilisateur
    public function scopeEnAttente($query)
    {
        return $query->where('status', 'en_attente');
    }

    public function scopeEnLivraison($query)
    {
        return $query->where('status', 'en_livraison');
    }

    public function scopeLivre($query)
    {
        return $query->where('status', 'livre');
    }

    public function scopeEnCours($query)
    {
        return $query->whereNotIn('status', [ColisStatus::LIVRE, ColisStatus::ANNULE, ColisStatus::ECHEC]);
    }

    public function scopePourLivreur($query, $livreurId)
    {
        return $query->where('livreur_id', $livreurId);
    }

    public function scopeParExpediteur($query, $userId)
    {
        return $query->where('expediteur_id', $userId);
    }

    public function scopeParDestinataire($query, $userId)
    {
        return $query->where('destinataire_id', $userId);
    }
}
