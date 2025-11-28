<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Support\Str;

class ColisArticle extends Model
{
    use HasFactory;

    protected $table = 'colis_articles';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'colis_id',
        'produit_id',
        'designation',
    ];

    public function colis(): BelongsTo
    {
        return $this->belongsTo(Colis::class, 'colis_id');
    }
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($article) {
            if (empty($article->id)) {
                $article->id = (string) Str::uuid();
            }
        });

    }
}
