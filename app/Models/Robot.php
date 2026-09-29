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
        'floor',
    ];

    protected $casts = [
        'battery_level' => 'integer',
        'current_x' => 'float',
        'current_y' => 'float',
        'floor' => 'integer',
    ];

    protected $appends = [
        'status_indonesian',
        'position_name',
        'task_type',
        'is_moving',
        'package_ready',
        'current_task_id',
        'is_busy',
    ];

    public function getStatusIndonesianAttribute(): string
    {
        return match ($this->status) {
            'Idle' => 'Siaga',
            'Heading to Pickup' => 'Menuju Pengambilan',
            'Waiting for Item' => 'Menunggu Barang',
            'Delivering' => 'Mengantar',
            'Returning' => 'Kembali',
            'Maintenance' => 'Perbaikan',
            'Charging' => 'Pengisian Daya',
            default => $this->status,
        };
    }

    public function getPositionNameAttribute(): string
    {
        // Jika robot sedang menuju pengambilan
        $pendingDelivery = $this->deliveries()->where('status', 'Pending')->latest()->first();
        if ($this->status === 'Heading to Pickup' && $pendingDelivery) {
            return 'Menuju ' . Delivery::formatLocationName($pendingDelivery->start_location);
        }

        // Jika robot sedang menunggu barang
        if ($this->status === 'Waiting for Item' && $pendingDelivery) {
            return Delivery::formatLocationName($pendingDelivery->start_location);
        }

        // Jika robot sedang mengantar
        $inProgressDelivery = $this->deliveries()->where('status', 'In Progress')->latest()->first();
        if ($this->status === 'Delivering' && $inProgressDelivery) {
            return 'Menuju ' . Delivery::formatLocationName($inProgressDelivery->destination_location);
        }

        // Jika robot sedang kembali
        if ($this->status === 'Returning') {
            return 'Menuju Markas';
        }

        $isFloor1 = ((int) ($this->floor ?? 1) === 1);
        $curX = (float) ($this->current_x ?? 85.48);
        $curY = (float) ($this->current_y ?? 51.07);

        if ($isFloor1) {
            $d3D = hypot($curX - 85.48, $curY - 51.07);
            $d2D = hypot($curX - 76.23, $curY - 64.42);
            if ($d3D <= 3.5 || $d2D <= 3.5) {
                return 'Markas Robot';
            }
        }

        static $cachedLocations = null;
        if ($cachedLocations === null) {
            $graphPath = base_path('graph_3d.json');
            $data = file_exists($graphPath) ? json_decode(file_get_contents($graphPath), true) : [];
            $cachedLocations = $data['locations'] ?? [];
        }

        $closestName = null;
        $minDist = 999.0;
        foreach ($cachedLocations as $loc) {
            $locFloor = (int) ($loc['floor'] ?? 1);
            if ($locFloor !== (int) ($this->floor ?? 1)) {
                continue;
            }
            $lx = (float) ($loc['x'] ?? 0);
            $ly = (float) ($loc['y'] ?? 0);
            $dist = hypot($curX - $lx, $curY - $ly);
            if ($dist < $minDist) {
                $minDist = $dist;
                $closestName = $loc['name'] ?? $loc['id'];
            }
        }

        if ($closestName && $minDist <= 8.0) {
            return Delivery::formatLocationName($closestName);
        }

        return 'Lantai ' . ($this->floor ?? 1);
    }

    public function getTaskTypeAttribute(): ?string
    {
        $active = $this->deliveries()->whereIn('status', ['Pending', 'In Progress'])->latest()->first();
        return $active ? ($active->task_type ?? 'Delivery') : null;
    }

    public function getIsMovingAttribute(): bool
    {
        return in_array($this->status, ['Delivering', 'Heading to Pickup', 'Returning']);
    }

    public function getPackageReadyAttribute(): bool
    {
        return ($this->status === 'Delivering');
    }

    public function getCurrentTaskIdAttribute(): ?int
    {
        $active = $this->deliveries()->whereIn('status', ['Pending', 'In Progress'])->latest()->first();
        return $active ? $active->id : null;
    }

    public function getIsBusyAttribute(): bool
    {
        if (in_array($this->status, ['Delivering', 'Heading to Pickup', 'Waiting for Item', 'Charging', 'Maintenance'])) {
            return true;
        }
        return $this->deliveries()->whereIn('status', ['Pending', 'In Progress'])->exists();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
