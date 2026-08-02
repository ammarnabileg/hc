<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تذاكر الدرس: العمود Override والإعداد هو الافتراضيّ العامّ (2.13 · 12.10).
 *
 * كان العمودان `NOT NULL DEFAULT 2/1`، فكلّ تدريب يولد ومعه رقمه الخاصّ —
 * ومعنى ذلك أنّ الإعداد العامّ في لوحة الإدارة **لا يظهر له أثر أبدًا** مهما
 * عدّله المالك، وهو بالضبط ما تمنعه القاعدة الذهبيّة.
 *
 * فصارا `nullable` بلا افتراضيّ: **NULL = اتبع الإعداد العامّ**، والقيمة =
 * استثناءٌ لهذا التدريب وحده. والصفوف الحاملة للقيمتين الافتراضيّتين القديمتين
 * تُفرَّغ مرّةً واحدة لأنّها لم تكن اختيارًا من أحد بل أثرًا للمخطّط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('tickets_before_half')->nullable()->default(null)->change();
            $table->unsignedInteger('tickets_after_half')->nullable()->default(null)->change();
        });

        // ما لم يختره أحد لا يُحسَب اختيارًا — والقيم المعدَّلة فعلًا تبقى كما هي.
        DB::table('courses')
            ->where('tickets_before_half', 2)
            ->where('tickets_after_half', 1)
            ->update(['tickets_before_half' => null, 'tickets_after_half' => null]);
    }

    public function down(): void
    {
        DB::table('courses')->whereNull('tickets_before_half')->update(['tickets_before_half' => 2]);
        DB::table('courses')->whereNull('tickets_after_half')->update(['tickets_after_half' => 1]);

        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('tickets_before_half')->default(2)->nullable(false)->change();
            $table->unsignedInteger('tickets_after_half')->default(1)->nullable(false)->change();
        });
    }
};
