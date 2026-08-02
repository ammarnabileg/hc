<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * جدولة تقرير دوريّ (24.3-خامسًا).
 */
class ReportSchedule extends Model
{
    use HasFactory;

    protected $table = 'report_schedules';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_financial' => 'boolean',
            'include_comparison' => 'boolean',
            'skip_when_empty' => 'boolean',
            'recipient_emails' => 'array',
            'recipient_role_ids' => 'array',
            'recipient_user_ids' => 'array',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ReportScheduleRun::class, 'report_schedule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
