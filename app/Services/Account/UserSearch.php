<?php

namespace App\Services\Account;

use App\Models\User;
use App\Support\Scope\ScopeFilter;
use Illuminate\Support\Collection;

/**
 * صفحة البحث الكبيرة (الدستور 13.1 · 24.5).
 *
 * ملاحظة خصوصيّة حاكمة: **البحث بالبريد/الموبايل وسيلة وصول فقط** —
 * لذلك المطابقة عليهما **تامّة** (لا LIKE) منعًا للتصيّد،
 * والنتائج لا تحمل أيّ بيان حسّاس إطلاقًا.
 *
 * ⚠️ وكان البحث يمرّ **بلا صلاحيّة ولا نطاق**: نفس الاستعلام يعطي نفس القائمة
 * لكلّ من طلبها، وفيها **المحظور والموقوف والمرفوض ومَن هو تحت المراجعة**.
 * فصار كلّ استعلامٍ محصورًا بنطاق الباحث في `user_search.list` (12.2.1-ب)
 * بالمعيار الواحد `ScopeFilter` — بلا تعريفٍ ثانٍ للشجرة — ومقصورًا على
 * **الحساب الفعّال** وحده، وهو الحال الوحيد الذي ينصّ عليه كارت النتيجة (24.5).
 */
class UserSearch
{
    /** المفتاح الحاكم على تنفيذ البحث (12.2.2 — `user_search`) */
    public const PERMISSION = 'user_search.list';

    /** المفتاح الحاكم على فتح الصفحة نفسها (12.2.2 — `user_search`) */
    public const PAGE_PERMISSION = 'user_search.view';

    /** الحقول المحدَّدة — و«الكلّ» حصريّ فوقها */
    public const FIELDS = ['code', 'name', 'phone', 'email'];

    /** الأعمدة الوحيدة التي تخرج من هنا — بلا موبايل ولا بريد (13.1) */
    private const PUBLIC_COLUMNS = ['id', 'code', 'name', 'avatar_path', 'country_id', 'governorate_id', 'status'];

    public function __construct(private readonly ScopeFilter $scope) {}

    /** @return array<string, string> */
    public static function fieldLabels(): array
    {
        return [
            'code' => 'الكود',
            'name' => 'الاسم',
            'phone' => 'رقم الموبايل',
            'email' => 'البريد',
            'all' => 'الكلّ',
        ];
    }

    /**
     * ⭐ منطق «الكلّ» الحصريّ (13.1):
     * اختيار «الكلّ» يلغي باقي الحقول، واختيار أيّ حقل محدَّد يلغي «الكلّ».
     * و`$last` هو آخر مربّع ضغطه المستخدم — به يُحسَم التعارض بلا لبس.
     *
     * @param  array<int, string>  $fields
     * @return array<int, string>
     */
    public static function normalizeFields(array $fields, ?string $last = null): array
    {
        $allowed = [...self::FIELDS, 'all'];
        $fields = array_values(array_intersect(array_unique($fields), $allowed));

        // بلا اختيار = «الكلّ» (الافتراضيّ أبسط شيء — 2.15-أ-10)
        if ($fields === []) {
            return ['all'];
        }

        $hasAll = in_array('all', $fields, true);
        $specific = array_values(array_diff($fields, ['all']));

        if (! $hasAll) {
            return $specific;
        }

        if ($specific === []) {
            return ['all'];
        }

        // الاثنان معًا: آخر ضغطة تحسم — ولو لم تُعرَف فـ«الكلّ» يغلب لحصريّته
        return $last !== null && $last !== 'all' ? $specific : ['all'];
    }

    /** الحقول الفعليّة التي يُبحَث بها بعد فكّ «الكلّ» */
    public static function expand(array $normalized): array
    {
        return in_array('all', $normalized, true) ? self::FIELDS : $normalized;
    }

    /**
     * نتائج البحث — شريحةً بشريحة (تمرير تدريجيّ لا ترقيم صفحات، 2.15).
     *
     * @return Collection<int, User>
     */
    public function results(?User $viewer, string $query, array $fields, int $offset = 0, ?int $limit = null): Collection
    {
        $query = trim($query);
        $limit ??= self::pageSize();

        if (mb_strlen($query) < self::minLength()) {
            return collect();
        }

        return $this->builder($viewer, $query, $fields)
            ->skip(max(0, $offset))
            ->take($limit)
            ->get();
    }

    public function count(?User $viewer, string $query, array $fields): int
    {
        $query = trim($query);

        if (mb_strlen($query) < self::minLength()) {
            return 0;
        }

        return $this->builder($viewer, $query, $fields)->count();
    }

    public static function pageSize(): int
    {
        // تمرير تدريجيّ: 6 مستخدمين في المرّة (13.1)
        return max(1, (int) setting('account.search.page_size', 6));
    }

    public static function minLength(): int
    {
        return max(1, (int) setting('account.search.min_query_length', 2));
    }

    private function builder(?User $viewer, string $query, array $fields)
    {
        $fields = self::expand(self::normalizeFields($fields));

        $builder = User::query()
            ->select(self::PUBLIC_COLUMNS)
            ->with(['country:id,name_ar', 'governorate:id,name_ar'])
            /*
             | ⭐ ما لا يجوز ظهوره لا يُستَعلَم عنه أصلًا: كارت النتيجة في 24.5
             | حالته **«فعّال»** وحدها — فالمحظور والموقوف والمرفوض ومَن هو تحت
             | المراجعة خارج الدليل، لا مكتوبين بحالةٍ رماديّة.
             */
            ->where('status', 'active');

        // ⭐ النطاق إلزاميّ مع كلّ صلاحيّة (12.2.1-ب) — والباب وحده لا يكفي.
        // وبلا إسناد `allow` لا صفوف: `ScopeFilter` يردّ `1 = 0` لا الدليل كاملًا.
        $this->scope->applyToUsers($builder, $viewer, self::PERMISSION);

        return $builder
            ->where(function ($q) use ($query, $fields) {
                foreach ($fields as $field) {
                    match ($field) {
                        // مطابقة تامّة: وسيلة وصول لا وسيلة تصفّح (13.1)
                        'phone' => $q->orWhere('phone', $query),
                        'email' => $q->orWhere('email', $query),
                        'code' => $q->orWhere('code', 'like', $query.'%'),
                        'name' => $q->orWhere('name', 'like', '%'.$query.'%'),
                        default => null,
                    };
                }
            })
            ->orderBy('name');
    }
}
