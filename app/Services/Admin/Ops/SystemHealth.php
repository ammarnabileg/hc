<?php

namespace App\Services\Admin\Ops;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * صحّة النظام وتنبيهاته الاستباقيّة (12.7-و · 2.16).
 *
 * قاعدتان تحكمان هذا الصنف:
 *  1) **كلّ مؤشّر بلون ورمز** — واللون وحده لا يحمل المعنى أبدًا (2.16-ب).
 *  2) **التنبيه يسبق الكارثة** — العتبات من `setting()`، وعند تجاوزها يصل
 *     إشعارٌ للأدمن بدل أن يكتشف القرصَ ممتلئًا من شكوى مستخدم.
 */
class SystemHealth
{
    public function __construct(
        private readonly OpsAudit $audit,
        private readonly OpsSettings $settings,
        private readonly BackupManager $backups,
    ) {}

    /**
     * تقرير كامل — كلّ مؤشّر: مفتاح · عنوان · قيمة · حالة (ok/warn/danger/idle) · سطر شرح واحد.
     *
     * @return array<int, array{key:string,label:string,value:string,state:string,hint:string}>
     */
    public function report(): array
    {
        return [
            $this->disk(),
            $this->database(),
            $this->cache(),
            $this->queue(),
            $this->schedule(),
            $this->extensions(),
            $this->writablePaths(),
            $this->lastBackup(),
        ];
    }

    /** أسوأ حالة في التقرير — هي حالة النظام كلّه، فلا يُخفي المتوسّطُ عطبًا */
    public function overallState(array $report): string
    {
        $states = array_column($report, 'state');

        return match (true) {
            in_array('danger', $states, true) => 'danger',
            in_array('warn', $states, true) => 'warn',
            default => 'ok',
        };
    }

    // ------------------------------------------------------------------ المؤشّرات

    public function disk(): array
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        if (! $total || $free === false) {
            return $this->row('disk', 'مساحة القرص', 'غير متاحة', 'idle', 'الخادم مش بيسمح بقراءة المساحة.');
        }

        $usedPercent = (int) round((($total - $free) / $total) * 100);
        $alert = (int) setting('backups.disk_alert_percent', 85);
        $warn = (int) setting('system.health.disk_warn_percent', 75);

