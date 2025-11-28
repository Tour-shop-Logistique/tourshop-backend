<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CommissionSetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'is_active'
    ];

    protected $casts = [
        'value' => 'float',
        'is_active' => 'boolean'
    ];
}
