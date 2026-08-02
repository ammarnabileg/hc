<?php

namespace App\Services\Admin\System;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * دماغ شاشة الإعدادات الواحدة بتاباتها الجانبيّة (2.13-و · 2.15-د).
 *
 * لماذا صنف واحد؟ لأنّ قواعد الإعدادات (البحث بالمسار · التصدير/الاستيراد ·
 * الحفظ التلقائيّ · Reset · Audit · عزل الماليّ) قاعدةٌ واحدة لا تتكرّر في كلّ تاب،
 * فلو تكرّرت اختلفت من مكان لمكان — وهذا أخطر ما يصيب لوحة إعدادات.
 */
class SettingsRegistry
{
    /**
     * التابات الجانبيّة: المفتاح ⟵ [العنوان · المجموعات · سطر تعريفيّ].
     * وترتيبها هو ترتيب العرض — ولا يُبنى من قاعدة البيانات حتى لا يتغيّر بالصدفة.
     *
     * @return array<string, array{label:string, groups:array<int,string>, hint:string}>
     */
    public function tabs(): array
    {
        return [
            'platform' => [
                'label' => 'إعدادات المنصّة',
                'groups' => ['system', 'accounts', 'integrations', 'ux', 'feel'],
                'hint' => 'الاسم واللغة والبريد والتكاملات وسلوك الجلسات.',
            ],
            'identity' => [
                'label' => 'الهويّة والمظهر',
                'groups' => ['appearance'],
                'hint' => 'توكنز الألوان والخطوط والمساحات والزخارف والسايد بار.',
            ],
            'onboarding' => [
                'label' => 'محتوى الـOnboarding',
                'groups' => ['onboarding'],
                'hint' => 'رحلة التسجيل من التعليمات إلى صفحة القبول.',
            ],
            'cv' => [
                'label' => 'قوالب الـCV',
                'groups' => ['cv'],
                'hint' => 'تكلفة القوالب بالتذاكر وحدود الأقسام والرابط العامّ.',
            ],
            'security' => [
                'label' => 'الأمان والخصوصيّة',
                'groups' => ['security'],
                'hint' => 'كلمات المرور والجلسات وحدود المحاولات وسلّة المحذوفات.',
            ],
            'features' => [
                'label' => 'مفاتيح المزايا',
                'groups' => ['features'],
                'hint' => 'إطفاء أو تشغيل أيّ ميزة بلا نشر كود — ولا صيانة جزئيّة.',
            ],
            'countries' => [
                'label' => 'بيانات الدول',
                'groups' => ['countries'],
                'hint' => 'مصدر الدول والمحافظات وسياسة الدمج بلا فقد بيانات.',
            ],
            'maintenance' => [
                'label' => 'وضع الصيانة',
                'groups' => ['maintenance'],
                'hint' => 'قفل المنصّة بالكامل مع تجميد كلّ المهل طوال المدّة.',
            ],
            'updates' => [
                'label' => 'التحديثات والترحيل',
                'groups' => ['updates'],
                'hint' => 'الترقية بنقرة دون فقد بيانات، واسترجاع بضغطة عند الفشل.',
            ],
            'backups' => [
                'label' => 'النسخ الاحتياطيّ وصحّة النظام',
                'groups' => ['backups'],
                'hint' => 'النسخ اليدويّة والمجدولة ومراقبة صحّة النظام.',
            ],
            'audit' => [
                'label' => 'سجلّ التدقيق',
                'groups' => [],
                'hint' => 'أثر كامل لكلّ تغيير إداريّ — للقراءة فقط.',
            ],
        ];
    }

    /**
     * ⭐ المجموعة الماليّة معزولة لمالك المنصّة وحده (2.13-و · 24.3).
     * والتاب لا يظهر أصلًا لغيره — لا معطَّلًا ولا رماديًّا (2.15-أ-7).
     */
    public function tabsFor(User $user): array
    {
        $tabs = $this->tabs();

        if ($user->isPlatformOwner()) {
            $tabs['finance'] = [
                'label' => '🔒 الماليّات',
                'groups' => ['finance'],
                'hint' => 'مصدر الحقيقة الوحيد لكلّ رقم ماليّ — مجموعة محميّة.',
            ];
        }

        return $tabs;
    }

