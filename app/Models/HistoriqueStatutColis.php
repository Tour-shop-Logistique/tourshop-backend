<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HistoriqueStatutColis extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';  // Spécifie le nom de la clé primaire
    protected $keyType = 'string';  // Indique que la clé primaire est une chaîne de caractères
    public $incrementing = false;  // Désactive l'auto-incrémentation

    /**
     * Méthode utilisée pour générer un UUID avant la création d'un nouvel historique.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

    protected $table = 'historique_statut_colis';

    protected $fillable = [
        'colis_id',
        'ancien_statut',
        'nouveau_statut',
        'user_id',
        'latitude',
        'longitude',
        'notes',
    ];

    protected $casts = [
        // 'created_at' et 'updated_at' sont déjà castés par défaut par Eloquent
    ];

    // Relations
    public function colis()
    {
        return $this->belongsTo(Colis::class);
    }

    public function user()
    {
        // L'utilisateur qui a effectué le changement de statut
        return $this->belongsTo(User::class);
    }
}