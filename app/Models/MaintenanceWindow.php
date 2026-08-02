<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWindow extends Model
{
    use HasFactory;

    protected $table = 'maintenance_windows';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'deadlines_recomputed' => 'boolean',
            'ended_at' => 'datetime',
            'expected_end_at' => 'datetime',
            'started_at' => 'datetime',
        ];
    }

    public function started_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
