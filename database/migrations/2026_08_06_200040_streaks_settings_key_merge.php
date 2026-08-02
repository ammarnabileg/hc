<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توحيد مفاتيح الستريكس (الدستور 7.2 · 2.13).
 *
 * كانت شاشة الإدارة تكتب `streaks.club5am.*` و`streaks.reward_days` بينما يقرأ
 * الكود `streaks.club_5am.*` و`streaks.reward.every_days` — فالأدمن يعدّل قيمةً
 * لا يراها النظام. المعتمَد الآن ما تكتبه الشاشة ويقرؤه الكود معًا، ونُرحّل
 * القيمة المعدَّلة من المفتاح اليتيم إن وُجدت ثمّ **نحذفه** فلا يبقى مفتاحان
 * لمعنى واحد.
 */
return new class extends Migration
{
    /** اليتيم ⟵ المعتمَد (ما يقرؤه الكود ويكتبه الكتالوج) */
    private const MAP = [
        'streaks.club_5am.window_start' => 'streaks.club5am.window_start',
        'streaks.club_5am.window_end' => 'streaks.club5am.window_end',
        'streaks.reward.every_days' => 'streaks.reward_days',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (self::MAP as $old => $new) {
            $legacy = DB::table('settings')->where('key', $old)->first();

            if (! $legacy) {
                continue;
            }

            $current = DB::table('settings')->where('key', $new)->first();

            if (! $current) {
                // لا نظير للمفتاح الجديد — نُعيد تسمية الصفّ نفسه فتبقى قيمته
                DB::table('settings')->where('key', $old)->update([
                    'key' => $new,
                    'group' => 'gamification_streaks',
                    'updated_at' => now(),
                ]);

                continue;
            }

            // القيمة التي غادرت افتراضيّها هي قصد الأدمن — فهي التي تنتقل
            $legacyEdited = $legacy->value !== null && (string) $legacy->value !== (string) $legacy->default_value;

            if ($legacyEdited) {
                DB::table('settings')->where('key', $new)->update([
                    'value' => $legacy->value,
                    'updated_at' => now(),
                ]);
            }

            DB::table('settings')->where('key', $old)->delete();
        }

        // المجموعة الموحّدة: تاب «الستريكس ونادي الخامسة» في لوحة التلعيب
        DB::table('settings')->where('group', 'streaks')->update(['group' => 'gamification_streaks']);
    }
};
