<?php

namespace App\Services\AdminScreens;

use App\Models\ReportSchedule;
use App\Models\ReportScheduleRun;
use App\Models\User;
use App\Services\Admin\System\StatsService;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * التقارير المجدولة (24.3-خامسًا).
 *
 * لماذا لا نكتفي بزرّ «تصدير» في شاشة الإحصائيّات؟ لأنّ التصدير يتطلّب أن
 * **يتذكّر** الأدمن أن يفتح الشاشة، والتقرير الذي يعتمد على التذكّر لا يصل.
 * الجدولة تنقل العبء من الإنسان إلى النظام: نعرّف مرّةً، ويصل كلّ مرّة.
 *
 * 🔒 والتقرير الذي يلمس بيانات ماليّة يُوسَم `is_financial` — فلا يُنشَأ ولا
 * يُشغَّل ولا يُقرأ خارج المجموعة المحميّة (12.7).
 */
class ReportScheduler
{
    public function __construct(private readonly StatsService $stats) {}

    /** التابات الماليّة — مرجع واحد يمنع اجتهاد كلّ نداء على حدة */
    public const FINANCIAL_TABS = ['sales'];

    /**
     * التقارير المتاحة لهذا المستخدم — التاب الماليّ **يختفي** لغير مالكه (2.15-أ-7).
     *
     * @return array<string,string>
     */
    public function reportsFor(User $user): array
    {
        $tabs = [];

        foreach ($this->stats->tabs() as $key => $tab) {
            if ($this->isFinancial($key) && ! $user->isPlatformOwner()) {
                continue;
            }

            $tabs[$key] = $tab['label'];
        }

        return $tabs;
    }

    public function isFinancial(string $tab): bool
    {
        return in_array($tab, self::FINANCIAL_TABS, true);
    }

    /** @return array<string,string> */
    public function frequencies(): array
    {
        return ScreenSettings::map('report_schedules.frequencies');
    }

    /** @return array<string,string> */
    public function formats(): array
    {
        return ScreenSettings::map('report_schedules.formats');
    }

    /**
     * موعد التشغيل التالي بتوقيت الجدولة نفسها — والحساب في UTC كي لا تنزلق
     * المواعيد مع تغيّر توقيت الخادم.
     */
    public function nextRunAt(ReportSchedule $schedule, ?CarbonImmutable $after = null): CarbonImmutable
    {
        $timezone = $schedule->timezone ?: (string) setting('report_schedules.default_timezone', 'Africa/Cairo');
        $now = ($after ?? CarbonImmutable::now())->setTimezone($timezone);
        $next = $now->setTime((int) $schedule->hour, 0);

        $next = match ($schedule->frequency) {
            'daily' => $next->lessThanOrEqualTo($now) ? $next->addDay() : $next,
            'monthly' => $this->monthlyNext($next, $now, (int) ($schedule->day_of_month ?: 1)),
            default => $this->weeklyNext($next, $now, (int) ($schedule->day_of_week ?? 0)),
        };

        return $next->setTimezone('UTC');
    }

    private function weeklyNext(CarbonImmutable $candidate, CarbonImmutable $now, int $dayOfWeek): CarbonImmutable
    {
        // لو اليوم هو اليوم المطلوب والساعة لسّه ما فاتتش ⟵ اليوم نفسه
        if ((int) $now->dayOfWeek === $dayOfWeek && $candidate->greaterThan($now)) {
            return $candidate;
        }

        // ⚠️ Carbon::next() يصفّر الوقت — فنعيد ضبط الساعة بعده لا قبله
        return $candidate->next($dayOfWeek)->setTime($candidate->hour, 0);
    }

    private function monthlyNext(CarbonImmutable $candidate, CarbonImmutable $now, int $dayOfMonth): CarbonImmutable
    {
        $day = min(max($dayOfMonth, 1), 28); // 28 يضمن وجود اليوم في كلّ الشهور
        $candidate = $candidate->day($day);

        return $candidate->lessThanOrEqualTo($now) ? $candidate->addMonth()->day($day) : $candidate;
    }