    /** إعدادات تاب بعينه، مرتّبةً ومصفّاةً بحسب مَن ينظر */
    public function forTab(string $tab, User $user, string $search = ''): Collection
    {
        $groups = $this->tabsFor($user)[$tab]['groups'] ?? [];

        if ($groups === []) {
            return collect();
        }

        return $this->visible($user)
            ->whereIn('group', $groups)
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (Setting $s) => str_contains($s->key, $search) || str_contains($s->label_ar, $search)
            ))
            ->sortBy('key')
            ->values();
    }

    /**
     * ⭐ بحث موحّد داخل كلّ الإعدادات بالاسم أو بالقيمة، والنتيجة **بمسارها الكامل**
     * (التاب › المجموعة › الحقل) لتنقل للحقل مع تظليل مؤقّت (2.13-و).
     *
     * @return array<int, array{key:string,label:string,tab:string,tab_label:string,group:string,path:string,value:?string}>
     */
    public function search(string $term, User $user): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $tabOf = [];
        foreach ($this->tabsFor($user) as $tabKey => $tab) {
            foreach ($tab['groups'] as $group) {
                $tabOf[$group] = [$tabKey, $tab['label']];
            }
        }

        return $this->visible($user)
            ->filter(fn (Setting $s) => str_contains(mb_strtolower($s->key), mb_strtolower($term))
                || str_contains($s->label_ar, $term)
                || str_contains((string) $s->value, $term))
            ->take(40)
            ->map(function (Setting $s) use ($tabOf) {
                [$tabKey, $tabLabel] = $tabOf[$s->group] ?? ['platform', 'إعدادات المنصّة'];

                return [
                    'key' => $s->key,
                    'label' => $s->label_ar,
                    'tab' => $tabKey,
                    'tab_label' => $tabLabel,
                    'group' => $s->group,
                    'path' => $tabLabel.' › '.$s->group.' › '.$s->label_ar,
                    'value' => $s->value,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * حفظ حقل واحد (حفظ تلقائيّ مع «تم الحفظ» جنب الحقل — 2.13-و).
     * وحماية من الحفظ الناقص: الرقم لا يُحفَظ إلّا داخل نطاقه.
     *
     * @return array{saved:bool, message:string, value:?string}
     */
    public function save(Setting $setting, mixed $value, User $actor): array
    {
        if (! $this->mayEdit($setting, $actor)) {
            return ['saved' => false, 'message' => 'الإعداد ده لمالك المنصّة وحده.', 'value' => $setting->value];
        }

        if ($this->isDisabled($setting)) {
            return ['saved' => false, 'message' => 'فعّل الميزة أوّلًا.', 'value' => $setting->value];
        }

        $normalized = $this->normalize($setting, $value);

        if ($normalized === null) {
            return [
                'saved' => false,
                'message' => 'القيمة خارج النطاق — '.$this->rangeHint($setting),
                'value' => $setting->value,
            ];
        }

        $old = $setting->value;

        if ($old === $normalized) {
            return ['saved' => true, 'message' => 'تم الحفظ ✓', 'value' => $normalized];
        }

        $setting->update(['value' => $normalized]);
        $this->audit($setting, $old, $normalized, $actor, 'settings.update');
        $this->flush();

        return ['saved' => true, 'message' => 'تم الحفظ ✓', 'value' => $normalized];
    }

    /** ↺ Reset لحقل واحد — والافتراضيّ يبقى ظاهرًا دائمًا كـPlaceholder (مرساة) */
    public function reset(Setting $setting, User $actor): array
    {
        return $this->save($setting, $setting->default_value, $actor);
    }

    /** الرجوع لآخر قيمة قبل التغيير الأخير — «تراجع عن آخر تغيير» */
    public function undo(Setting $setting, User $actor): array
    {
        $last = $this->lastChange($setting);

        if (! $last) {
            return ['saved' => false, 'message' => 'مفيش تغيير سابق نرجع له.', 'value' => $setting->value];
        }

        return $this->save($setting, $last['old'], $actor);
    }

    /**
     * ⭐ Audit بالـHover — آخر تغيير فقط (قيمة سابقة واحدة) مع رابط لبروفايل المحرّر.
     * والسجلّ نفسه يبقى Append-only في صفحة سجلّ التدقيق — فلا تعارض.
     *
     * @return array{old:?string,new:?string,by:?string,by_url:?string,at:string}|null
     */
    public function lastChange(Setting $setting): ?array
    {
        $log = AuditLog::query()
            ->where('auditable_type', $setting->getMorphClass())
            ->where('auditable_id', $setting->getKey())
            ->latest('id')
            ->first();

        if (! $log) {
            return null;
        }

        $editor = $log->user;

        return [
            'old' => $log->old_values['value'] ?? null,
            'new' => $log->new_values['value'] ?? null,
            'by' => $editor?->name,
            'by_url' => $editor?->profileUrl(),
            'at' => $log->created_at?->diffForHumans() ?? '',
        ];
    }

    /** تصدير الإعدادات المرئيّة لهذا المستخدم كـJSON (نقل التخصيص في ثوانٍ) */
    public function export(User $user): array
    {
        return $this->visible($user)
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->value])
            ->all();
    }

    /**
     * استيراد JSON — يتخطّى المفاتيح المجهولة والممنوعة بصمت مُحصى،
     * فلا يفتح الاستيرادُ بابًا خلفيًّا على المجموعة الماليّة.
     *
     * @return array{applied:int, skipped:int}
     */
    public function import(array $payload, User $user): array
    {
        $applied = 0;
        $skipped = 0;

        foreach ($payload as $key => $value) {
            $setting = Setting::query()->where('key', $key)->first();

            if (! $setting || ! $this->mayEdit($setting, $user)) {
                $skipped++;

                continue;
            }

            $result = $this->save($setting, $value, $user);
            $result['saved'] ? $applied++ : $skipped++;
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /** هل يجوز لهذا المستخدم تعديل هذا الإعداد؟ (عزل الحسّاس) */
    public function mayEdit(Setting $setting, User $user): bool
    {
        if ($setting->is_owner_only || $setting->group === 'finance') {
            return $user->isPlatformOwner();
        }

        return true;
    }

    /**
     * ⭐ إعدادات ميزة موقوفة تظهر **معطَّلة** بسطر «فعّل الميزة أوّلًا» (2.13-و)
     * — بدل تعديل بلا أثر يوهم الأدمن أنّه غيّر شيئًا.
     */
    public function isDisabled(Setting $setting): bool
    {
        $toggle = $this->togglerOf($setting->key);

        return $toggle !== null && ! setting($toggle, true);
    }

    /** مفتاح الميزة الحاكم لهذا الإعداد (إن وُجد) */
    public function togglerOf(string $key): ?string
    {
        foreach ($this->toggleMap() as $prefix => $toggle) {
            if ($key !== $toggle && str_starts_with($key, $prefix)) {
                return $toggle;
            }
        }

        return null;
    }

    /** مثال بالقيمة يتحدّث لحظيًّا مع الكتابة («72 = 3 أيّام») — خطّ الدفاع الأوّل */
    public function liveExample(Setting $setting): ?string
    {
        if ($setting->type !== 'number') {
            return null;
        }

        $value = (float) ($setting->value ?? 0);

        return match (true) {
            str_contains($setting->key, 'hours') => rtrim(rtrim(number_format($value / 24, 2, '.', ''), '0'), '.').' يوم',
            str_contains($setting->key, 'minutes') => rtrim(rtrim(number_format($value / 60, 2, '.', ''), '0'), '.').' ساعة',
            str_contains($setting->key, 'percent') => 'من كلّ 100 ⟵ '.$value,
            default => null,
        };
    }

    /** النطاق المسموح لكلّ نوع رقميّ — يُعرَض كسطر خفيف لا كخطأ أحمر صارخ */
    public function rangeHint(Setting $setting): string
    {
        [$min, $max] = $this->range($setting);

        return "من {$min} إلى {$max}";
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return Collection<int, Setting> */
    private function visible(User $user): Collection
    {
        $query = Setting::query();

        if (! $user->isPlatformOwner()) {
            $query->where('is_owner_only', false)->where('group', '!=', 'finance');
        }

        return $query->orderBy('key')->get();
    }

    /** @return array{0:float,1:float} */
    private function range(Setting $setting): array
    {
        return match (true) {
            str_contains($setting->key, 'percent') => [0, 100],
            str_contains($setting->key, 'hours') => [1, 8760],
            str_contains($setting->key, 'days') => [1, 3650],
            default => [0, 1000000],
        };
    }

    private function normalize(Setting $setting, mixed $value): ?string
    {
        if ($setting->type === 'bool') {
            return in_array($value, [true, 1, '1', 'true', 'on'], true) ? '1' : '0';
        }

        if ($setting->type === 'number') {
            if (! is_numeric($value)) {
                return null;
            }

            [$min, $max] = $this->range($setting);

            return ($value < $min || $value > $max) ? null : (string) ($value + 0);
        }

        if ($setting->type === 'json') {
            $decoded = is_array($value) ? $value : json_decode((string) $value, true);

            return $decoded === null ? null : json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /** سطر Audit — والصفحة تعرض آخر واحد فقط، والجدول يبقى Append-only */
    public function audit(Setting $setting, ?string $old, ?string $new, User $actor, string $action, ?string $reason = null): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $setting->getMorphClass(),
            'auditable_id' => $setting->getKey(),
            'old_values' => ['value' => $old],
            'new_values' => array_filter(['value' => $new, 'reason' => $reason], fn ($v) => $v !== null),
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }

    private function flush(): void
    {
        Cache::forget('settings');
    }

    /** بادئة الإعداد ⟵ مفتاح الميزة الحاكم */
    private function toggleMap(): array
    {
        return [
            'store.' => 'store.enabled',
            'bundles.' => 'bundles.enabled',
            'coupons.' => 'coupons.enabled',
            'order_bump.' => 'order_bump.enabled',
            'topup.manual.' => 'topup.manual.enabled',
            'topup.gateway.' => 'topup.gateway.enabled',
            'articles.' => 'articles.enabled',
            'images.' => 'images.enabled',
            'ads.' => 'ads.tracking.enabled',
        ];
    }
}
