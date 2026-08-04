<?php

namespace App\Services\Engagement;

use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use Illuminate\Support\Facades\Cache;

/**
 * كاتب إعدادات الرسائل الإيجابيّة والسفراء (2.13-أ/د).
 *
 * لماذا كاتب مستقلّ؟ لأنّ الإعداد بلا شاشة يعدّله الأدمن = عجزٌ للمالك؛
 * ولأنّ الكتابة لازم تمسح الكاش فورًا وإلّا صار للإعداد قيمتان: واحدة في
 * الشاشة وأخرى في المنطق. ولكلّ تعديل **Audit** بقيمته القديمة والجديدة.
 */
class EngagementSettings
{
    /**
     * الكتالوج: المفتاح ⟵ [المجموعة، اللافتة، النوع، الافتراضيّ، الشرح].
     * ولا يُكتَب مفتاح خارجه منعًا لتلويث جدول الإعدادات.
     *
     * @return array<string,array{0:string,1:string,2:string,3:string,4:string}>
     */
    public static function catalog(): array
    {
        return [
            'engagement.positive.enabled' => ['engagement', setting('engagement.engagement_settings.catalog_1', 'تفعيل الرسائل الإيجابيّة'), 'bool', '1', setting('engagement.engagement_settings.catalog_2', 'إيقافها يخفي الأيقونة والرسائل كلّها بلا حذف.')],
            'engagement.positive.icon_chance_percent' => ['engagement', setting('engagement.engagement_settings.catalog_3', 'احتمال ظهور الأيقونة (%)'), 'number', '3', setting('engagement.engagement_settings.catalog_4', '3 = تظهر في 3 تحميلات من كلّ 100.')],
            'engagement.positive.ticket_chance_percent' => ['engagement', setting('engagement.engagement_settings.catalog_5', 'احتمال زرّ التذكرة (%)'), 'number', '20', setting('engagement.engagement_settings.catalog_6', '20 = خُمس ظهورات الأيقونة فيها تذكرة.')],
            'engagement.positive.ticket_amount' => ['engagement', setting('engagement.engagement_settings.catalog_7', 'عدد تذاكر المفاجأة'), 'number', '1', setting('engagement.engagement_settings.catalog_8', '1 = تذكرة واحدة لكلّ مرّة.')],
            'engagement.positive.daily_ticket_cap' => ['engagement', setting('engagement.engagement_settings.catalog_9', 'حدّ تذاكر المفاجأة يوميًّا'), 'number', '1', setting('engagement.engagement_settings.catalog_10', 'صفر = إيقاف التذكرة تمامًا.')],
            'engagement.positive.no_repeat_last' => ['engagement', setting('engagement.engagement_settings.catalog_11', 'كم رسالة لا تتكرّر قبل إعادتها'), 'number', '5', setting('engagement.engagement_settings.catalog_12', '5 = آخر خمس رسائل لا تتكرّر.')],
            'engagement.positive.envelope_title' => ['engagement', setting('engagement.engagement_settings.catalog_13', 'عنوان ظرف الرسالة'), 'string', setting('engagement.engagement_settings.catalog_14', 'وصلتك رسالة'), ''],
            'engagement.positive.open_label' => ['engagement', setting('engagement.engagement_settings.catalog_15', 'نصّ زرّ الفتح'), 'string', setting('engagement.engagement_settings.catalog_16', 'افتح الظرف'), ''],
            'engagement.positive.close_label' => ['engagement', setting('engagement.engagement_settings.catalog_17', 'نصّ زرّ الإغلاق'), 'string', setting('engagement.engagement_settings.catalog_18', 'تمام'), ''],
            'engagement.positive.ticket_label' => ['engagement', setting('engagement.engagement_settings.catalog_19', 'نصّ زرّ التذكرة'), 'string', setting('engagement.engagement_settings.catalog_20', 'استلام تذكرة'), ''],
            'engagement.positive.icon_label' => ['engagement', setting('engagement.engagement_settings.catalog_21', 'وصف الأيقونة لقارئ الشاشة'), 'string', setting('engagement.engagement_settings.catalog_22', 'رسالة إيجابيّة مستنّياك'), ''],
            // السياقات نفسها إعداد — يضيف الأدمن سياقًا جديدًا بلا سطر كود (2.13)
            'engagement.positive.contexts' => ['engagement', setting('engagement.engagement_settings.catalog_23', 'السياقات المعتمَدة'), 'lines', self::defaultContexts(), setting('engagement.engagement_settings.catalog_24', 'سطر لكلّ سياق بصيغة: المفتاح = اللافتة.')],
        ];
    }

