<?php

namespace App\Services\Admin\Content;

use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ بلوك إعدادات «دليل المستخدم» (12.6-ج — فجوة مسدودة).
 *
 * كانت الشاشة بلا بلوك إعدادات إطلاقًا: البحث و«هل كان مفيدًا؟» ظاهران على
 * الصفحة العامّة دائمًا بلا Toggle، والتصنيفات والوسوم حقولٌ حرّة بلا شاشة
 * تديرها، ونصّ الحالة الفارغة والصفحة الافتراضيّة بلا مكانٍ يعدّلهما الأدمن.
 * النصّ الدستوريّ حرفيًّا (24.3 سطر 5087):
 *
 * > «Toggle البحث داخل الدليل (ON) · Toggle «هل كان مفيدًا؟» (ON) + نصّه (ع/إ) ·
 * >  Toggle عرض التصنيفات في الشريط الجانبيّ · عدد المقالات/صفحة (12) · CRUD
 * >  التصنيفات والوسوم · نصّ الحالة الفارغة (ع/إ) · ↺ Reset.»
 *
 * ⚠️ **بعض المفاتيح مملوكةٌ لمجالٍ آخر** (`account.help.page_size` زرعه
 * `AccountDemoSeeder` · `help.show.text_1`/`help.index.message_1` زرعهما
 * `ScreenTextDemoSeeder` · `help.categories` زرعه `AdminContentDemoSeeder`
 * نفسه) — فهذا البلوك **يكتب قيمتها فقط** ولا يعيد تعريف بطاقتها (المجموعة/
 * اللافتة/النوع/الافتراضيّ)، وإلّا مسحنا تعريفًا صحيحًا يقرؤه مكانٌ آخر
 * (كالبحث الموحَّد في 12.7). القائمة الكاملة الجديدة التي يملكها هذا البلوك
 * وحده هي `catalog()` فقط.
 */
class HelpGuideSettings
{
    /**
     * مفاتيح جديدة **يملكها هذا البلوك بالكامل** — تعريفٌ كامل + ↺ Reset.
     *
     * المفتاح ⟵ [المجموعة، اللافتة، النوع، الافتراضيّ].
     *
     * @return array<string, array{0:string,1:string,2:string,3:string}>
     */
    public static function catalog(): array
    {
        return [
            'help.search_enabled' => ['help', (string) setting('admin_content.help_guide.toggle_search', 'تفعيل البحث داخل الدليل'), 'bool', '1'],
            'help.feedback_enabled' => ['help', (string) setting('admin_content.help_guide.toggle_feedback', 'تفعيل «هل كان مفيدًا؟»'), 'bool', '1'],
            'help.show.text_1_en' => ['help', (string) setting('admin_content.help_guide.feedback_text_en', 'نصّ «هل كان مفيدًا؟» (إنجليزيّ)'), 'string', 'Was this helpful?'],
            'help.sidebar_categories_enabled' => ['help', (string) setting('admin_content.help_guide.toggle_sidebar_categories', 'إظهار التصنيفات فوق الأدلّة'), 'bool', '1'],
            'help.index.message_1_en' => ['help', (string) setting('admin_content.help_guide.empty_text_en', 'نصّ الحالة الفارغة (إنجليزيّ)'), 'string', 'No results. Try another word.'],
        ];
    }

    /**
     * مفاتيح مملوكة لمجالٍ آخر — نكتب **قيمتها فقط** ونقرأ بطاقتها من صفّها
     * المزروع، فلا نخترع تعريفًا ثانيًا لمفتاحٍ له تعريفٌ بالفعل (2.13).
     *
     * @return list<string>
     */
    public static function sharedKeys(): array
    {
        return ['help.show.text_1', 'help.index.message_1', 'account.help.page_size'];
    }