    /** الجدولات المستحقّة الآن — تُقرأ بالساعة من المهمّة المجدولة */
    public function due(?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();

        return ReportSchedule::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now))
            ->orderBy('id')
            ->get();
    }

    /**
     * المستقبِلون: بُرُد صريحة + مستخدمون + أدوار — بلا تكرار ولا بريد فاسد.
     *
     * @return array<int,string>
     */
    public function recipients(ReportSchedule $schedule): array
    {
        $emails = collect($schedule->recipient_emails ?? []);

        if ($schedule->recipient_user_ids) {
            $emails = $emails->merge(
                User::query()->whereIn('id', $schedule->recipient_user_ids)->pluck('email')
            );
        }

        if ($schedule->recipient_role_ids) {
            $userIds = DB::table('role_user')->whereIn('role_id', $schedule->recipient_role_ids)->pluck('user_id');
            $emails = $emails->merge(User::query()->whereIn('id', $userIds)->pluck('email'));
        }

        return $emails
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * تشغيل جدولة واحدة: يبني التقرير ويرسله ويكتب سطرًا في السجلّ **مهما كانت
     * النتيجة** — النجاح والفشل والفراغ كلّها وقائع تستحقّ التسجيل.
     *
     * @return array{result:string,message:string,rows:int}
     */
    public function run(ReportSchedule $schedule, ?User $actor = null, bool $manual = false): array
    {
        $attempts = max(1, (int) setting('report_schedules.retry_attempts', 3));
        $period = $this->stats->period(
            CarbonImmutable::now()->subDays(max(1, (int) $schedule->period_days) - 1)->toDateString(),
            CarbonImmutable::now()->toDateString(),
            (bool) $schedule->include_comparison,
        );

        $rows = $this->stats->exportRows($schedule->report_tab, $period);
        $recipients = $this->recipients($schedule);

        if ($rows === [] && $schedule->skip_when_empty) {
            return $this->record($schedule, 'empty', 'مافيش بيانات في المدى ده — واخترت ألّا يُرسَل التقرير الفاضي.', 0, 0, 1, $manual, $actor);
        }

        if ($recipients === []) {
            return $this->record($schedule, 'failed', 'مافيش مستقبِلين صالحين — ضيف بريدًا أو دورًا في تبويب المستقبِلين.', count($rows), 0, 1, $manual, $actor);
        }

        $body = $this->body($schedule, count($rows), $period['days']);
        $subject = $this->subject($schedule);
        $attachment = $this->attachment($schedule, $rows);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                Mail::raw($body, function ($message) use ($recipients, $subject, $attachment) {
                    $message->to($recipients)->subject($subject);

                    if ($attachment !== null) {
                        $message->attachData($attachment['content'], $attachment['name'], ['mime' => $attachment['mime']]);
                    }
                });

                return $this->record($schedule, 'sent', 'اتبعت لـ'.count($recipients).' مستقبِل ✓', count($rows), count($recipients), $attempt, $manual, $actor);
            } catch (Throwable $exception) {
                Log::warning('فشل إرسال تقرير مجدول', ['schedule' => $schedule->id, 'attempt' => $attempt]);

                if ($attempt === $attempts) {
                    $this->notifyFailure($schedule, $exception->getMessage());

                    return $this->record(
                        $schedule,
                        'failed',
                        'تعذّر الإرسال بعد '.$attempts.' محاولات — راجع إعدادات البريد ثمّ اضغط «شغّل الآن».',
                        count($rows),
                        count($recipients),
                        $attempt,
                        $manual,
                        $actor,
                    );
                }
            }
        }

        return $this->record($schedule, 'failed', 'تعذّر الإرسال.', count($rows), count($recipients), $attempts, $manual, $actor);
    }

    /**
     * المرفق — وفوق الحدّ يُستبدَل برابط تنزيل مؤقّت بدل مرفقٍ يرفضه البريد.
     *
     * @return array{name:string,mime:string,content:string}|null
     */
    private function attachment(ReportSchedule $schedule, array $rows): ?array
    {
        $limit = max(0, (int) setting('report_schedules.export_row_limit', 50000));
        $rows = array_slice($rows, 0, $limit ?: null);

        $content = $this->toCsv($rows);
        $maxBytes = max(1, (int) setting('report_schedules.max_attachment_kb', 10240)) * 1024;

        if (strlen($content) > $maxBytes) {
            return null;
        }

        return [
            'name' => str()->slug($schedule->name ?: 'report').'-'.now()->format('Ymd').'.csv',
            'mime' => 'text/csv; charset=UTF-8',
            'content' => $content,
        ];
    }

    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        // BOM حتّى تفتح العربيّة سليمةً في إكسل بلا خطوة إضافيّة
        fwrite($handle, "\xEF\xBB\xBF");

        if ($rows !== []) {
            fputcsv($handle, array_keys((array) $rows[0]));

            foreach ($rows as $row) {
                fputcsv($handle, array_values((array) $row));
            }
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function subject(ReportSchedule $schedule): string
    {
        return strtr((string) setting('report_schedules.subject_template', 'تقرير :name — :date'), [
            ':name' => $schedule->name,
            ':tab' => $schedule->report_tab,
            ':date' => now()->format('Y-m-d'),
        ]);
    }

    private function body(ReportSchedule $schedule, int $rows, int $days): string
    {
        return strtr((string) setting('report_schedules.body_template', "تقرير «:name» عن آخر :days يوم.\nعدد الصفوف: :rows"), [
            ':name' => $schedule->name,
            ':days' => (string) $days,
            ':rows' => (string) $rows,
            ':date' => now()->format('Y-m-d'),
        ]);
    }

    /** تنبيه المنشئ عند الفشل — فالتقرير الصامت الفاشل أسوأ من غيابه */
    private function notifyFailure(ReportSchedule $schedule, string $reason): void
    {
        if (! setting('report_schedules.notify_admin_on_failure', true)) {
            return;
        }

        $admin = $schedule->creator;

        if (! $admin) {
            return;
        }

        Notifier::send(
            $admin,
            'reports',
            'تقرير «'.$schedule->name.'» ما اتبعتش',
            'السبب: '.mb_substr($reason, 0, 180).' — راجع إعدادات البريد وجرّب «شغّل الآن».',
            route('admin.report-schedules.index'),
        );
    }

    /** كتابة النتيجة + تحديث موعد التشغيل التالي في معاملة واحدة */
    private function record(
        ReportSchedule $schedule,
        string $result,
        string $message,
        int $rows,
        int $recipients,
        int $attempt,
        bool $manual,
        ?User $actor,
    ): array {
        ReportScheduleRun::create([
            'report_schedule_id' => $schedule->id,
            'ran_at' => now(),
            'result' => $result,
            'rows_count' => $rows,
            'recipients_count' => $recipients,
            'attempt' => $attempt,
            'was_manual' => $manual,
            'message' => $message,
            'triggered_by' => $actor?->id,
        ]);

        $schedule->forceFill([
            'last_run_at' => now(),
            'last_result' => $result,
            'next_run_at' => $this->nextRunAt($schedule),
        ])->save();

        if ($manual) {
            AuditTrail::log($actor, 'report_schedules.run', $schedule, [], ['result' => $result]);
        }

        return ['result' => $result, 'message' => $message, 'rows' => $rows];
    }

    /** تنظيف السجلّ القديم — يُنادى من نفس المهمّة المجدولة */
    public function pruneLog(): int
    {
        $days = max(1, (int) setting('report_schedules.log_keep_days', 180));

        return (int) ReportScheduleRun::query()->where('ran_at', '<', now()->subDays($days))->delete();
    }
}
