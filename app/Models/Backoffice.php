<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Modèle Backoffice - Gestion des informations backoffice
 */
class Backoffice extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Méthode utilisée pour générer un UUID avant la création d'un nouveau backoffice.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

    protected $fillable = [
        'user_id',
        'nom_organisation',
        'telephone',
        'adresse',
        'localisation',
        'ville',
        'commune',
        'pays',
        'email',
        'logo',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        
    ];

    // Relations
    public function user()
    {
        // L'admin créateur du backoffice
        return $this->belongsTo(User::class);
    }

    public function users()
    {
        // Tous les utilisateurs backoffice rattachés à ce backoffice
        return $this->hasMany(User::class, 'backoffice_id')->notDeleted();
    }

    public function members()
    {
        // Les membres backoffice (non admin, non supprimés)
        return $this->hasMany(User::class, 'backoffice_id')
                   ->where('id', '!=', $this->user_id)
                   ->notDeleted();
    }

    // Scopes
    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

      public function tarifs()
    {
        // Une agence peut avoir plusieurs tarifs
        return $this->hasMany(TarifBase::class);
    }
}
