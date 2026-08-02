<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حدودُ شاشات كانت أرقامًا محروقة في الخدمات والمتحكّمات (2.13):
 * نافذة «النشطون الآن» · طول قوائم «الأعلى» في الإحصائيّات · تاريخ نوافذ الصيانة.
 */
return new class extends Migration
{
    private const ROWS = [
        ['admin_dashboard.online_window_minutes', 'admin_dashboard', 'نافذة «النشطون الآن» (دقيقة)', 'number', '15'],
        ['stats.top_list_size', 'stats', 'عدد صفوف قوائم «الأعلى»', 'number', '8'],
        ['maintenance.windows_history_limit', 'maintenance', 'عدد نوافذ الصيانة الظاهرة في السجلّ', 'number', '10'],
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
