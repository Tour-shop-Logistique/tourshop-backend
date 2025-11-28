<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ContactExpedition extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contacts_expedition';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

    /**
     * Les attributs qui peuvent être massivement assignés
     */
    protected $fillable = [
        'type_contact',
        'nom_prenom',
        'societe',
        'telephone',
        'email',
        'adresse',
        'pays',
        'ville',
        'etat',
        'quartier',
        'code_postal',
    ];

    /**
     * Les attributs qui doivent être castés
     */
    protected $casts = [];

    /**
     * Scope pour les expéditeurs
     */
    public function scopeExpediteurs($query)
    {
        return $query->where('type_contact', 'expediteur');
    }

    /**
     * Scope pour les destinataires
     */
    public function scopeDestinataires($query)
    {
        return $query->where('type_contact', 'destinataire');
    }

    /**
     * Scope pour les contacts par pays
     */
    public function scopeParPays($query, $pays)
    {
        return $query->where('pays', $pays);
    }
}
