<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة التكلفة في الكيانات Overrides — وجدول «أوجه الصرف» هو الحاكم (2.13 · 12.10).
 *
 * كان لكلّ من هذه القيم **مصدران متنازعان**: صفٌّ في `xp_rules.spend` يحرّره
 * المالك من لوحة الإدارة **بلا أيّ مستهلك**، وعمودٌ في جدول الكيان هو المقروء
 * فعلًا. فالمالك يعدّل الجدول ولا يرى أثرًا — وهو نصّ محروق بخطوة إضافيّة.
 *
 * فصارت الأعمدة `nullable` بلا افتراضيّ: **NULL = اتبع العامّ**، والقيمة =
 * استثناءٌ صريح لهذا الكيان وحده — على غرار عمودَي تذاكر التدريب.
 *
 * والصفوف الحاملة للافتراضيّ القديم تُفرَّغ مرّةً واحدة لأنّها لم تكن اختيارًا
 * من أحد بل أثرًا للمخطّط:
 *  - `challenges.entry_cost` = 0 (وكان عمودًا يُحرَّر ولا يُقرأ أصلًا).
 *  - `games.ticket_cost` = تكلفة الدخول العامّة نفسها.
 *  - `cv_templates.price_tickets` = السعر العامّ نفسه — والقالب المجّانيّ لا يُلمَس.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->decimal('entry_cost', 12, 2)->nullable()->default(null)->change();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->decimal('ticket_cost', 12, 2)->nullable()->default(null)->change();
        });

        Schema::table('cv_templates', function (Blueprint $table) {
            $table->decimal('price_tickets', 12, 2)->nullable()->default(null)->change();
        });

        // ما لم يختره أحد لا يُحسَب اختيارًا — والقيم المعدَّلة فعلًا تبقى كما هي.
        DB::table('challenges')->where('entry_cost', 0)->update(['entry_cost' => null]);

        DB::table('games')
            ->where('ticket_cost', (float) $this->setting('games.ticket_cost', 1))
            ->update(['ticket_cost' => null]);

        DB::table('cv_templates')
            ->where('is_free', false)
            ->where('price_tickets', (float) $this->setting('cv.template.default_price_tickets', 2))
            ->update(['price_tickets' => null]);
    }

    public function down(): void
    {
        DB::table('challenges')->whereNull('entry_cost')->update(['entry_cost' => 0]);
        DB::table('games')->whereNull('ticket_cost')->update(['ticket_cost' => 1]);
        DB::table('cv_templates')->whereNull('price_tickets')->update(['price_tickets' => 0]);

        Schema::table('challenges', function (Blueprint $table) {
            $table->decimal('entry_cost', 12, 2)->default(0)->nullable(false)->change();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->decimal('ticket_cost', 12, 2)->default(1)->nullable(false)->change();
        });

        Schema::table('cv_templates', function (Blueprint $table) {
            $table->decimal('price_tickets', 12, 2)->default(0)->nullable(false)->change();
        });
    }

    /** قراءة إعداد مباشرةً — المايجريشن لا يعتمد على كاش التطبيق */
    private function setting(string $key, float $fallback): float
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return is_numeric($value) ? (float) $value : $fallback;
    }
};