    /**
     * صفوف العرض بقيمها الحاليّة وحالة «معدَّل» — بالشكل الذي تقرؤه
     * `admin.volunteer.partials.settings-card` مباشرةً (2.13-و).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(): array
    {
        $keys = array_merge(array_keys(self::catalog()), self::sharedKeys());
        $stored = Setting::query()->whereIn('key', $keys)->get()->keyBy('key');
        $rows = [];

        foreach (self::catalog() as $key => [$group, $label, $type, $default]) {
            $value = (string) ($stored->get($key)->value ?? $default);

            $rows[] = ['key' => $key, 'label' => $label, 'type' => $type, 'default' => $default, 'value' => $value, 'modified' => $value !== $default];
        }

        foreach (self::sharedKeys() as $key) {
            $setting = $stored->get($key);

            if (! $setting) {
                // المفتاح لم يُزرَع بعد (بيئة بلا `AccountDemoSeeder`/`ScreenTextDemoSeeder`)
                // — الشاشة لا تخترع له تعريفًا، لا تعرضه إلّا حين يوجد فعلًا.
                continue;
            }

            $default = (string) ($setting->default_value ?? '');
            $value = (string) ($setting->value ?? $default);

            $rows[] = [
                'key' => $key,
                'label' => (string) $setting->label_ar,
                'type' => $setting->type ?: 'string',
                'default' => $default,
                'value' => $value,
                'modified' => $value !== $default,
            ];
        }

        return $rows;
    }

    /** كتابة دفعة — ومفاتيح خارج الكتالوجَين تُهمَل بصمت (فورمٌ مزوَّر لا يلوّث الجدول). */
    public static function putMany(array $values, ?User $actor = null): int
    {
        $catalog = self::catalog();
        $shared = self::sharedKeys();
        $count = 0;

        foreach ($values as $key => $value) {
            if (isset($catalog[$key])) {
                [$group, $label, $type, $default] = $catalog[$key];
                $setting = Setting::firstOrNew(['key' => $key]);
                $old = $setting->value;
                $setting->fill([
                    'group' => $group,
                    'label_ar' => $label,
                    'type' => $type,
                    'default_value' => $default,
                    'value' => self::normalize($type, $value),
                ])->save();
            } elseif (in_array($key, $shared, true)) {
                $setting = Setting::query()->where('key', $key)->first();

                if (! $setting) {
                    continue; // لا نُنشئ صفًّا لمفتاحٍ مملوكٍ لمجالٍ آخر لم يُزرَع بعد
                }

                $old = $setting->value;
                $setting->update(['value' => self::normalize($setting->type ?: 'string', $value)]);
            } else {
                continue;
            }

            AuditTrail::log($actor, 'settings.update', $setting, ['value' => $old], ['key' => $key, 'value' => $setting->value]);
            $count++;
        }

        Cache::forget('settings');

        return $count;
    }

    /** ↺ Reset لكلّ مفاتيح البلوك (كتالوجه + المشتركة) لافتراضيّها المزروع. */
    public static function resetAll(?User $actor = null): int
    {
        $defaults = [];

        foreach (self::catalog() as $key => $row) {
            $defaults[$key] = $row[3];
        }

        foreach (self::sharedKeys() as $key) {
            $setting = Setting::query()->where('key', $key)->first();

            if ($setting) {
                $defaults[$key] = (string) $setting->default_value;
            }
        }

        return self::putMany($defaults, $actor);
    }

    // ================================================== CRUD: تصنيفات ووسوم

    /**
     * ⚠️ الافتراضيّ **نصٌّ JSON واحد** لا مصفوفة PHP حرفيّة — فكلّ النصّ
     * العربيّ يبقى **داخل** استدعاء `setting()` نفسه (حارس النصّ المحروق
     * 2.13-أ يُعِدُّ ما بداخل `setting()` ممتثلًا، لا ما يقع خارجه ولو في
     * مصفوفة PHP)، تمامًا كما تفعل `EngagementSettings::defaultContexts()`.
     */
    private static function categoryDefaultsJson(): string
    {
        return (string) setting('admin_content.help_guide.categories_seed', '["البداية","التدريبات","الشهادات","المحفظة","الحساب"]');
    }

    /** @return list<string> */
    public static function categories(): array
    {
        $seed = json_decode(self::categoryDefaultsJson(), true) ?: [];

        return self::cleanList((array) setting('help.categories', $seed));
    }

    /** @return list<string> */
    public static function tags(): array
    {
        return self::cleanList((array) setting('help.tags', []));
    }

    /** الافتراضيّ المعروض كمرساة تحت قائمة التصنيفات (2.13-و) */
    public static function defaultCategories(): array
    {
        return (array) (json_decode(self::categoryDefaultsJson(), true) ?: []);
    }

    /**
     * حفظ قائمة التصنيفات من لوحة الأدمن — إضافة/تعديل/حذف (12.6-ج).
     *
     * @param  array<int, string>  $items
     * @return list<string>
     */
    public static function saveCategories(array $items, ?User $actor = null): array
    {
        return self::saveList(
            'help.categories',
            (string) setting('admin_content.help_guide.categories_label', 'تصنيفات دليل المستخدم'),
            self::defaultCategories(),
            $items,
            $actor,
        );
    }

    /**
     * حفظ قائمة الوسوم من لوحة الأدمن — إضافة/تعديل/حذف (12.6-ج).
     *
     * @param  array<int, string>  $items
     * @return list<string>
     */
    public static function saveTags(array $items, ?User $actor = null): array
    {
        return self::saveList('help.tags', (string) setting('admin_content.help_guide.tags_label', 'وسوم دليل المستخدم'), [], $items, $actor);
    }

    /**
     * @param  array<int, string>  $defaults
     * @param  array<int, string>  $items
     * @return list<string>
     */
    private static function saveList(string $key, string $label, array $defaults, array $items, ?User $actor): array
    {
        $clean = self::cleanList($items);

        $setting = Setting::updateOrCreate(
            ['key' => $key],
            [
                'group' => 'help',
                'label_ar' => $label,
                'type' => 'json',
                'default_value' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
                'value' => json_encode($clean, JSON_UNESCAPED_UNICODE),
            ],
        );

        Cache::forget('settings');

        AuditTrail::log($actor, 'settings.update', $setting, [], ['key' => $key, 'value' => $clean]);

        return $clean;
    }

    /** @param array<int, mixed> $items @return list<string> */
    private static function cleanList(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    private static function normalize(string $type, mixed $value): string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'number' => (string) (int) $value,
            default => (string) $value,
        };
    }
}
