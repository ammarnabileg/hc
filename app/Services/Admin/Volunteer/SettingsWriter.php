<?php

namespace App\Services\Admin\Volunteer;

use App\Models\Entity;
use App\Models\Setting;
use App\Models\SettingOverride;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * كاتب الإعدادات: يكتب في `settings` ويمسح الكاش فورًا فينعكس التعديل
 * على `setting()` في نفس الطلب — وإلّا صار للإعداد قيمتان: واحدة في الشاشة
 * وأخرى في المنطق، وهي أسوأ من عدم وجود الإعداد أصلًا (2.13).
 */
class SettingsWriter
{
    /** يضمن وجود صفّ الإعداد بقيمته الافتراضيّة من الكتالوج */
    public static function ensure(string $key): ?Setting
    {
        $row = SettingsCatalog::all()[$key] ?? null;

        if (! $row) {
            return Setting::query()->where('key', $key)->first();
        }

        [$group, $label, $type, $default] = $row;

        return Setting::firstOrCreate(
            ['key' => $key],
            ['group' => $group, 'label_ar' => $label, 'type' => $type, 'default_value' => $default, 'value' => $default],
        );
    }

    /** كتابة قيمة واحدة + Audit + مسح الكاش */
    public static function put(string $key, mixed $value, ?User $actor = null): void
    {
        $setting = self::ensure($key);

        if (! $setting) {
            return;
        }

        $old = $setting->value;
        $setting->value = self::normalize($value);
        $setting->save();

        Cache::forget('settings');

        AuditTrail::log($actor, 'settings.update', $setting, ['value' => $old], ['key' => $key, 'value' => $setting->value]);
    }

    /** كتابة دفعة من مفاتيح مجموعة واحدة — «حفظ الكلّ» في تاب */
    public static function putMany(array $values, ?User $actor = null): int
    {
        $catalog = SettingsCatalog::all();
        $count = 0;

        foreach ($values as $key => $value) {
            if (! isset($catalog[$key])) {
                continue; // لا نكتب مفتاحًا خارج الكتالوج — منعًا لتلويث الجدول
            }

            self::put($key, $value, $actor);
            $count++;
        }

        return $count;
    }

    /** ↺ Reset لمفتاح واحد إلى افتراضيّه المعتمَد */
    public static function reset(string $key, ?User $actor = null): void
    {
        $default = SettingsCatalog::defaultOf($key);

        if ($default === null) {
            return;
        }

        self::put($key, $default, $actor);
    }

    /** ↺ Reset لكلّ مفاتيح تاب */
    public static function resetGroup(string $group, ?User $actor = null): int
    {
        $count = 0;

        foreach (SettingsCatalog::group($group) as $key => $row) {
            self::put($key, $row[3], $actor);
            $count++;
        }

        return $count;
    }

    /** Override لكيان بعينه (2.13-هـ) — يعلو القيمة العامّة داخل الكيان وحده */
    public static function override(string $key, Entity $entity, mixed $value, ?User $actor = null): void
    {
        $setting = self::ensure($key);

        if (! $setting) {
            return;
        }

        SettingOverride::updateOrCreate(
            ['setting_id' => $setting->id, 'entity_id' => $entity->id],
            ['value' => self::normalize($value), 'updated_by' => $actor?->id],
        );

        AuditTrail::log($actor, 'settings.override', $setting, [], ['key' => $key, 'entity_id' => $entity->id, 'value' => self::normalize($value)]);
    }

    public static function dropOverride(string $key, Entity $entity, ?User $actor = null): void
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return;
        }

        SettingOverride::query()
            ->where('setting_id', $setting->id)
            ->where('entity_id', $entity->id)
            ->delete();

        AuditTrail::log($actor, 'settings.override_removed', $setting, [], ['key' => $key, 'entity_id' => $entity->id]);
    }

    /** هل غادر الحقل افتراضيّه؟ — شارة «معدَّل» بجانبه (24.2) */
    public static function isModified(string $key): bool
    {
        $default = SettingsCatalog::defaultOf($key);

        if ($default === null) {
            return false;
        }

        $current = Setting::query()->where('key', $key)->value('value');

        return $current !== null && (string) $current !== (string) $default;
    }

    /** كلّ إعدادات مجموعة بقيمها الحاليّة وحالة «معدَّل» — مادّة عرض التاب */
    public static function groupRows(string $group): array
    {
        $rows = [];
        $stored = Setting::query()->whereIn('key', array_keys(SettingsCatalog::group($group)))->pluck('value', 'key');

        foreach (SettingsCatalog::group($group) as $key => [$g, $label, $type, $default]) {
            $rows[$key] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'default' => $default,
                'value' => $stored[$key] ?? $default,
                'modified' => isset($stored[$key]) && (string) $stored[$key] !== (string) $default,
            ];
        }

        return $rows;
    }

    private static function normalize(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value === null ? null : (string) $value;
    }
}
