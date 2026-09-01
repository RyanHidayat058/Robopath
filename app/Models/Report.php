<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'robot_id',
        'issue_type',
        'description',
        'image_path',
        'status',
    ];

    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class);
    }
}
