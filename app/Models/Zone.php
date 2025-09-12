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

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

    protected $fillable = [
        'nom',
        'code',
        'pays', // JSON array des pays de cette zone
        'actif'
    ];

    protected $casts = [
        'pays' => 'array',
        'actif' => 'boolean'
    ];

    /**
     * Une zone peut avoir plusieurs tarifs
     */
    public function tarifsDepart(): HasMany
    {
        return $this->hasMany(Tarif::class, 'zone_depart_id');
    }

    public function tarifsArrivee(): HasMany
    {
        return $this->hasMany(Tarif::class, 'zone_arrivee_id');
    }

    /**
     * Vérifie si un pays appartient à cette zone
     */
    public function contientPays(string $pays): bool
    {
        return in_array(strtolower($pays), array_map('strtolower', $this->pays ?? []));
    }
}