    /** السياقات الافتراضيّة — نفس ما يزرعه سيدر المجال */
    /** السياقات الافتراضيّة — قيمةٌ يعدّلها المالك، لا ثابتٌ نظاميّ (2.13-ب) */
    private static function defaultContexts(): string
    {
        return (string) setting('engagement.engagement_settings.default_contexts', '{"any":"أيّ لحظة","surprise":"الأيقونة المفاجئة","lesson_complete":"بعد إكمال درس","course_complete":"بعد إتمام تدريب","streak_broken":"بعد انكسار الستريك","exam_failed":"بعد محاولة امتحان غير موفّقة","empty_state":"في الشاشات الفاضية","first_login":"أوّل دخول بعد التفعيل"}');
    }

    /** تحويل خريطة السياقات إلى أسطر يقرأها الأدمن بسهولة (والعكس عند الحفظ) */
    public static function mapToLines(string $json): string
    {
        $map = json_decode($json, true);

        if (! is_array($map)) {
            return '';
        }

        $lines = [];

        foreach ($map as $key => $label) {
            $lines[] = $key.' = '.$label;
        }

        return implode("\n", $lines);
    }

    public static function linesToMap(string $text): string
    {
        $map = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $label] = explode('=', $line, 2);
            $key = trim($key);
            $label = trim($label);

            if ($key !== '' && $label !== '') {
                $map[$key] = $label;
            }
        }

        return json_encode($map ?: json_decode(self::defaultContexts(), true), JSON_UNESCAPED_UNICODE);
    }

    /**
     * صفوف العرض بقيمها الحاليّة وحالة «معدَّل» — والافتراضيّ يبقى ظاهرًا كمرساة (2.13-و).
     *
     * @return array<string,array{key:string,label:string,type:string,default:string,value:string,hint:string,modified:bool}>
     */
    public static function rows(): array
    {
        $stored = Setting::query()->whereIn('key', array_keys(self::catalog()))->pluck('value', 'key');
        $rows = [];

        foreach (self::catalog() as $key => [$group, $label, $type, $default, $hint]) {
            $value = (string) ($stored[$key] ?? $default);

            $rows[$key] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'default' => $type === 'lines' ? self::mapToLines($default) : $default,
                'value' => $type === 'lines' ? self::mapToLines($value) : $value,
                'hint' => $hint,
                'modified' => $value !== (string) $default,
            ];
        }

        return $rows;
    }

    /** كتابة دفعة من مفاتيح الكتالوج وحدها */
    public static function putMany(array $values, ?User $actor = null): int
    {
        $catalog = self::catalog();
        $count = 0;

        foreach ($values as $key => $value) {
            if (! isset($catalog[$key])) {
                continue;
            }

            [$group, $label, $type, $default, $hint] = $catalog[$key];

            $setting = Setting::firstOrNew(['key' => $key]);
            $old = $setting->value;

            $setting->fill([
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'hint' => $hint ?: null,
                'value' => self::normalize($type, $value),
            ])->save();

            AuditTrail::log($actor, 'settings.update', $setting, ['value' => $old], ['key' => $key, 'value' => $setting->value]);
            $count++;
        }

        Cache::forget('settings');

        return $count;
    }

    /** ↺ رجوع كلّ مفاتيح الكتالوج لافتراضيّها */
    public static function resetAll(?User $actor = null): int
    {
        $defaults = [];

        foreach (self::catalog() as $key => $row) {
            // النوع «أسطر» يُخزَّن JSON ويُعرَض أسطرًا — فالرجوع للافتراضيّ يمرّ بنفس التحويل
            $defaults[$key] = $row[2] === 'lines' ? self::mapToLines($row[3]) : $row[3];
        }

        return self::putMany($defaults, $actor);
    }

    private static function normalize(string $type, mixed $value): string
    {
        if ($type === 'bool') {
            return $value ? '1' : '0';
        }

        if ($type === 'number') {
            return (string) (int) $value;
        }

        if ($type === 'lines') {
            return self::linesToMap((string) $value);
        }

        return (string) $value;
    }
}
