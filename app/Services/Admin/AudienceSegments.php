<?php

namespace App\Services\Admin;

use App\Models\AdAudience;
use App\Models\Announcement;
use App\Models\User;
use App\Support\Scope\ScopeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * شرائح الجمهور (12.13 · 24.1) — شريحة تُبنى بشروط، تُحفَظ باسم، وتُعاد الاستفادة
 * منها في الإشعارات والمكافآت والفعاليّات بدل إعادة بناء نفس الفلاتر كلّ مرّة.
 *
 * **النوع فرقٌ في السلوك لا تسمية:**
 * - **ديناميكيّة:** الشرط هو الشريحة — تُعاد حسبتها كلّ مرّة، فمن استوفى الشرط
 *   بعد الحفظ دخل، ومن خرج عنه خرج.
 * - **ثابتة:** تُجمَّد **قائمة أعضائها** لحظة الحفظ في `audience_segment_members`،
 *   فلا يتغيّر عددها بتغيّر بيانات المستخدمين بعدها أبدًا.
 *
 * و**حصر النطاق (12.2.1-ب) يبقى مطبَّقًا في كلّ مسار**: مَن يرى فريقه وحده لا
 * يبني شريحةً بالمنصّة كلّها ثمّ يخاطبها — لا وقت البناء ولا وقت الإرسال، لأنّ
 * الشريحة المحفوظة تُحلّ بنطاق **صاحبها** لا بنطاق مَن يستعملها لاحقًا.
 */
class AudienceSegments
{
    /** أنواع الشرائح — الفرق سلوكيّ، والتسمية من الإعدادات (2.13). */
    public const TYPE_DYNAMIC = 'dynamic';

    public const TYPE_STATIC = 'static';

    /** وسم يميّز شرائح المستخدمين عن شرائح الإعلانات في نفس الجدول. */
    public const KIND = 'user_segment';

    /**
     * كاش نتائج `contains()` داخل الطلب الواحد — الفيد يسأل عن نفس الشريحة
     * لعشرات المنشورات، فبلا كاش يتحوّل السؤال إلى استعلامٍ لكلّ منشور.
     *
     * @var array<string, bool>
     */
    private array $containsCache = [];

