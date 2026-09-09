<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkItem extends Model
{
    use HasFactory;

    protected $table = 'work_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_public_board_candidate' => 'boolean',
            'is_recurring' => 'boolean',
            'last_generated_at' => 'datetime',
            'next_generation_at' => 'datetime',
            'progress_percent' => 'decimal:2',
            'vxp_pool' => 'decimal:2',
            'vxp_spent' => 'decimal:2',
        ];
    }

    public function work_package(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class, 'work_package_id');
    }

    public function assigned_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function nominator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominated_by');
    }
}
