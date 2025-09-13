<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    // Pas de génération automatique d'UUID: l'id est fourni (ex: Z1..Z8)

    protected $fillable = [
        'id',
        'nom',
        'pays',
        'actif'
    ];

    protected $casts = [
        'pays' => 'array',
        'actif' => 'boolean'
    ];

    /**
     * Une zone peut avoir plusieurs tarifs
     */

    public function tarifsBase(): HasMany
    {
        return $this->hasMany(TarifBase::class, 'zone_destination_id');
    }

    /**
     * Vérifie si un pays appartient à cette zone
     */
    public function contientPays(string $pays): bool
    {
        return in_array(strtolower($pays), array_map('strtolower', $this->pays ?? []));
    }
}
