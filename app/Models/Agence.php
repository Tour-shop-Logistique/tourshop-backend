<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Agence extends Model
{
    use HasFactory;

    protected $primaryKey = 'id'; // Spécifie le nom de la clé primaire
    protected $keyType = 'string'; // Indique que la clé primaire est une chaîne de caractères
    public $incrementing = false; // Désactive l'auto-incrémentation

    /**
     * Méthode utilisée pour générer un UUID avant la création d'une nouvelle agence.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid(); // Génère et assigne un UUID
        });
    }

    protected $fillable = [
        'user_id',
        'nom_agence',
        'telephone',
        'description',
        'adresse',
        'ville',
        'commune',
        'pays',
        'latitude',
        'longitude',
        'horaires',
        'photos',
        'actif',
        'message_accueil',
        'promotions'
    ];

    protected $casts = [
        'horaires' => 'array',
        'photos' => 'array',
        'promotions' => 'array',
        'actif' => 'boolean',
    ];

    // Relations
    public function user()
    {
        // Une agence appartient à un utilisateur (son administrateur)
        return $this->belongsTo(User::class);
    }

    // Garder seulement celle-ci car plus explicite
    public function colisEnleves()
    {
        // Les colis déposés/enlevés par cette agence
        return $this->hasMany(Colis::class, 'agence_id');
    }

    public function tarifs()
    {
        // Une agence peut avoir plusieurs tarifs
        return $this->hasMany(Tarif::class);
    }

    // Scopes
    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    public function scopeDansZone($query, $latitude, $longitude)
    {
        return $query->selectRaw(
            "*, (6371 * acos(cos(radians($latitude)) * cos(radians(latitude)) * cos(radians(longitude) - radians($longitude)) + sin(radians($latitude)) * sin(radians(latitude)))) AS distance"
        )->havingRaw('distance <= zone_couverture_km');
    }

    /**
     * Tous les utilisateurs rattachés à cette agence (dont l'admin).
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * L'administrateur (créateur) de l'agence.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
