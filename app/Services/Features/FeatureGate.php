<?php

namespace App\Services\Features;

use App\Models\User;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * ⭐ **قارئُ المزايا الوحيد** — كلّ سؤال «هل هذه الميزة شغّالة لهذا المستخدم؟»
 * يمرّ من هنا: الحارس والشاشة والقالب. قارئان يعنيان أن تختلف إجابةُ الخادم عن
 * إجابة الواجهة، وحينها يُخفى الزرّ ويبقى المسار مفتوحًا — وهو ما تمنعه 24.3.
 *
 * **ترتيب الحسم (الأخصّ يعلو):**
 *   1) Override شريحة (أضيق: أشخاصٌ بأعيانهم)
 *   2) Override دور
 *   3) الحالة العامّة `enabled`
 * وداخل المستوى الواحد: **الإيقاف يغلب** — فتعارضُ دورين لا يفتح بابًا بالصدفة.
 *
 * ثمّ — وبعد الحسم بالإيقاف — يأتي سؤالٌ ثانٍ مستقلّ: **«مَن يراها أثناء
 * الإيقاف»** (لا أحد · الأدمن فقط · أدوار محدّدة). وهو استثناءُ رؤية لا تشغيل.
 */
class FeatureGate
{
    private const CACHE_KEY = 'feature_flags';

    /** @var array<int, array{roles:list<int>, segments:list<int>}> ذاكرة الطلب */
    private array $memberships = [];

    public function __construct(private readonly AccessEngine $access) {}

    /** هل يحصل هذا المستخدم على الميزة؟ — الإجابة القصيرة التي تستهلكها المنصّة */
    public function allows(string $key, ?User $user = null): bool
    {
        return (bool) $this->state($key, $user)['allowed'];
    }

    /**
     * الحالة الكاملة — يستهلكها الحارس والشاشة.
     *
     * @return array{
     *   known:bool, allowed:bool, enabled:bool, scope:string,
     *   exempt:bool, behavior:string, message:string, flag:array<string,mixed>|null
     * }
     */
    public function state(string $key, ?User $user = null): array
    {
        $flag = $this->flags()[$key] ?? null;

        // ميزةٌ بلا صفّ = ميزةٌ لم يُعلَن عنها بعد ⟵ شغّالة. والصمت لا يقفل بابًا.
        if ($flag === null) {
            return [
                'known' => false, 'allowed' => true, 'enabled' => true, 'scope' => 'global',
                'exempt' => false, 'behavior' => $this->defaultBehavior(), 'message' => '', 'flag' => null,
            ];
        }

        [$enabled, $scope] = $this->resolve($flag, $user);

        if ($enabled) {
            return [
                'known' => true, 'allowed' => true, 'enabled' => true, 'scope' => $scope,
                'exempt' => false, 'behavior' => $this->behaviorOf($flag), 'message' => $this->messageOf($flag), 'flag' => $flag,
            ];
        }

        $exempt = $this->seesWhileOff($flag, $user);

        return [
            'known' => true, 'allowed' => $exempt, 'enabled' => false, 'scope' => $scope,
            'exempt' => $exempt, 'behavior' => $this->behaviorOf($flag), 'message' => $this->messageOf($flag), 'flag' => $flag,
        ];
    }

    /**
     * الحسم: العامّ ثمّ الـOverride الأخصّ.
     *
     * @param  array<string, mixed>  $flag
     * @return array{0:bool,1:string}
     */
    private function resolve(array $flag, ?User $user): array
    {
        $enabled = (bool) $flag['enabled'];

        if (! $user || $flag['overrides'] === []) {
            return [$enabled, 'global'];
        }

        $membership = $this->membershipOf($user);

        foreach (['segment', 'role'] as $type) {
            $ids = $type === 'segment' ? $membership['segments'] : $membership['roles'];
            $decision = null;

            foreach ($flag['overrides'] as $override) {
                if ($override['scope_type'] !== $type || ! in_array((int) $override['scope_id'], $ids, true)) {
                    continue;
                }

                // الإيقاف يغلب داخل المستوى الواحد — لا يُفتَح بابٌ بتعارضٍ
                $decision = $decision === null ? (bool) $override['enabled'] : ($decision && (bool) $override['enabled']);
            }

            if ($decision !== null) {
                return [$decision, $type];
            }
        }

        return [$enabled, 'global'];
    }

    /**
     * **مَن يراها أثناء الإيقاف** — يُقيَّم على الخادم لا بإخفاء زرّ (24.3).
     *
     * @param  array<string, mixed>  $flag
     */
    private function seesWhileOff(array $flag, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return match ((string) $flag['visibility']) {
            'admins' => $this->access->opensAdminPanel($user),
            'roles' => array_intersect($this->membershipOf($user)['roles'], array_map('intval', $flag['visible_roles'])) !== [],
            default => false,
        };
    }

    /** @param array<string, mixed> $flag */
    private function behaviorOf(array $flag): string
    {
        $behavior = (string) $flag['behavior'];

        return $behavior !== '' ? $behavior : $this->defaultBehavior();
    }

    private function defaultBehavior(): string
    {
        $behavior = (string) setting('features.disabled_behavior', 'hide');

        return in_array($behavior, ['hide', 'message'], true) ? $behavior : 'hide';
    }

    /**
     * نصّ ما يراه المستخدم بدلها (ع/إ) — نصّ الميزة أوّلًا ثمّ الافتراضيّ العامّ.
     *
     * @param  array<string, mixed>  $flag
     */
    private function messageOf(array $flag): string
    {
        $english = app()->getLocale() === 'en';

        $own = trim((string) ($english ? ($flag['message_en'] ?? '') : ($flag['message_ar'] ?? '')));

        if ($own !== '') {
            return $own;
        }

        return trim((string) ($english
            ? setting('features.disabled_message_en', 'This feature is paused for a moment — it will be back soon.')
            : setting('features.disabled_message', 'الميزة دي متوقّفة مؤقّتًا — هترجع قريب.')));
    }

    /**
     * أدوار المستخدم وشرائحه — مرّةً واحدة في الطلب.
     *
     * @return array{roles:list<int>, segments:list<int>}
     */
    private function membershipOf(User $user): array
    {
        if (isset($this->memberships[$user->id])) {
            return $this->memberships[$user->id];
        }

        $roles = DB::table('role_user')->where('user_id', $user->id)->pluck('role_id')
            ->map(fn ($id) => (int) $id)->values()->all();

        $segments = DB::table('audience_segment_members')->where('user_id', $user->id)->pluck('ad_audience_id')
            ->map(fn ($id) => (int) $id)->values()->all();

        return $this->memberships[$user->id] = ['roles' => $roles, 'segments' => $segments];
    }

    /**
     * كلّ المزايا وOverrideاتها — كاشٌ واحد كما تفعل `setting()` تمامًا.
     *
     * @return array<string, array<string, mixed>>
     */
    public function flags(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                $overrides = DB::table('feature_flag_overrides')->get()->groupBy('feature_flag_id');

                return DB::table('feature_flags')->get()->mapWithKeys(function ($row) use ($overrides) {
                    $flag = (array) $row;
                    $flag['visible_roles'] = json_decode((string) ($flag['visible_roles'] ?? ''), true) ?: [];
                    $flag['overrides'] = collect($overrides->get($flag['id'], []))
                        ->map(fn ($o) => (array) $o)->values()->all();

                    return [$flag['key'] => $flag];
                })->all();
            });
        } catch (Throwable) {
            // قبل الترحيل/التنصيب لا جدولَ مزايا — والغياب لا يقفل المنصّة
            return [];
        }
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
