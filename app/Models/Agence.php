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
        'code_agence',
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
        'logo',
        'actif',
        'message_accueil',
        'type'
    ];

    protected $casts = [
        'horaires' => 'array',
        'photos' => 'array',
        'actif' => 'boolean',
    ];

    // Relations
    public function user()
    {
        // L'admin créateur de l'agence
        return $this->belongsTo(User::class);
    }

    /**
     * Tous les utilisateurs rattachés à cette agence (dont l'admin).
     */
    public function users()
    {
        return $this->hasMany(User::class, "agence_id")->notDeleted();
    }

    public function tarifsSimple()
    {
        // Une agence peut avoir plusieurs tarifs
        return $this->hasMany(TarifAgenceSimple::class, "agence_id");
    }

    public function tarifsGroupage()
    {
        // Une agence peut avoir plusieurs tarifs
        return $this->hasMany(TarifAgenceGroupage::class, "agence_id");
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
