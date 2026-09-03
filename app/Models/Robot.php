<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Robot extends Model
{
    protected $fillable = [
        'name',
        'status',
        'battery_level',
        'current_x',
        'current_y',
    ];

    protected $casts = [
        'battery_level' => 'integer',
        'current_x' => 'float',
        'current_y' => 'float',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
