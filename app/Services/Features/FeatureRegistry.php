<?php

namespace App\Services\Features;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * دماغ شاشة «مفاتيح المزايا» (24.3): الجدول والفلاتر والتبديل والـOverride
 * والـAudit والتصدير/الاستيراد و↺ Reset.
 *
 * والقراءة كلّها تمرّ من `FeatureGate` — فالشاشة لا تحسب الحالة بنفسها، وإلّا
 * انفصل ما تعرضه عمّا يفرضه الخادم.
 */
class FeatureRegistry
{
    public function __construct(private readonly FeatureGate $gate) {}

    /** لافتات المجموعات التسع — من الإعدادات لا من الكود (2.13) */
    public function groupLabels(): array
    {
        $labels = setting('features.groups', []);

        return is_array($labels) ? $labels : [];
    }

    public function groupLabel(string $group): string
    {
        return (string) ($this->groupLabels()[$group] ?? $group);
    }

    /**
     * صفوف الجدول بأعمدتها الثمانية.
     *
     * @param  array{q?:string, group?:string, status?:string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(array $filters = []): Collection
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $group = (string) ($filters['group'] ?? '');
        $status = (string) ($filters['status'] ?? '');

        return collect($this->gate->flags())
            ->values()
            ->map(fn (array $flag) => $this->row($flag))
            ->when($q !== '', fn ($rows) => $rows->filter(
                fn (array $row) => str_contains($row['key'], $q) || str_contains((string) $row['label_ar'], $q)
            ))
            ->when($group !== '', fn ($rows) => $rows->filter(fn (array $row) => $row['group'] === $group))
            ->when($status !== '', fn ($rows) => $rows->filter(fn (array $row) => $row['status'] === $status))
            ->sortBy(fn (array $row) => [array_search($row['group'], FeatureCatalog::GROUPS, true), $row['key']])
            ->values();
    }

    /**
     * الحالة المعروضة: مشتغّل · موقوف · **جزئيّ** (له Override يخالف العامّ).
     *
     * @param  array<string, mixed>  $flag
     * @return array<string, mixed>
     */
    private function row(array $flag): array
    {
        $enabled = (bool) $flag['enabled'];
        $overrides = $flag['overrides'];

        $partial = collect($overrides)->contains(fn (array $o) => (bool) $o['enabled'] !== $enabled);

        return [
            'id' => (int) $flag['id'],
            'key' => (string) $flag['key'],
            'group' => (string) $flag['group'],
            'label_ar' => (string) $flag['label_ar'],
            'label_en' => (string) ($flag['label_en'] ?? ''),
            'enabled' => $enabled,
            /*
             | «شارة تجريبيّة **للمزايا الجديدة**» (24.3): والجِدَّة تُحسَب من عمر
             | الصفّ لا تُوسَم بيدٍ — فوسمٌ يدويّ يبقى بعد سنة على ميزةٍ استقرّت،
             | والشارة تفقد معناها. والعتبة إعدادٌ لا رقمٌ محروق.
             */
            'is_beta' => (bool) $flag['is_beta'] || $this->isNew($flag),
            'status' => $partial ? 'partial' : ($enabled ? 'on' : 'off'),
            'visibility' => (string) $flag['visibility'],
            'visible_roles' => array_map('intval', $flag['visible_roles']),
            'behavior' => (string) $flag['behavior'],
            'message_ar' => (string) ($flag['message_ar'] ?? ''),
            'message_en' => (string) ($flag['message_en'] ?? ''),
            'notify_affected' => (bool) $flag['notify_affected'],
            'disabled_reason' => (string) ($flag['disabled_reason'] ?? ''),
            'disabled_at' => $flag['disabled_at'],
            'last_toggled_at' => $flag['last_toggled_at'],
            'last_toggled_by' => $flag['last_toggled_by'],
            'overrides' => $overrides,
            'routes' => FeatureCatalog::definitions()[$flag['key']]['routes'] ?? [],
        ];
    }

    /**
     * ميزةٌ «جديدة» = عمر صفّها أقلّ من العتبة (`features.beta_days`).
     *
     * @param  array<string, mixed>  $flag
     */
    private function isNew(array $flag): bool
    {
        $days = (int) setting('features.beta_days', 30);

        return $days > 0
            && $flag['created_at'] !== null
            && now()->diffInDays($flag['created_at'], true) < $days;
    }

