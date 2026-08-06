<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `SettingsRegistry` (بحث/تصفّح الإعدادات عبر المنصّة — 12.7 حاكم عامّ) يحتاج
 * تابًّا يستضيف مجموعة `developers` الجديدة (12.15-د) وإلّا ظهرت «مجموعة بلا
 * شاشة» في `settings:coverage`. أربعة مفاتيح فقط — بنفس مجموعة ونمط
 * `system.settings_registry.tabs_*` و`group_catalog_*` الحاليّة، **في هجرة**
 * لا في سيدرٍ وحده لأنّ الإنتاج لا يشغّل السيدرات (2.13).
 */
return new class extends Migration
{
    private const ROWS = [
        ['system.settings_registry.tabs_49', 'system', 'تاب سجلّ الإعدادات: المطوّرين — API', 'string', 'المطوّرين — API'],
        ['system.settings_registry.tabs_50', 'system', 'وصف تاب سجلّ الإعدادات: المطوّرين — API', 'string', 'تفعيل واجهة الـAPI وحدّ المعدّل الافتراضيّ ومدّة الاحتفاظ بسجلّ الطلبات.'],
        ['system.settings_registry.group_catalog_165', 'system', 'عنوان مجموعة سجلّ الإعدادات: المطوّرين — API', 'string', 'المطوّرين — API'],
        ['system.settings_registry.group_catalog_166', 'system', 'وصف مجموعة سجلّ الإعدادات: المطوّرين — API', 'string', 'تفعيل الواجهة وحدّ المعدّل ونصوص شاشة مفاتيح الـAPI.'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ROWS as [$key, $group, $label, $type, $default]) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
                'is_sensitive' => false,
                'is_owner_only' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::ROWS, 0))->delete();
    }
};
