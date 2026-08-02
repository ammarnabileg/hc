<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تصحيح مطابقة الدستور:
 *
 * 1) نقاط الخبرة (القسم 7): **أُلغي مفهوم «نصف المهلة» للـXP** — القيمة تتناقص
 *    **خطّيًّا وباستمرار** من القيمة القصوى عند بداية التدريب حتى **صفر عند الديدلاين**.
 *    ونصف المهلة يبقى **للتذاكر وحدها**: تذكرتان قبله وواحدة بعده.
 *
 * 2) الإتاحة والجدولة (القسم 5): **فترات إتاحة متعدّدة** لكلّ تدريب،
 *    و**أوقات تشغيل يوميّة**، والفتح/الغلق بـ**التوقيت المحلّيّ للمستخدم** لا الخادم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // القيمة القصوى لكلّ درس — نقطة بداية التناقص الخطّيّ
            $table->unsignedInteger('xp_max')->default(0)->after('deadline_days');

            // التذاكر بنصف الديدلاين (7): 2 قبله · 1 بعده — وكلاهما إعداد لا رقم محروق
            $table->unsignedInteger('tickets_before_half')->default(2)->after('xp_max');
            $table->unsignedInteger('tickets_after_half')->default(1)->after('tickets_before_half');

            // أوقات التشغيل اليوميّة (5) — خارجها التدريب مقفول ولو كانت الفترة سارية
            $table->time('daily_open_at')->nullable()->after('tickets_after_half');
            $table->time('daily_close_at')->nullable()->after('daily_open_at');
        });

        // ترحيل القيم القائمة: الأعلى من القيمتين القديمتين يصير القيمة القصوى
        if (Schema::hasColumn('courses', 'xp_before_half')) {
            DB::table('courses')->update([
                'xp_max' => DB::raw('CASE WHEN xp_before_half >= xp_after_half THEN xp_before_half ELSE xp_after_half END'),
            ]);
        }

        // فترات الإتاحة المتعدّدة لكلّ تدريب (5)
        Schema::create('course_availability_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['course_id', 'starts_on', 'ends_on']);
        });
    }
};
