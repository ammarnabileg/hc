<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط جدولَي الألعاب — تنفيذًا لإلغاء البند 7.5 بقرار المالك (الدستور v5.3).
 *
 * ولمَ هجرةٌ جديدة لا حذفُ هجرة الإنشاء؟ لأنّ الهجرة المطبَّقة على تنصيبٍ
 * قائم لا تُسحَب بحذف ملفّها — يبقى الجدول في قاعدته ويختفي أثرُه من الشجرة،
 * فينجو الجدول من الإلغاء صامتًا. والإسقاط هنا يقع على **كلّ** تنصيب.
 *
 * ⚠️ **وتاريخها بعد آخر هجرةٍ في الشجرة عن قصد:** الترتيب بالاسم لا بالنيّة،
 * فهجرةٌ بتاريخٍ أسبق تُسقِط الجدول ثمّ تعيده هجرةُ الإنشاء بعدها — إسقاطٌ
 * يُبطِله ترتيبُ التنفيذ. (وقع هذا فعلًا وكشفه الحارس قبل أن يُعتمَد.)
 *
 * ومعه تُرفَع صفوف الإعدادات والصلاحيّات التي لا مستهلكَ لها بعد الإلغاء،
 * وإلّا بقيت في اللوحة تَعِد بما لا يقع (وهو عين ما تمنعه 2.13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('game_sessions');
        Schema::dropIfExists('games');

        // ⚠️ كلّ ما بعده مشروطٌ بوجود الجدول: هذه الهجرة تسبق بعض الجداول على
        // مسار `migrate:fresh`، والتنظيف تطهيرٌ لتنصيبٍ قائم لا شرطُ صحّةٍ للجديد.

        // إعدادات القسم الملغى — لا شاشة تقرؤها ولا كود يستهلكها
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'like', 'games.%')->delete();

            // «دخول لعبة» من أوجه صرف التذاكر (7.1 بعد الإلغاء)
            $this->dropSpendFace('game.enter');
        }

        if (Schema::hasTable('setting_definitions')) {
            DB::table('setting_definitions')->where('key', 'like', 'games.%')->delete();
        }

        // مفاتيح المورد الملغى — والصفوف المعلّقة بها في الأدوار والاستثناءات
        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->where('resource', 'games')->pluck('id');

            if ($ids->isNotEmpty()) {
                if (Schema::hasTable('permission_role')) {
                    DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
                }

                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }
    }

    /**
     * الرجوع لا يعيد الألعاب: الإلغاء قرارُ مالكٍ لا عطبٌ يُتراجَع عنه،
     * وإعادة جدولٍ فارغٍ بلا كودٍ يقرؤه تعيد الوهم لا الوظيفة.
     */
    public function down(): void {}

    /** يرفع وجه الصرف من `xp_rules.spend` ويترك ما أضافه المالك بيده */
    private function dropSpendFace(string $key): void
    {
        $row = DB::table('settings')->where('key', 'xp_rules.spend')->first();

        if (! $row) {
            return;
        }

        $rows = json_decode((string) $row->value, true);

        if (! is_array($rows)) {
            return;
        }

        $kept = array_values(array_filter(
            $rows,
            fn ($face) => (string) ($face['key'] ?? '') !== $key,
        ));

        if (count($kept) === count($rows)) {
            return;
        }

        DB::table('settings')->where('key', 'xp_rules.spend')->update([
            'value' => json_encode($kept, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
