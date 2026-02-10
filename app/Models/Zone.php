<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Zone extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Méthode utilisée pour générer un UUID avant la création d'une nouvelle zone.
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
        'pays',
        'actif',
        'backoffice_id'
    ];

    protected $casts = [
        'pays' => 'array',
        'actif' => 'boolean'
    ];

    /**
     * Chaque zone appartient à un backoffice
     */
    public function backoffice()
    {
        return $this->belongsTo(Backoffice::class);
    }

    /**
     * Une zone peut avoir plusieurs tarifs
     */

    public function tarifsBase(): HasMany
    {
        return $this->hasMany(TarifSimple::class, 'zone_destination_id');
    }

    public function tarifsAgence(): HasMany
    {
        return $this->hasMany(TarifAgenceSimple::class, 'zone_destination_id');
    }

    /**
     * Vérifie si un pays appartient à cette zone
     */
    public function contientPays(string $pays): bool
    {
        return in_array(strtolower($pays), array_map('strtolower', $this->pays ?? []));
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
