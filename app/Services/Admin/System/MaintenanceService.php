<?php

namespace App\Services\Admin\System;

use App\Models\MaintenanceWindow;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * وضع الصيانة العامّ + تجميد المهل (12.7-و-1).
 *
 * ⛔ لا صيانة جزئيّة لميزة بعينها — أُلغيت؛ إطفاء ميزة يتمّ من «مفاتيح المزايا».
 *
 * ⭐ آليّة الحساب: نخزّن **لحظة بدء ونهاية كلّ فترة** ثمّ نطرح **مجموع الفترات
 *    المدموجة** — فلو تداخلت فترتان لا تُحسب المدّة مرّتين. والتعديل على المهل
 *    يقع **دفعةً واحدة عند الرفع** لا صفًّا صفًّا أثناء الصيانة.
 */
class MaintenanceService
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    public function isActive(): bool
    {
        return (bool) setting('system.maintenance.enabled', false) && $this->current() !== null;
    }

    public function current(): ?MaintenanceWindow
    {
        return MaintenanceWindow::query()->whereNull('ended_at')->latest('id')->first();
    }

    /**
     * تفعيل الصيانة: رسالة + عدد ساعات — ومن هذه اللحظة كلّ المهل مجمَّدة.
     * ولا نلمس أيّ عمود الآن: التجميد أثرٌ يُحسب، والتطبيق عند الرفع.
     */
    public function start(User $actor, string $message, int $hours): MaintenanceWindow
    {
        if ($window = $this->current()) {
            return $window;
        }

        $window = MaintenanceWindow::create([
            'message' => $message,
            'planned_hours' => max(1, $hours),
            'started_at' => now(),
            'expected_end_at' => now()->addHours(max(1, $hours)),
            'started_by' => $actor->id,
        ]);

        $this->flag(true, $message, $actor);

        return $window;
    }

    /** تمديد بضغطة: +1 · +3 · مخصّص — والحدّ من الإعدادات لا رقم محروق */
    public function extend(User $actor, int $hours): ?MaintenanceWindow
    {
        $window = $this->current();

        if (! $window) {
            return null;
        }

        $max = (int) setting('system.maintenance.max_extend_hours', 24);
        $hours = max(1, min($hours, $max));

        $window->update([
            'expected_end_at' => CarbonImmutable::parse($window->expected_end_at)->addHours($hours),
            'planned_hours' => $window->planned_hours + $hours,
        ]);

        $this->note($actor, 'system.maintenance.enabled', 'تمديد الصيانة '.$hours.' ساعة');

        return $window->refresh();
    }

    /**
     * رفع الصيانة: إغلاق الفترة ثمّ **إعادة حساب دفعة واحدة** لكلّ المهل المتأثّرة،
     * وسجلّ بعدد السجلّات المعدَّلة ومدّة التجميد (12.7-و-1).
     *
     * @return array{seconds:int, rows:int}
     */
    public function lift(User $actor): array
    {
        $window = $this->current();

        if (! $window) {
            return ['seconds' => 0, 'rows' => 0];
        }

        $window->update(['ended_at' => now()]);

        $seconds = (int) CarbonImmutable::parse($window->started_at)
            ->diffInSeconds(CarbonImmutable::parse($window->ended_at));

        $rows = 0;

        if (setting('system.maintenance.freeze_deadlines', true)) {
            $rows = $this->shiftDeadlines($window, $seconds);
        }

        $window->update(['deadlines_recomputed' => true]);
        $this->flag(false, (string) $window->message, $actor);
        $this->note($actor, 'system.maintenance.enabled', "رفع الصيانة — {$rows} مهلة اتعدّلت بفارق {$seconds} ثانية");

        return ['seconds' => $seconds, 'rows' => $rows];
    }

    /**
     * مجموع ثواني التجميد الواقعة داخل مدّة بعينها، **بلا ازدواج حساب**
     * عند تداخل الفترات — تُستعمَل لأيّ مهلة تُحسب لاحقًا.
     */
    public function frozenSeconds(CarbonImmutable $from, ?CarbonImmutable $to = null): int
    {
        $to ??= CarbonImmutable::now();

        $ranges = MaintenanceWindow::query()
            ->orderBy('started_at')
            ->get(['started_at', 'ended_at'])
            ->map(fn ($w) => [
                CarbonImmutable::parse($w->started_at),
                CarbonImmutable::parse($w->ended_at ?? now()),
            ])
            ->all();

        $total = 0;
        $cursorEnd = null;

        foreach ($ranges as [$start, $end]) {
            $start = $start->lessThan($from) ? $from : $start;
            $end = $end->greaterThan($to) ? $to : $end;

            if ($end->lessThanOrEqualTo($start)) {
                continue;
            }

            // دمج الفترات المتداخلة: نتقدّم بالمؤشّر فلا تُحسب ثانيةٌ مرّتين
            if ($cursorEnd !== null && $start->lessThan($cursorEnd)) {
                $start = $cursorEnd;

                if ($end->lessThanOrEqualTo($start)) {
                    continue;
                }
            }

            $total += (int) $start->diffInSeconds($end);
            $cursorEnd = $end;
        }

        return $total;
    }

    /**
     * ⭐ نصّ ما يراه المستخدم: عند بلوغ العدّاد صفرًا **لا عدّاد سالب** —
     * تتبدّل الرسالة بنفس المساحة بالظبط فلا تقفز الصفحة (12.7-و-1).
     *
     * @return array{message:string, ends_at:?string, seconds_left:int, overrun:bool, refresh_seconds:int}
     */
    public function publicState(): array
    {
        $window = $this->current();
        $refresh = (int) setting('system.maintenance.refresh_seconds', 120);

        if (! $window) {
            return ['message' => '', 'ends_at' => null, 'seconds_left' => 0, 'overrun' => false, 'refresh_seconds' => $refresh];
        }

        $left = (int) now()->diffInSeconds(CarbonImmutable::parse($window->expected_end_at), false);

        return [
            'message' => (string) $window->message,
            'ends_at' => CarbonImmutable::parse($window->expected_end_at)->toIso8601String(),
            'seconds_left' => max(0, $left),
            'overrun' => $left <= 0,
            'refresh_seconds' => $refresh,
        ];
    }

    /** رسالة ما بعد الصفر — نصّها إعداد لا نصّ محروق */
    public function overrunMessage(): string
    {
        return (string) setting('system.maintenance.overrun_text', 'قرّبنا ننتهي — دقايق');
    }

    /**
     * ⭐ استثناء IP الأدمن ليكمل عمله والموقع مقفول (12.7-ج).
     *
     * **مصدرٌ واحد للقائمة**: `system.maintenance.exempt_ips`. كان لها مصدران
     * (`admin_ips` هنا و`exempt_ips` في البوّابة)، وقائمةٌ أمنيّة بمصدرين خطر:
     * الأدمن يشيل IP من شاشةٍ فيبقى مسموحًا من الأخرى. و`allow_admin_ip` يبقى
     * كما هو — فهو مفتاح تشغيل لا قائمة ثانية.
     *
     * @return array<int, string>
     */
    public function exemptIps(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/[\s,]+/', (string) setting('system.maintenance.exempt_ips', '')) ?: [],
        )));
    }

    public function ipAllowed(?string $ip): bool
    {
        if (! setting('system.maintenance.allow_admin_ip', true)) {
            return false;
        }

        return $ip !== null && in_array($ip, $this->exemptIps(), true);
    }

    // ------------------------------------------------- الصيانة المجدولة (12.7-ج)

    /**
     * ⭐ «مجدول (يبدأ/ينتهي تلقائيًّا)» — كان الفورم رسالةً وساعاتٍ فقط بلا **وقت
     * بدء**، فلا سبيل لجدولتها أصلًا. الآن: موعدٌ في المستقبل يُحفَظ ولا يُفعَّل،
     * والمسحة الدوريّة تفعّله في وقته، والنهاية تلقائيّة كما هي بعدد الساعات.
     *
     * @return array{at: ?string, message: string, hours: int}
     */
    public function scheduled(): array
    {
        return [
            'at' => (string) setting('system.maintenance.scheduled_at', '') ?: null,
            'message' => (string) setting('system.maintenance.scheduled_message', ''),
            'hours' => (int) setting('system.maintenance.scheduled_hours', 0),
        ];
    }

    public function schedule(User $actor, string $message, int $hours, CarbonImmutable $startsAt): void
    {
        $this->write('system.maintenance.scheduled_at', $startsAt->toDateTimeString(), $actor);
        $this->write('system.maintenance.scheduled_message', $message, $actor);
        $this->write('system.maintenance.scheduled_hours', (string) max(1, $hours), $actor);
    }

    public function cancelSchedule(User $actor): void
    {
        $this->write('system.maintenance.scheduled_at', '', $actor);
        $this->write('system.maintenance.scheduled_hours', '0', $actor);
    }

    /**
     * تُنادى من المسحة الدوريّة: تبدأ الصيانة المجدولة حين يحلّ موعدها.
     * والقرار هنا لا في تعبير الكرون — فالموعد إعدادٌ يغيّره الأدمن أيّ وقت.
     */
    public function startDueSchedule(?User $actor = null): ?MaintenanceWindow
    {
        $scheduled = $this->scheduled();

        if (! $scheduled['at'] || $scheduled['hours'] <= 0 || $this->isActive()) {
            return null;
        }

        if (CarbonImmutable::parse($scheduled['at'])->isFuture()) {
            return null;
        }

        $actor ??= User::query()->whereNotNull('id')->orderBy('id')->first();

        if (! $actor) {
            return null;
        }

        $window = $this->start($actor, $scheduled['message'], $scheduled['hours']);
        $this->cancelSchedule($actor);

        return $window;
    }

    /**
     * الأعمدة المجمَّدة — إعدادٌ قابل للتعديل لا خريطة محروقة.
     * **ولا يشمل** تصفير Rep الشهريّ ولا «مشرف الشهر» فهما مؤقّتان دوريّان ثابتان.
     *
     * @return array<string, array<int,string>>
     */
    public function frozenTargets(): array
    {
        $configured = setting('system.maintenance.frozen_targets');

        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return [
            'enrollments' => ['deadline_at'],
            'tasks' => ['deadline_at', 'merge_window_at'],
            'task_submissions' => ['fix_due_at'],
            'task_contributions' => ['internal_deadline_at', 'owner_review_due_at'],
            'contribution_checkpoints' => ['response_due_at'],
            'objections' => ['sla_due_at'],
            'escalations' => ['window_due_at'],
            'escalation_steps' => ['due_at'],
            'arbitrations' => ['window_due_at'],
            'placement_requests' => ['respond_due_at'],
            'consent_requests' => ['request_expires_at', 'consent_expires_at', 'cooldown_until'],
            'meetings' => ['attendance_closes_at'],
        ];
    }

    // ------------------------------------------------------------------ داخليّ

    /** إعادة الحساب دفعةً واحدة: كلّ مهلة لم تكن قد فاتت قبل الصيانة تُزاح بالفارق */
    private function shiftDeadlines(MaintenanceWindow $window, int $seconds): int
    {
        if ($seconds <= 0) {
            return 0;
        }

        $startedAt = CarbonImmutable::parse($window->started_at);
        $rows = 0;

        foreach ($this->frozenTargets() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $rows += DB::table($table)
                    ->whereNotNull($column)
                    // ما فات قبل بدء الصيانة لا يُمدَّد — التجميد استئنافٌ لا مكافأة
                    ->where($column, '>=', $startedAt)
                    ->update([$column => DB::raw($this->addSecondsExpression($column, $seconds))]);
            }
        }

        return $rows;
    }

    /** تعبير إضافة الثواني — SQLite في التطوير وغيرها في الإنتاج */
    private function addSecondsExpression(string $column, int $seconds): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "datetime({$column}, '+{$seconds} seconds')",
            'pgsql' => "{$column} + interval '{$seconds} seconds'",
            default => "DATE_ADD({$column}, INTERVAL {$seconds} SECOND)",
        };
    }

    /** كتابة إعدادٍ واحد بسطر تدقيقه — كلّ تغييرٍ مَن ومتى (12.7-ج). */
    private function write(string $key, string $value, User $actor): void
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return;
        }

        $old = $setting->value;
        $setting->update(['value' => $value]);
        $this->registry->audit($setting, $old, $value, $actor, 'maintenance.schedule');

        Cache::forget('settings');
    }

    private function flag(bool $on, string $message, User $actor): void
    {
        $enabled = Setting::query()->where('key', 'system.maintenance.enabled')->first();
        $text = Setting::query()->where('key', 'system.maintenance.message')->first();

        if ($enabled) {
            $old = $enabled->value;
            $enabled->update(['value' => $on ? '1' : '0']);
            $this->registry->audit($enabled, $old, $on ? '1' : '0', $actor, 'maintenance.toggle');
        }

        if ($text && $on) {
            $old = $text->value;
            $text->update(['value' => $message]);
            $this->registry->audit($text, $old, $message, $actor, 'maintenance.message');
        }

        Cache::forget('settings');
    }

    private function note(User $actor, string $key, string $reason): void
    {
        $setting = Setting::query()->where('key', $key)->first();

        if ($setting) {
            $this->registry->audit($setting, $setting->value, $setting->value, $actor, 'maintenance.note', $reason);
        }
    }
}