        return $this->row(
            'disk',
            'مساحة القرص',
            $usedPercent.'% مستخدَمة · فاضي '.$this->backups->humanSize((int) $free),
            $usedPercent >= $alert ? 'danger' : ($usedPercent >= $warn ? 'warn' : 'ok'),
            "التنبيه عند {$alert}% — والتحذير المبكّر عند {$warn}%.",
            ['percent' => $usedPercent],
        );
    }

    public function database(): array
    {
        $startedAt = microtime(true);

        try {
            DB::select('select 1');
            $ms = (int) round((microtime(true) - $startedAt) * 1000);
        } catch (Throwable $e) {
            return $this->row('database', 'قاعدة البيانات', 'مش متّصلة', 'danger', 'راجع بيانات الاتّصال في ملفّ البيئة.');
        }

        $slow = (int) setting('system.health.db_slow_ms', 300);

        return $this->row(
            'database',
            'قاعدة البيانات',
            DB::getDriverName().' · '.$ms.' م.ث',
            $ms >= $slow ? 'warn' : 'ok',
            "الردّ الطبيعيّ أقلّ من {$slow} م.ث.",
        );
    }

    public function cache(): array
    {
        $key = 'ops.health.probe';
        $value = (string) Str::uuid();

        try {
            Cache::put($key, $value, 30);
            $ok = Cache::get($key) === $value;
            Cache::forget($key);
        } catch (Throwable $e) {
            $ok = false;
        }

        return $this->row(
            'cache',
            'الكاش',
            (string) config('cache.default'),
            $ok ? 'ok' : 'danger',
            $ok ? 'الكتابة والقراءة شغّالة.' : 'الكاش مش بيكتب — الصفحات هتبقى أبطأ والإعدادات ممكن تتأخّر.',
        );
    }

    public function queue(): array
    {
        $connection = (string) config('queue.default');

        if (! Schema::hasTable('jobs')) {
            return $this->row('queue', 'الطوابير', $connection, 'idle', 'مافيش جدول طوابير — التنفيذ فوريّ.');
        }

        $pending = (int) DB::table('jobs')->count();
        $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
        $limit = (int) setting('system.health.queue_warn_jobs', 100);

        return $this->row(
            'queue',
            'الطوابير',
            "{$connection} · {$pending} منتظرة · {$failed} فاشلة",
            $failed > 0 ? 'danger' : ($pending > $limit ? 'warn' : 'ok'),
            "التحذير لو المنتظر عدّى {$limit} مهمّة.",
            ['pending' => $pending, 'failed' => $failed],
        );
    }

    /** آخر تشغيل للجدولة — وتوقّفها يعني توقّف كلّ المؤقّتات بلا أن يلاحظ أحد */
    public function schedule(): array
    {
        $last = $this->scheduleLastRun();
        $limit = (int) setting('backups.cron_alert_hours', 1);

        if (! $last) {
            return $this->row('schedule', 'الجدولة', 'مافيش تشغيل مسجَّل', 'danger', 'الكرون غالبًا مش متظبّط على الخادم.');
        }

        $hours = round((time() - $last) / 3600, 1);

        return $this->row(
            'schedule',
            'الجدولة',
            'آخر تشغيل من '.$this->humanHours($hours),
            $hours > $limit ? 'danger' : 'ok',
            "التنبيه لو عدّى {$limit} ساعة بلا تشغيل.",
            ['hours' => $hours],
        );
    }

    /** الامتدادات المطلوبة (gd · zip · intl · imagick) — والقائمة إعداد لا كود */
    public function extensions(): array
    {
        $required = setting('system.health.required_extensions', ['gd', 'zip', 'intl', 'imagick']);
        $required = is_array($required) ? $required : [];

        $missing = array_values(array_filter($required, fn ($ext) => ! extension_loaded((string) $ext)));

        return $this->row(
            'extensions',
            'الامتدادات المطلوبة',
            $missing === [] ? count($required).' امتداد كلّها موجودة' : 'ناقص: '.implode(' · ', $missing),
            $missing === [] ? 'ok' : 'danger',
            $missing === [] ? 'كلّ حاجة تمام.' : 'نصّب الناقص من الخادم عشان الصور والأرشيف يشتغلوا.',
            ['missing' => $missing],
        );
    }

    /** صلاحيّات الكتابة — أوّل ما يكسر النسخ الاحتياطيّ ورفع الملفّات */
    public function writablePaths(): array
    {
        $paths = setting('system.health.writable_paths', ['storage/app', 'storage/logs', 'storage/framework', 'bootstrap/cache']);
        $paths = is_array($paths) ? $paths : [];
        $paths[] = 'storage/'.trim((string) setting('backups.path', 'backups'), '/');

        $blocked = [];

        foreach (array_unique($paths) as $relative) {
            $full = base_path((string) $relative);

            if (! is_dir($full)) {
                @mkdir($full, 0755, true);
            }

            if (! is_dir($full) || ! is_writable($full)) {
                $blocked[] = (string) $relative;
            }
        }

        return $this->row(
            'writable',
            'صلاحيّات الكتابة',
            $blocked === [] ? count($paths).' مجلّد قابل للكتابة' : 'مقفول: '.implode(' · ', $blocked),
            $blocked === [] ? 'ok' : 'danger',
            $blocked === [] ? 'كلّ المجلّدات مفتوحة للكتابة.' : 'اضبط ملكيّة المجلّدات دي للمستخدم اللي بيشغّل الخادم.',
            ['blocked' => $blocked],
        );
    }

    public function lastBackup(): array
    {
        $hours = $this->backups->hoursSinceLastBackup();
        $limit = (int) setting('backups.max_age_hours_alert', 48);

        if ($hours === null) {
            return $this->row('backup', 'آخر نسخة احتياطيّة', 'مافيش نسخة لسه', 'danger', 'خُد نسختك الأولى دلوقتي.');
        }

        return $this->row(
            'backup',
            'آخر نسخة احتياطيّة',
            'من '.$this->humanHours($hours),
            $hours > $limit ? 'danger' : ($hours > $limit / 2 ? 'warn' : 'ok'),
            "التنبيه لو عدّى {$limit} ساعة بلا نسخة.",
            ['hours' => $hours],
        );
    }

    // ------------------------------------------------------------------ التنبيهات

    /**
     * التنبيهات الاستباقيّة الثلاثة (12.7-و): القرص · قِدَم آخر نسخة · تعطّل الجدولة.
     *
     * @return array<int, array{key:string,level:string,message:string}>
     */
    public function alerts(?array $report = null): array
    {
        if (! setting('system.health.alerts_enabled', true)) {
            return [];
        }

        $report = $report ?? $this->report();
        $byKey = collect($report)->keyBy('key');
        $alerts = [];

        foreach (['disk' => 'مساحة القرص', 'backup' => 'آخر نسخة احتياطيّة', 'schedule' => 'الجدولة'] as $key => $label) {
            $row = $byKey->get($key);

            if ($row && $row['state'] === 'danger') {
                $alerts[] = [
                    'key' => $key,
                    'level' => 'danger',
                    'message' => $label.': '.$row['value'].' — '.$row['hint'],
                ];
            }
        }

        return $alerts;
    }

    /**
     * إنشاء إشعار للأدمن عند تجاوز العتبات — مع **تبريد** حتى لا يتحوّل التنبيه
     * إلى ضجيج يُهمَل. ويمرّ عبر بوّابة الإشعارات الموحّدة إن كانت موجودة.
     *
     * @return int عدد التنبيهات التي أُرسِلت فعلًا
     */
    public function dispatchAlerts(array $alerts, ?User $actor = null): int
    {
        if ($alerts === []) {
            return 0;
        }

        $cooldown = max(1, (int) setting('system.health.alert_cooldown_minutes', 180));
        $state = setting('system.health.last_alert_at', []);
        $state = is_array($state) ? $state : [];
        $now = time();
        $sent = 0;

        foreach ($alerts as $alert) {
            $last = (int) ($state[$alert['key']] ?? 0);

            if ($now - $last < $cooldown * 60) {
                continue;
            }

            $state[$alert['key']] = $now;
            $sent++;

            $this->audit->record($actor, 'ops.system.alert', [
                'key' => $alert['key'],
                'message' => $alert['message'],
            ], 'ops.system');

            $this->notify($alert);
        }

        if ($sent > 0) {
            $this->settings->put('system.health.last_alert_at', $state);
        }

        return $sent;
    }

    public function runCheck(?User $actor): array
    {
        $report = $this->report();
        $alerts = $this->alerts($report);
        $sent = $this->dispatchAlerts($alerts, $actor);

        $this->settings->put('system.health.last_check_at', now()->toDateTimeString());

        $this->audit->record($actor, 'ops.system.health_checked', [
            'state' => $this->overallState($report),
            'alerts' => count($alerts),
            'notified' => $sent,
        ], 'ops.system');

        return ['report' => $report, 'alerts' => $alerts, 'notified' => $sent];
    }

    public function lastCheckAt(): ?string
    {
        $value = (string) setting('system.health.last_check_at', '');

        return $value !== '' ? $value : null;
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * بوّابة الإشعارات الموحّدة (2.8) — وتُستدعى **إن وُجدت** فقط، فلا يسقط
     * فحصُ الصحّة لو كان مجال الإشعارات لم يُبنَ بعد على هذا التنصيب.
     */
    private function notify(array $alert): void
    {
        $notifier = 'App\Services\Notifications\Notifier';

        if (! class_exists($notifier)) {
            return;
        }

        foreach ($this->recipients() as $user) {
            $notifier::send(
                $user,
                (string) setting('system.health.alert_category', 'system'),
                (string) setting('system.health.alert_title', 'تنبيه نظام'),
                $alert['message'],
                route('admin.ops.system'),
            );
        }
    }

    /**
     * مَن يستحقّ التنبيه؟ مَن يملك `system_health.view` — لا كلّ الأدمنز،
     * فالتنبيه الذي يصل لمن لا يملك التصرّف فيه ضجيجٌ لا فائدة.
     *
     * @return Collection<int, User>
     */
    private function recipients(): Collection
    {
        $limit = max(1, (int) setting('system.health.alert_max_recipients', 10));

        $direct = DB::table('permission_user')
            ->join('permissions', 'permissions.id', '=', 'permission_user.permission_id')
            ->where('permissions.key', 'system_health.view')
            ->where('permission_user.effect', 'allow')
            ->pluck('permission_user.user_id');

        $viaRoles = DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permissions.key', 'system_health.view')
            ->where('permission_role.effect', 'allow')
            ->pluck('role_user.user_id');

        $ids = $direct->merge($viaRoles)->unique()->take($limit * 3)->all();

        if ($ids === []) {
            return collect();
        }

        return User::query()->whereIn('id', $ids)->limit($limit)->get();
    }

    private function row(string $key, string $label, string $value, string $state, string $hint, array $extra = []): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'state' => $state, 'hint' => $hint] + $extra;
    }

    private function scheduleLastRun(): ?int
    {
        $recorded = (string) setting('system.schedule.last_run_at', '');

        if ($recorded !== '' && ($timestamp = strtotime($recorded))) {
            return $timestamp;
        }

        // بديل عمليّ: أحدث ملفّ قفل تكتبه الجدولة في مجلّد الإطار
        $newest = null;

        foreach (glob(storage_path('framework').DIRECTORY_SEPARATOR.'schedule-*') ?: [] as $file) {
            $newest = max($newest ?? 0, (int) @filemtime($file));
        }

        return $newest ?: null;
    }

    private function humanHours(float $hours): string
    {
        if ($hours < 1) {
            return max(1, (int) round($hours * 60)).' دقيقة';
        }

        if ($hours < 48) {
            return rtrim(rtrim(number_format($hours, 1), '0'), '.').' ساعة';
        }

        return (int) round($hours / 24).' يوم';
    }
}
