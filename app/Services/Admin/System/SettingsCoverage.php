<?php

namespace App\Services\Admin\System;

use App\Models\Setting;
use App\Services\Admin\Volunteer\SettingsCatalog;
use Illuminate\Support\Collection;

/**
 * تقرير تغطية الإعدادات — الحارس الدائم للقاعدة الذهبيّة 2.13.
 *
 * لماذا خدمة لا سكربت لمرّة؟ لأنّ المخالفة تعود مع كلّ ميزة جديدة: يزرع أحدهم
 * مجموعة إعدادات في سيدر وينسى تابها، فتصير مفاتيحُ في قاعدة البيانات لا يقدر
 * المالك على تعديلها. فحصٌ دائم يكشفها فورًا — بأمر artisan وباختبار يفشل.
 */
class SettingsCoverage
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    /**
     * كلّ مجموعة معروفة للمنصّة بعدد مفاتيحها — من قاعدة البيانات **ومن الكتالوج**.
     *
     * لماذا الكتالوج أيضًا؟ لأنّ مجموعةً مكتوبةً في الكود ولمّا تُزرَع بعد ستصير
     * يتيمةً أوّل ما تُزرَع — والأفضل أن يكتشفها الفحص قبل أن تصل قاعدة البيانات.
     *
     * @return array<string, int>
     */
    public function declaredGroups(): array
    {
        $counts = Setting::query()
            ->selectRaw('`group` as g, COUNT(*) as c')
            ->groupBy('g')
            ->pluck('c', 'g')
            ->map(fn ($c) => (int) $c)
            ->all();

        foreach (SettingsCatalog::all() as $row) {
            $counts[$row[0]] ??= 0;
        }

        arsort($counts);

        return $counts;
    }

    /**
     * الصورة الكاملة: كلّ مجموعة بعدد مفاتيحها وتابها — أو «بلا تاب».
     *
     * @return Collection<int, array{group:string, count:int, tab:?string, tab_label:?string, labelled:bool}>
     */
    public function report(): Collection
    {
        $groupToTab = $this->registry->groupToTab();
        $tabs = $this->registry->tabs();
        $catalog = $this->registry->groupCatalog();

        return collect($this->declaredGroups())
            ->map(function (int $count, string $group) use ($groupToTab, $tabs, $catalog) {
                $tab = $groupToTab[$group] ?? null;

                return [
                    'group' => $group,
                    'count' => $count,
                    'tab' => $tab,
                    'tab_label' => $tab ? ($tabs[$tab]['label'] ?? $tab) : null,
                    'labelled' => isset($catalog[$group]),
                ];
            })
            ->values();
    }

    /** المجموعات بلا تاب — كلّ واحدة منها مخالفةٌ صريحة لـ2.13 */
    public function orphanGroups(): Collection
    {
        return $this->report()->where('tab', null)->values();
    }

    /** المجموعات التي لها تاب لكن بلا عنوان عربيّ في الكتالوج */
    public function unlabelledGroups(): Collection
    {
        return $this->report()->where('labelled', false)->values();
    }

    /** مفاتيح لا تصل إليها أيّ شاشة — مجموع مفاتيح المجموعات اليتيمة */
    public function orphanKeyCount(): int
    {
        return (int) $this->orphanGroups()->sum('count');
    }

    public function totalKeys(): int
    {
        return Setting::query()->count();
    }

    /** نسبة التغطية المئويّة — الرقم الذي نطارده حتى يصير 100 */
    public function coveragePercent(): float
    {
        $total = $this->totalKeys();

        return $total === 0 ? 100.0 : round(($total - $this->orphanKeyCount()) / $total * 100, 1);
    }

    /**
     * مفاتيح بنفس البادئة موزَّعة على مجموعتين أو أكثر — مصدر الخلط في البحث
     * وسببُ أن يفتح المفتاحُ تابًا لا يسكنه (تكرار `celebrations.*` مثلًا).
     *
     * @return Collection<string, array<int, string>>
     */
    public function splitPrefixes(): Collection
    {
        return Setting::query()
            ->get(['key', 'group'])
            ->groupBy(fn (Setting $s) => explode('.', $s->key)[0])
            ->map(fn (Collection $rows) => $rows->pluck('group')->unique()->sort()->values()->all())
            ->filter(fn (array $groups) => count($groups) > 1);
    }
}
