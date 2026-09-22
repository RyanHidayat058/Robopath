<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $fillable = [
        'robot_id',
        'item_name',
        'origin_location',
        'start_location',
        'destination_location',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'formatted_start_location',
        'formatted_destination_location',
    ];

    public static function formatLocationName(?string $location): string
    {
        if (! $location) {
            return '-';
        }

        // Jika diawali dengan {lantai}_ (misal 1_Resepsionis atau 2_Ruang Direktur)
        if (preg_match('/^(\d+)_(.+)$/', trim($location), $matches)) {
            $floor = $matches[1];
            $name = trim($matches[2]);
            return "{$name} (Lantai {$floor})";
        }

        return $location;
    }

    public function getFormattedStartLocationAttribute(): string
    {
        return self::formatLocationName($this->start_location);
    }

    public function getFormattedDestinationLocationAttribute(): string
    {
        return self::formatLocationName($this->destination_location);
    }

    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class);
    }
}