    /** شارة عدد المزايا الموقوفة في الهيدر */
    public function pausedCount(): int
    {
        return collect($this->gate->flags())->filter(fn (array $f) => ! (bool) $f['enabled'])->count();
    }

    /**
     * مزايا موقوفة منذ أكثر من العتبة — «تنبيه الأدمن عند إيقاف ميزة > 24 ساعة».
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function longOutages(): Collection
    {
        if (! (bool) setting('features.alert_long_outage', true)) {
            return collect();
        }

        $hours = (int) setting('features.alert_after_hours', 24);

        return collect($this->gate->flags())
            ->filter(fn (array $f) => ! (bool) $f['enabled'] && $f['disabled_at'] !== null
                && now()->diffInHours($f['disabled_at'], true) >= $hours)
            ->map(fn (array $f) => $this->row($f))
            ->values();
    }

    // ============================================================== الكتابة

    /**
     * تبديل ميزة — ومعه بوب-أب الإيقاف كاملًا: **نصّ ما يراه المستخدم بدلها**
     * (ع/إ) + Toggle إشعار المتأثّرين + **سبب الإيقاف الذي يدخل الـAudit**.
     *
     * @param  array<string, mixed>  $payload
     * @return array{saved:bool, message:string}
     */
    public function toggle(string $key, bool $enabled, User $actor, array $payload = [], ?Request $request = null): array
    {
        $flag = $this->gate->flags()[$key] ?? null;

        if ($flag === null) {
            return ['saved' => false, 'message' => (string) setting('features.msg.unknown_feature', 'الميزة دي مش موجودة — حدّث الصفحة وجرّب تاني.')];
        }

        $reason = trim((string) ($payload['reason'] ?? ''));

        // سببُ الإيقاف إلزاميّ: سجلٌّ بلا سبب يخبرك «مَن ومتى» ولا يخبرك **لماذا**،
        // وهو أوّل ما يُسأل عنه بعد أسبوع (24.3 — «سبب الإيقاف يدخل الـAudit»).
        if (! $enabled && $reason === '') {
            return ['saved' => false, 'message' => (string) setting('features.msg.reason_required', 'اكتب سبب الإيقاف — هو اللي هيفضل في السجلّ ويفهّم اللي بعدك.')];
        }

        $before = $this->snapshot($flag);

        $update = [
            'enabled' => $enabled,
            'last_toggled_by' => $actor->id,
            'last_toggled_at' => now(),
            'updated_at' => now(),
        ];

        if (! $enabled) {
            $update['disabled_reason'] = $reason;
            $update['disabled_at'] = $flag['disabled_at'] ?? now();
            $update['long_outage_alerted_at'] = null;
            $update['message_ar'] = trim((string) ($payload['message_ar'] ?? ''));
            $update['message_en'] = trim((string) ($payload['message_en'] ?? ''));
            $update['notify_affected'] = (bool) ($payload['notify_affected'] ?? false);

            if (array_key_exists('behavior', $payload) && in_array($payload['behavior'], FeatureCatalog::BEHAVIORS, true)) {
                $update['behavior'] = (string) $payload['behavior'];
            }

            if (array_key_exists('visibility', $payload) && in_array($payload['visibility'], FeatureCatalog::VISIBILITY, true)) {
                $update['visibility'] = (string) $payload['visibility'];
                $update['visible_roles'] = json_encode(array_values(array_map('intval', (array) ($payload['visible_roles'] ?? []))));
            }
        } else {
            $update['disabled_at'] = null;
            $update['long_outage_alerted_at'] = null;
        }

        DB::table('feature_flags')->where('id', $flag['id'])->update($update);

        FeatureGate::forget();

        $this->log(
            $enabled ? 'feature_toggles.enable' : 'feature_toggles.disable',
            (int) $flag['id'],
            $before,
            $this->snapshot($this->gate->flags()[$key]) + ['reason' => $reason],
            $actor,
            $request,
        );

        if (! $enabled && ($update['notify_affected'] ?? false)) {
            $this->notifyAffected($key, $flag);
        }

        return [
            'saved' => true,
            'message' => (string) setting('features.msg.saved', 'اتحفظ ✓'),
        ];
    }

