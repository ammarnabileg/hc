<?php

namespace App\Services\Admin;

use App\Models\AdAudience;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * شرائح الجمهور (24.1) — شريحة تُبنى بشروط، تُحفَظ باسم، وتُعاد الاستفادة منها
 * في الإشعارات والمكافآت والفعاليّات بدل إعادة بناء نفس الفلاتر كلّ مرّة.
 */
class AudienceSegments
{
    /** معايير الشريحة — قائمة مقفولة تُعرَض بتسمياتها العربيّة */
    public const CRITERIA = [
        'status' => 'حالة الحساب',
        'role' => 'الدور',
        'country_id' => 'الدولة',
        'min_xp' => 'أدنى XP',
        'registered_days' => 'مسجَّل خلال (يوم)',
    ];

    public function all(): Collection
    {
        return AdAudience::orderByDesc('id')->get();
    }

    /**
     * بناء استعلام الشريحة من شروطها المحفوظة.
     *
     * ومع `$viewer` تُحصَر الشريحة **بنطاقه** (12.2.1-ب) — فمَن يرى فريقه وحده
     * لا يبني شريحةً بالمنصّة كلّها ثمّ يخاطبها.
     */
    public function query(array $rule, ?User $viewer = null)
    {
        return User::query()
            ->when($viewer !== null, fn ($q) => app(ScopeFilter::class)->applyToUsers($q, $viewer, 'user_segments.list'))
            ->when($rule['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($rule['role'] ?? null, fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('key', $role)))
            ->when($rule['country_id'] ?? null, fn ($q, $country) => $q->where('country_id', $country))
            ->when($rule['min_xp'] ?? null, fn ($q, $xp) => $q->where('xp', '>=', (int) $xp))
            ->when($rule['registered_days'] ?? null, fn ($q, $days) => $q->where('created_at', '>=', now()->subDays((int) $days)));
    }

    public function count(array $rule): int
    {
        return min($this->query($rule)->count(), (int) setting('admin.segments.max_members', 50000));
    }

    /** عيّنة أعضاء للمعاينة اللحظيّة — عددها إعداد لا رقم محروق */
    public function preview(array $rule): Collection
    {
        return $this->query($rule)
            ->latest('id')
            ->limit((int) setting('admin.segments.preview_rows', 10))
            ->get();
    }

    public function save(string $name, array $rule): AdAudience
    {
        $rule = array_filter($rule, fn ($value) => $value !== null && $value !== '');

        return AdAudience::updateOrCreate(['name' => $name], [
            'kind' => 'retargeting',
            'rule' => $rule,
            'size' => $this->count($rule),
            'last_built_at' => now(),
            'is_active' => true,
        ]);
    }

    /** ملخّص المعايير بالعربيّة — سطر واحد يشرح الشريحة (2.15-أ-8) */
    public function summary(array $rule): string
    {
        $parts = [];

        foreach (self::CRITERIA as $key => $label) {
            if (($rule[$key] ?? null) !== null && $rule[$key] !== '') {
                $value = $key === 'status'
                    ? (UserDirectory::STATUSES[$rule[$key]] ?? $rule[$key])
                    : $rule[$key];

                $parts[] = $label.': '.$value;
            }
        }

        return $parts === [] ? 'كلّ المستخدمين' : implode(' · ', $parts);
    }
}