    /**
     * المعايير المنصوصة في 12.13 — **قائمة مقفولة** لا شروط حرّة.
     *
     * `type`: ids (قائمة معرّفات) · keys (قائمة مفاتيح) · range (من/إلى) · days (خلال X يوم).
     *
     * @return array<string, array{label: string, type: string}>
     */
    public static function criteria(): array
    {
        $labels = setting('admin.segments.criteria_labels', []);
        $labels = is_array($labels) ? $labels : [];

        $shape = [
            'path' => 'ids',
            'course' => 'ids',
            'role' => 'keys',
            'country' => 'ids',
            'governorate' => 'ids',
            'status' => 'keys',
            'xp' => 'range',
            'tickets' => 'range',
            'last_active_days' => 'days',
            'registered_days' => 'days',
        ];

        $out = [];

        foreach ($shape as $field => $type) {
            $out[$field] = [
                'label' => (string) ($labels[$field] ?? $field),
                'type' => $type,
            ];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function types(): array
    {
        $types = setting('admin.segments.types', [
            self::TYPE_DYNAMIC => 'ديناميكيّة (تُحدَّث تلقائيًّا)',
            self::TYPE_STATIC => 'ثابتة (تُجمَّد الآن)',
        ]);

        return is_array($types) && $types !== [] ? $types : [self::TYPE_DYNAMIC => 'ديناميكيّة', self::TYPE_STATIC => 'ثابتة'];
    }

    /** روابط منطق التجميع (AND/OR) — نصّ عربيّ من الإعدادات لا كود. */
    public static function matchModes(): array
    {
        $modes = setting('admin.segments.match_modes', ['all' => 'كلّ الشروط (AND)', 'any' => 'أيّ شرط (OR)']);

        return is_array($modes) && $modes !== [] ? $modes : ['all' => 'كلّ الشروط (AND)', 'any' => 'أيّ شرط (OR)'];
    }

    // ------------------------------------------------------------- القراءة والعرض

    /**
     * قائمة الشرائح مع فلاترها الثلاثة الظاهرة (2.15): النوع · الحالة · مستخدَمة.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AdAudience>
     */
    public function all(array $filters = []): Collection
    {
        $segments = AdAudience::query()
            ->where('kind', self::KIND)
            ->when(($q = trim((string) ($filters['q'] ?? ''))) !== '', fn ($b) => $b->where('name', 'like', '%'.$q.'%'))
            ->when(isset(self::types()[(string) ($filters['type'] ?? '')]), fn ($b) => $b->where('segment_type', (string) $filters['type']))
            // الحالة: نشطة (الافتراضيّ) · مؤرشفة · الكلّ — والأرشفة بدل الحذف (12.13)
            ->when(($filters['state'] ?? '') === 'archived', fn ($b) => $b->whereNotNull('archived_at'))
            ->when(! in_array($filters['state'] ?? '', ['archived', 'all'], true), fn ($b) => $b->whereNull('archived_at'))
            ->orderByDesc('id')
            ->limit((int) setting('admin.segments.per_page', 20))
            ->get();

        if (($used = (string) ($filters['used'] ?? '')) === '') {
            return $segments;
        }

        return $segments
            ->filter(fn (AdAudience $s) => $used === 'used' ? $this->usage($s)->isNotEmpty() : $this->usage($s)->isEmpty())
            ->values();
    }

    /**
     * بناء استعلام الشريحة من شرطها — بمنطق **AND/OR ومجموعات** (12.13).
     *
     * ومع `$viewer` تُحصَر الشريحة **بنطاقه** (12.2.1-ب).
     */
    public function query(array $rule, ?User $viewer = null): Builder
    {
        $rule = $this->normalize($rule);

        $query = User::query()
            ->when($viewer !== null, fn ($q) => app(ScopeFilter::class)->applyToUsers($q, $viewer, 'user_segments.list'));

        $groups = array_values(array_filter(
            $rule['groups'],
            fn (array $group) => $this->conditionsOf($group) !== [],
        ));

        if ($groups === []) {
            return $query;
        }

        $outer = $rule['match'] === 'any' ? 'orWhere' : 'where';

        $query->where(function (Builder $wrapper) use ($groups, $outer) {
            foreach ($groups as $index => $group) {
                $method = $index === 0 ? 'where' : $outer;

                $wrapper->{$method}(function (Builder $inner) use ($group) {
                    $this->applyGroup($inner, $group);
                });
            }
        });

        return $query;
    }

    public function count(array $rule, ?User $viewer = null): int
    {
        return min($this->query($rule, $viewer)->count(), $this->maxMembers());
    }

    /** عيّنة أعضاء للمعاينة اللحظيّة — عددها إعداد لا رقم محروق (12.13). */
    public function preview(array $rule, ?User $viewer = null): Collection
    {
        if (! setting('admin.segments.show_member_sample', true)) {
            return collect();
        }

        return $this->query($rule, $viewer)
            ->latest('id')
            ->limit((int) setting('admin.segments.preview_rows', 10))
            ->get();
    }

    /**
     * ⭐ أعضاء شريحةٍ محفوظة — **وهنا يظهر فرق النوع**:
     * الثابتة من قائمتها المجمَّدة، والديناميكيّة من إعادة حساب الشرط.
     *
     * @return Builder<User>
     */
    public function membersQuery(AdAudience $segment): Builder
    {
        if ($this->isStatic($segment)) {
            return User::query()->whereIn(
                'users.id',
                DB::table('audience_segment_members')->where('ad_audience_id', $segment->id)->select('user_id'),
            );
        }

        // الشريحة الديناميكيّة تُحلّ بنطاق **صاحبها** لا بنطاق مَن يستعملها (12.2.1-ب)
        return $this->query((array) $segment->rule, $this->owner($segment));
    }

    public function memberCount(AdAudience $segment): int
    {
        return min($this->membersQuery($segment)->count(), $this->maxMembers());
    }

    /** أعضاء الشريحة كما تُحلّ **على الخادم لحظة الإرسال** (12.6-أ · 12.13). */
    public function members(AdAudience $segment, ?int $limit = null): Collection
    {
        return $this->membersQuery($segment)
            ->limit($limit ?? $this->maxMembers())
            ->get();
    }

    /** هل هذا المستخدم داخل هذه الشريحة الآن؟ (يستعمله فيد التعليمات) */
    public function contains(AdAudience $segment, User $user): bool
    {
        $key = $segment->id.':'.$user->id;

        if (array_key_exists($key, $this->containsCache)) {
            return $this->containsCache[$key];
        }

        return $this->containsCache[$key] = $this->membersQuery($segment)
            ->whereKey($user->id)
            ->exists();
    }

    public function isStatic(AdAudience $segment): bool
    {
        return (string) $segment->segment_type === self::TYPE_STATIC;
    }

    // ------------------------------------------------------------------- الكتابة

    /**
     * حفظ شريحة (إنشاء أو تعديل).
     *
     * والثابتة **تُجمَّد لحظتها**: نكتب أعضاءها صفوفًا، فيبقى عددها كما هو مهما
     * تغيّرت بيانات المستخدمين بعدها — وهذا هو الفرق الحقيقيّ عن الديناميكيّة.
     *
     * @param  array<string, mixed>  $rule
     */
    public function save(
        string $name,
        array $rule,
        string $type = self::TYPE_DYNAMIC,
        ?User $actor = null,
        ?AdAudience $segment = null,
        ?string $description = null,
    ): AdAudience {
        $type = $this->normalizeType($type);
        $rule = $this->normalize($rule);

        $payload = [
            'name' => $name,
            'description' => $description,
            'kind' => self::KIND,
            'segment_type' => $type,
            'rule' => $rule,
            'is_active' => true,
        ];

        if ($segment === null) {
            $payload['created_by'] = $actor?->id;
            $segment = AdAudience::create($payload);
        } else {
            $segment->update($payload);
        }

        return $this->rebuild($segment->refresh(), $actor);
    }

    /**
     * إعادة بناء الشريحة: الثابتة تُجمَّد من جديد، والديناميكيّة يُحدَّث عدّادها.
     * والنطاق المستعمَل هو نطاق **صاحب الشريحة** أو مَن يعيد بناءها إن غاب.
     */
    public function rebuild(AdAudience $segment, ?User $actor = null): AdAudience
    {
        $viewer = $this->owner($segment) ?? $actor;

        if ($this->isStatic($segment)) {
            $ids = $this->query((array) $segment->rule, $viewer)
                ->limit($this->maxMembers())
                ->pluck('id');

            DB::table('audience_segment_members')->where('ad_audience_id', $segment->id)->delete();

            $now = now();

            $ids->chunk(500)->each(function (Collection $chunk) use ($segment, $now) {
                DB::table('audience_segment_members')->insert(
                    $chunk->map(fn ($id) => [
                        'ad_audience_id' => $segment->id,
                        'user_id' => (int) $id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });

            $segment->update(['size' => $ids->count(), 'frozen_at' => $now, 'last_built_at' => $now]);

            return $segment->refresh();
        }

        $segment->update([
            'size' => $this->count((array) $segment->rule, $viewer),
            'frozen_at' => null,
            'last_built_at' => now(),
        ]);

        return $segment->refresh();
    }

    /** تكرار الشريحة كنسخةٍ مستقلّة — الثابتة تُجمَّد من جديد لا تَرِث أعضاء أبيها. */
    public function duplicate(AdAudience $segment, ?User $actor = null): AdAudience
    {
        $copy = $segment->replicate(['created_at', 'updated_at', 'frozen_at', 'archived_at']);
        $copy->name = $segment->name.(string) setting('admin.segments.duplicate_suffix', ' — نسخة');
        $copy->archived_at = null;
        $copy->frozen_at = null;
        $copy->created_by = $actor?->id ?? $segment->created_by;
        $copy->save();

        return $this->rebuild($copy, $actor);
    }

    /** الأرشفة بدل الحذف — Toggle في الاتّجاهين (12.13). */
    public function toggleArchive(AdAudience $segment): AdAudience
    {
        $segment->update([
            'archived_at' => $segment->archived_at ? null : now(),
            'is_active' => (bool) $segment->archived_at,
        ]);

        return $segment->refresh();
    }

    // -------------------------------------------------------------------- الاستخدام

    /**
     * «مستخدَمة في X مكان» — **رقمٌ محسوب من الاستخدام الفعليّ** لا نصّ ثابت،
     * وكلّ سطرٍ فيه رابطُه فيصل الأدمن للمكان بضغطة (12.13).
     *
     * @return Collection<int, array{label: string, url: string}>
     */
    public function usage(AdAudience $segment): Collection
    {
        return Announcement::query()
            ->where('audience', 'like', '%"segment"%')
            ->latest('id')
            ->limit((int) setting('admin.segments.usage_rows', 10))
            ->get(['id', 'title', 'audience'])
            ->filter(fn (Announcement $a) => in_array(
                (int) $segment->id,
                array_map('intval', (array) ($a->audience['ids'] ?? [])),
                true,
            ))
            ->map(fn (Announcement $a) => [
                'label' => (string) $a->title,
                'url' => route('admin.guidance.analytics', $a),
            ])
            ->values();
    }

    // --------------------------------------------------------------- الشرط ووصفه

    /**
     * توحيد شكل الشرط — ويقبل **الشكل القديم المسطّح** فلا تضيع شريحةٌ محفوظة
     * قبل باني المجموعات (الترحيل بلا فقد، 2.11-د).
     *
     * @param  array<string, mixed>  $rule
     * @return array{match: string, groups: array<int, array{match: string, conditions: array<int, array<string, mixed>>}>}
     */
    public function normalize(array $rule): array
    {
        if (isset($rule['groups']) && is_array($rule['groups'])) {
            return [
                'match' => $this->matchMode($rule['match'] ?? 'all'),
                'groups' => array_values(array_map(
                    fn ($group) => [
                        'match' => $this->matchMode(is_array($group) ? ($group['match'] ?? 'all') : 'all'),
                        'conditions' => $this->cleanConditions(is_array($group) ? (array) ($group['conditions'] ?? []) : []),
                    ],
                    $rule['groups'],
                )),
            ];
        }

        return ['match' => 'all', 'groups' => [['match' => 'all', 'conditions' => $this->legacyConditions($rule)]]];
    }

    /** ملخّص المعايير بالعربيّة — سطر واحد يشرح الشريحة (2.15-أ-8). */
    public function summary(array $rule): string
    {
        $rule = $this->normalize($rule);
        $criteria = self::criteria();
        $groupTexts = [];

        foreach ($rule['groups'] as $group) {
            $parts = [];

            foreach ($this->conditionsOf($group) as $condition) {
                $field = (string) $condition['field'];
                $label = $criteria[$field]['label'] ?? $field;
                $parts[] = $label.': '.$this->conditionText($condition);
            }

            if ($parts !== []) {
                $glue = $group['match'] === 'any' ? ' أو ' : ' و';
                $groupTexts[] = implode($glue, $parts);
            }
        }

        if ($groupTexts === []) {
            return (string) setting('admin.segments.summary_all', 'كلّ المستخدمين');
        }

        $outer = $rule['match'] === 'any' ? ' — أو — ' : ' — و — ';

        return implode($outer, array_map(
            fn ($text) => count($groupTexts) > 1 ? '('.$text.')' : $text,
            $groupTexts,
        ));
    }

    // ------------------------------------------------------------------ داخليّ

    private function maxMembers(): int
    {
        return max(1, (int) setting('admin.segments.max_members', 50000));
    }

    private function owner(AdAudience $segment): ?User
    {
        return $segment->created_by ? User::find($segment->created_by) : null;
    }

    private function normalizeType(string $type): string
    {
        if ($type === self::TYPE_STATIC) {
            return self::TYPE_STATIC;
        }

        // إطفاء الشرائح الديناميكيّة من الإعدادات يجعل كلّ جديدةٍ ثابتة — لا يعطّل الشاشة
        return setting('admin.segments.dynamic_enabled', true) ? self::TYPE_DYNAMIC : self::TYPE_STATIC;
    }

    private function matchMode(mixed $mode): string
    {
        return (string) $mode === 'any' ? 'any' : 'all';
    }

    /** @return array<int, array<string, mixed>> */
    private function conditionsOf(array $group): array
    {
        return array_values(array_filter(
            (array) ($group['conditions'] ?? []),
            fn ($condition) => is_array($condition) && isset(self::criteria()[(string) ($condition['field'] ?? '')]),
        ));
    }

    /**
     * تنظيف الشروط الواردة من الفورم: المعيار من القائمة المقفولة، والقيمة الفاضية
     * تُسقِط الشرط كلّه — فلا يُحفَظ شرطٌ لا يعني شيئًا ثمّ يُقاس عليه عدد.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cleanConditions(array $conditions): array
    {
        $criteria = self::criteria();
        $max = (int) setting('admin.segments.max_conditions', 8);
        $clean = [];

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            $field = (string) ($condition['field'] ?? '');

            if (! isset($criteria[$field])) {
                continue;
            }

            $shaped = match ($criteria[$field]['type']) {
                'ids' => $this->shapeList($field, $condition, 'intval'),
                'keys' => $this->shapeList($field, $condition, 'strval'),
                'range' => $this->shapeRange($field, $condition),
                default => $this->shapeDays($field, $condition),
            };

            if ($shaped !== null) {
                $clean[] = $shaped;
            }

            if (count($clean) >= $max) {
                break;
            }
        }

        return $clean;
    }

    /** @return array<string, mixed>|null */
    private function shapeList(string $field, array $condition, string $caster): ?array
    {
        $values = collect((array) ($condition['values'] ?? []))
            ->map(fn ($value) => $caster($value))
            ->filter(fn ($value) => $value !== '' && $value !== 0)
            ->unique()
            ->values()
            ->all();

        return $values === [] ? null : ['field' => $field, 'values' => $values];
    }

    /** @return array<string, mixed>|null */
    private function shapeRange(string $field, array $condition): ?array
    {
        $min = ($condition['min'] ?? '') === '' ? null : (int) $condition['min'];
        $max = ($condition['max'] ?? '') === '' ? null : (int) $condition['max'];

        return $min === null && $max === null ? null : ['field' => $field, 'min' => $min, 'max' => $max];
    }

    /** @return array<string, mixed>|null */
    private function shapeDays(string $field, array $condition): ?array
    {
        $value = ($condition['value'] ?? '') === '' ? null : (int) $condition['value'];

        return $value === null || $value <= 0 ? null : ['field' => $field, 'value' => $value];
    }

    /**
     * ترجمة الشكل المسطّح القديم إلى شروطٍ بالشكل الجديد (2.11-د: بلا فقد).
     *
     * @return array<int, array<string, mixed>>
     */
    private function legacyConditions(array $rule): array
    {
        $conditions = [];

        if (($status = $rule['status'] ?? null) !== null && $status !== '') {
            $conditions[] = ['field' => 'status', 'values' => [(string) $status]];
        }

        if (($role = $rule['role'] ?? null) !== null && $role !== '') {
            $conditions[] = ['field' => 'role', 'values' => [(string) $role]];
        }

        if (($country = $rule['country_id'] ?? null) !== null && $country !== '') {
            $conditions[] = ['field' => 'country', 'values' => [(int) $country]];
        }

        if (($xp = $rule['min_xp'] ?? null) !== null && $xp !== '') {
            $conditions[] = ['field' => 'xp', 'min' => (int) $xp, 'max' => null];
        }

        if (($days = $rule['registered_days'] ?? null) !== null && $days !== '') {
            $conditions[] = ['field' => 'registered_days', 'value' => (int) $days];
        }

        return $conditions;
    }

    private function applyGroup(Builder $query, array $group): void
    {
        $method = $group['match'] === 'any' ? 'orWhere' : 'where';

        foreach ($this->conditionsOf($group) as $index => $condition) {
            $call = $index === 0 ? 'where' : $method;

            $query->{$call}(function (Builder $inner) use ($condition) {
                $this->applyCondition($inner, $condition);
            });
        }
    }

    private function applyCondition(Builder $query, array $condition): void
    {
        $field = (string) $condition['field'];
        $values = (array) ($condition['values'] ?? []);

        match ($field) {
            'status' => $query->whereIn('users.status', $values),
            'role' => $query->whereHas('roles', fn ($r) => $r->whereIn('roles.key', $values)),
            'country' => $query->whereIn('users.country_id', $values),
            'governorate' => $query->whereIn('users.governorate_id', $values),
            'course' => $query->whereHas('enrollments', fn ($e) => $e->whereIn('enrollments.course_id', $values)),
            'path' => $query->whereHas('enrollments', fn ($e) => $e->whereIn(
                'enrollments.course_id',
                DB::table('course_learning_path')->whereIn('learning_path_id', $values)->select('course_id'),
            )),
            'xp' => $this->applyRange($query, 'users.xp', $condition),
            'tickets' => $this->applyTickets($query, $condition),
            'last_active_days' => $query->where('users.last_seen_at', '>=', now()->subDays((int) $condition['value'])),
            'registered_days' => $query->where('users.created_at', '>=', now()->subDays((int) $condition['value'])),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function applyRange(Builder $query, string $column, array $condition): void
    {
        if (($condition['min'] ?? null) !== null) {
            $query->where($column, '>=', (int) $condition['min']);
        }

        if (($condition['max'] ?? null) !== null) {
            $query->where($column, '<=', (int) $condition['max']);
        }
    }

    /** نطاق التذاكر من رصيد المحفظة — رمز العملة إعدادٌ لا كودٌ محروق. */
    private function applyTickets(Builder $query, array $condition): void
    {
        $code = (string) setting('admin.segments.tickets_currency', 'tickets');

        $query->whereExists(function ($sub) use ($code, $condition) {
            $sub->from('wallet_balances')
                ->join('currencies', 'currencies.id', '=', 'wallet_balances.currency_id')
                ->whereColumn('wallet_balances.user_id', 'users.id')
                ->where('currencies.code', $code);

            if (($condition['min'] ?? null) !== null) {
                $sub->where('wallet_balances.balance', '>=', (int) $condition['min']);
            }

            if (($condition['max'] ?? null) !== null) {
                $sub->where('wallet_balances.balance', '<=', (int) $condition['max']);
            }
        });
    }

    private function conditionText(array $condition): string
    {
        if (isset($condition['values'])) {
            return implode('، ', array_map('strval', (array) $condition['values']));
        }

        if (array_key_exists('value', $condition)) {
            return (string) $condition['value'];
        }

        $min = $condition['min'] ?? null;
        $max = $condition['max'] ?? null;

        return match (true) {
            $min !== null && $max !== null => $min.' → '.$max,
            $min !== null => '≥ '.$min,
            default => '≤ '.$max,
        };
    }
}