    /**
     * ضبط **النطاق**: Override لدور أو شريحة — أو رفعه بالعودة إلى «عامّ».
     *
     * @return array{saved:bool, message:string}
     */
    public function setOverride(string $key, string $scopeType, int $scopeId, ?bool $enabled, User $actor, ?Request $request = null): array
    {
        $flag = $this->gate->flags()[$key] ?? null;

        if ($flag === null || ! in_array($scopeType, FeatureCatalog::SCOPE_TYPES, true) || $scopeId <= 0) {
            return ['saved' => false, 'message' => (string) setting('features.msg.bad_scope', 'النطاق ده مش مظبوط — اختر دورًا أو شريحة موجودة.')];
        }

        $before = $this->snapshot($flag);

        if ($enabled === null) {
            DB::table('feature_flag_overrides')
                ->where('feature_flag_id', $flag['id'])
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->delete();
        } else {
            DB::table('feature_flag_overrides')->updateOrInsert(
                ['feature_flag_id' => $flag['id'], 'scope_type' => $scopeType, 'scope_id' => $scopeId],
                ['enabled' => $enabled, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        FeatureGate::forget();

        $this->log('feature_toggles.scope', (int) $flag['id'], $before, $this->snapshot($this->gate->flags()[$key]), $actor, $request);

        return ['saved' => true, 'message' => (string) setting('features.msg.saved', 'اتحفظ ✓')];
    }

    /** ↺ لميزةٍ واحدة: تعود شغّالةً عامّةً بلا Override ولا نصّ خاصّ */
    public function reset(string $key, User $actor, ?Request $request = null): array
    {
        $flag = $this->gate->flags()[$key] ?? null;

        if ($flag === null) {
            return ['saved' => false, 'message' => (string) setting('features.msg.unknown_feature', 'الميزة دي مش موجودة — حدّث الصفحة وجرّب تاني.')];
        }

        $before = $this->snapshot($flag);

        DB::table('feature_flag_overrides')->where('feature_flag_id', $flag['id'])->delete();
        DB::table('feature_flags')->where('id', $flag['id'])->update([
            'enabled' => true,
            'visibility' => 'none',
            'visible_roles' => null,
            'behavior' => '',
            'message_ar' => null,
            'message_en' => null,
            'notify_affected' => false,
            'disabled_reason' => null,
            'disabled_at' => null,
            'long_outage_alerted_at' => null,
            'last_toggled_by' => $actor->id,
            'last_toggled_at' => now(),
            'updated_at' => now(),
        ]);

        FeatureGate::forget();

        $this->log('feature_toggles.reset', (int) $flag['id'], $before, $this->snapshot($this->gate->flags()[$key]), $actor, $request);

        return ['saved' => true, 'message' => (string) setting('features.msg.saved', 'اتحفظ ✓')];
    }

    /** ↺ Reset الكلّ — كلّ المزايا تعود شغّالةً عامّة */
    public function resetAll(User $actor, ?Request $request = null): int
    {
        $keys = array_keys($this->gate->flags());

        foreach ($keys as $key) {
            $this->reset($key, $actor, $request);
        }

        return count($keys);
    }

    // ====================================================== تصدير/استيراد

    /** @return array<string, mixed> */
    public function export(): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'features' => collect($this->gate->flags())->map(fn (array $f) => [
                'key' => $f['key'],
                'enabled' => (bool) $f['enabled'],
                'visibility' => $f['visibility'],
                'visible_roles' => $f['visible_roles'],
                'behavior' => $f['behavior'],
                'message_ar' => $f['message_ar'],
                'message_en' => $f['message_en'],
                'overrides' => collect($f['overrides'])->map(fn (array $o) => [
                    'scope_type' => $o['scope_type'],
                    'scope_id' => (int) $o['scope_id'],
                    'enabled' => (bool) $o['enabled'],
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * الاستيراد **لا يخلق مزايا** — المفتاح المجهول يُتخطّى ويُعَدّ.
     * فملفٌّ قديم لا يزرع في المنصّة ميزةً لا يعرفها الكود.
     *
     * @param  array<string, mixed>  $payload
     * @return array{applied:int, skipped:int}
     */
    public function import(array $payload, User $actor, ?Request $request = null): array
    {
        $applied = 0;
        $skipped = 0;

        foreach ((array) ($payload['features'] ?? []) as $item) {
            $key = (string) ($item['key'] ?? '');
            $flag = $this->gate->flags()[$key] ?? null;

            if ($flag === null) {
                $skipped++;

                continue;
            }

            $before = $this->snapshot($flag);

            DB::table('feature_flags')->where('id', $flag['id'])->update([
                'enabled' => (bool) ($item['enabled'] ?? true),
                'visibility' => in_array($item['visibility'] ?? 'none', FeatureCatalog::VISIBILITY, true) ? $item['visibility'] : 'none',
                'visible_roles' => json_encode(array_values(array_map('intval', (array) ($item['visible_roles'] ?? [])))),
                'behavior' => in_array($item['behavior'] ?? '', FeatureCatalog::BEHAVIORS, true) ? $item['behavior'] : '',
                'message_ar' => $item['message_ar'] ?? null,
                'message_en' => $item['message_en'] ?? null,
                'disabled_at' => ((bool) ($item['enabled'] ?? true)) ? null : now(),
                'last_toggled_by' => $actor->id,
                'last_toggled_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('feature_flag_overrides')->where('feature_flag_id', $flag['id'])->delete();

            foreach ((array) ($item['overrides'] ?? []) as $override) {
                if (! in_array($override['scope_type'] ?? '', FeatureCatalog::SCOPE_TYPES, true)) {
                    continue;
                }

                DB::table('feature_flag_overrides')->insert([
                    'feature_flag_id' => $flag['id'],
                    'scope_type' => $override['scope_type'],
                    'scope_id' => (int) ($override['scope_id'] ?? 0),
                    'enabled' => (bool) ($override['enabled'] ?? true),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            FeatureGate::forget();

            $this->log('feature_toggles.import', (int) $flag['id'], $before, $this->snapshot($this->gate->flags()[$key]), $actor, $request);
            $applied++;
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    // ================================================================ Audit

    /**
     * سجلّ ميزةٍ بعينها — **مَن ومتى ولماذا**، مقروءًا من القاعدة.
     *
     * @return Collection<int, AuditLog>
     */
    public function auditFor(string $key): Collection
    {
        $flag = $this->gate->flags()[$key] ?? null;

        if ($flag === null) {
            return collect();
        }

        return AuditLog::query()
            ->with('user')
            ->where('auditable_type', 'feature_flag')
            ->where('auditable_id', $flag['id'])
            ->latest('id')
            ->limit((int) setting('features.audit.limit', 30))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $flag
     * @return array<string, mixed>
     */
    private function snapshot(array $flag): array
    {
        return [
            'key' => $flag['key'],
            'enabled' => (bool) $flag['enabled'],
            'visibility' => $flag['visibility'],
            'visible_roles' => $flag['visible_roles'],
            'behavior' => $flag['behavior'],
            'message_ar' => $flag['message_ar'],
            'overrides' => collect($flag['overrides'])
                ->map(fn (array $o) => $o['scope_type'].':'.$o['scope_id'].'='.((bool) $o['enabled'] ? '1' : '0'))
                ->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function log(string $action, int $flagId, array $old, array $new, User $actor, ?Request $request): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => 'feature_flag',
            'auditable_id' => $flagId,
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
        ]);
    }

    /**
     * إشعار المتأثّرين — المستخدمون الذين تُغلَق عنهم الميزة فعلًا.
     * ونقتصر على حدٍّ من الإعدادات: بثٌّ بلا حدّ يغرق الناس (12.6-ب).
     *
     * @param  array<string, mixed>  $flag
     */
    private function notifyAffected(string $key, array $flag): void
    {
        $limit = (int) setting('features.notify.max_recipients', 500);

        if ($limit <= 0) {
            return;
        }

        $title = (string) setting('features.notify.title', 'ميزة وقفت مؤقّتًا');
        $body = $this->gate->state($key)['message'];

        User::query()
            ->where('status', 'active')
            ->limit($limit)
            ->get()
            ->reject(fn (User $user) => $this->gate->allows($key, $user))
            ->each(fn (User $user) => Notifier::send(
                $user,
                (string) setting('features.notify.category', 'system'),
                $title,
                $body,
            ));
    }
}
