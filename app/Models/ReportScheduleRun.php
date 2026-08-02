<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

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
            'download_expires_at' => 'datetime',
        ];
    }

    /** هل لهذا السطر رابط تنزيل مؤقّت ما زال صالحًا؟ */
    public function hasLiveDownload(): bool
    {
        return $this->download_token !== null
            && $this->download_path !== null
            && $this->download_expires_at !== null
            && $this->download_expires_at->isFuture();
    }

    /**
     * الرابط الموقَّع نفسه — يُولَّد بنفس تاريخ الانتهاء المحفوظ، فالتوقيع الذي
     * وصل في البريد هو التوقيع الذي يظهر في الشاشة بلا تمديدٍ للمدّة.
     */
    public function downloadUrl(): ?string
    {
        if (! $this->hasLiveDownload()) {
            return null;
        }

        return URL::temporarySignedRoute('reports.download', $this->download_expires_at, [
            'token' => $this->download_token,
        ]);
    }

    /** نوع الملفّ من صيغته — مرجع واحد يستعمله المرفق ومسار التنزيل */
    public function downloadMime(): string
    {
        return match ((string) $this->download_format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            default => 'text/csv; charset=UTF-8',
        };
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
