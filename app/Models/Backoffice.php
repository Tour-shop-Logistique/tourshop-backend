<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;

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

    public function tarifSimple()
    {
        return $this->hasMany(TarifSimple::class);
    }

    public function tarifGroupage()
    {
        return $this->hasMany(TarifGroupage::class);
    }

    protected function logo(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!$value)
                    return null;

                // Si le logo commence déjà par http, c'est une URL complète
                if (str_starts_with($value, 'http')) {
                    return $value;
                }

                $projectUrl = rtrim(config('supabase.url'), '/');
                $bucket = config('supabase.bucket');

                return "{$projectUrl}/storage/v1/object/public/{$bucket}/{$value}";
            },
        );
    }
}
