<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modèle User - Gestion des utilisateurs de l'application
 * Supporte différents types : client, livreur, admin, agence
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'id';  // Spécifie le nom de la clé primaire
    protected $keyType = 'string';  // Indique que la clé primaire est une chaîne de caractères
    public $incrementing = false;  // Désactive l'auto-incrémentation

    /**
     * Méthode utilisée pour générer un UUID avant la création d'un nouvel utilisateur.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

    protected $fillable = [
        'nom',
        'prenoms',
        'telephone',
        'email',
        'type',
        'password',
        'avatar',
        'adresses_favoris',
        'disponible',
        'actif',
        'role',
        'is_deleted',
        // Rattachement à une agence
        'agence_id',
        // Rattachement à un backoffice
        'backoffice_id',
        
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'telephone_verified_at' => 'datetime',
        'password' => 'hashed',
        'adresses_favoris' => 'array',
        'disponible' => 'boolean',
        'actif' => 'boolean',
        'is_deleted' => 'boolean',
        'type' => UserType::class,
    ];

    // Relations avec les colis et agences
    public function colisExpedies()
    {
        // Un utilisateur peut expédier plusieurs colis
        return $this->hasMany(Colis::class, 'expediteur_id');
    }

    public function colisRecus()
    {
        // Un utilisateur peut être destinataire de plusieurs colis
        return $this->hasMany(Colis::class, 'destinataire_id');
    }

    public function colisLivres()
    {
        // Un livreur (user) peut livrer plusieurs colis
        return $this->hasMany(Colis::class, 'livreur_id');
    }

    public function agence()
    {
        // Tout utilisateur est rattaché à UNE agence via users.agence_id 
        return $this->belongsTo(Agence::class);
    }

    public function backoffice()
    {
        // Tout utilisateur backoffice est rattaché à UN backoffice via users.backoffice_id
        return $this->belongsTo(Backoffice::class);
    }

    public function historiqueStatuts()
    {
        // Un utilisateur peut avoir effectué plusieurs changements de statut de colis
        return $this->hasMany(HistoriqueStatutColis::class, 'user_id');
    }

    // Scopes pour filtrer les utilisateurs par type et disponibilité
    public function scopeClients($query)
    {
        return $query->where('type', UserType::CLIENT);
    }

    public function scopeLivreurs($query)
    {
        return $query->where('type', UserType::LIVREUR);
    }

    public function scopeDisponibles($query)
    {
        return $query->where('disponible', true);
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    // Accesseurs pour les propriétés calculées
    public function getNomCompletAttribute()
    {
        return $this->nom . ' ' . $this->prenoms;
    }

    /**
     * Helper: indique si cet utilisateur est administrateur de SON agence
     * (i.e., il est le créateur de l'agence).
     */
    public function isAgenceAdmin(): bool
    {
        return $this->agence && $this->agence->user_id === $this->id;
    }

    /**
     * Helper: indique si cet utilisateur est administrateur de SON backoffice
     * (i.e., il est le créateur du backoffice).
     */
    public function isBackofficeAdmin(): bool
    {
        return $this->backoffice && $this->backoffice->user_id === $this->id;
    }
}
