<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سطر في سجلّ إرسال التقارير المجدولة (24.3-خامسًا) — يُكتَب ولا يُعاد كتابته.
 */
class ReportScheduleRun extends Model
{
    use HasFactory;

    protected $table = 'report_schedule_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
            'was_manual' => 'boolean',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'report_schedule_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
