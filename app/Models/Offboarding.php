<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offboarding extends Model
{
    use HasFactory;

    protected $table = 'offboardings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'clearance_checklist' => 'array',
            'completed_at' => 'datetime',
            'cooldown_until' => 'datetime',
            'exit_interview_done' => 'boolean',
            'honorable_certificate_issued' => 'boolean',
            'notice_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function initiated_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approved_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
