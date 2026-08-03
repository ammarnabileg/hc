<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 23 — 1.2 (سيناريو مشرف عام الملفّات) · 1.6: **مسودّات الملفّات**.
 *
 * «وإن لم يوجد ملفٌّ مناسب **أنشأ أثناء البناء «مسودّات ملفات» جديدة وربطها
 * بالحزم** — **ولا تتفعّل رسميًّا (عضويّات ودعوات) إلّا لحظة ضغط مشرف عام
 * التطوّع «إرسال للتنفيذ»** — فيظلّ الفتح حصريًّا للقمّة بصفر خطوة إضافيّة».
 *
 * ولماذا عمودٌ يربط المسودّة بهدفها بدل الاكتفاء بـ`status = 'draft'`؟ لأنّ
 * «تتفعّل مسودّات الملفّات **المربوطة**» تعني مسودّات **هذا الهدف** وحده —
 * فبلا الرابط يفتح إطلاقُ هدفٍ ملفّاتِ هدفٍ آخر ما زال قيد البناء، وهو فتحٌ
 * لم تضغطه القمّة: نقضٌ لحصريّة الفتح لا مجرّد خطأ عرض.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            // مسودّة ملفٍّ وُلدت داخل بناء هذا الهدف — تُفعَّل بإطلاقه وحده
            $table->foreignId('draft_goal_id')->nullable()->after('parent_id')
                ->constrained('goals')->nullOnDelete();

            $table->index(['status', 'draft_goal_id']);
        });

        Schema::table('memberships', function (Blueprint $table) {
            // الدعوة المكتوبة أثناء البناء: صفٌّ موجودٌ **بلا أثر** حتّى الإطلاق
            $table->timestamp('invited_at')->nullable()->after('started_at');
            $table->timestamp('activated_at')->nullable()->after('invited_at');

            /*
             * و`started_at` تصير قابلة للفراغ: العضويّة المدعوّة **لم تبدأ**.
             * وملؤها بتاريخ اليوم كان سيجعل الصفّ يكذب — تُحسَب مدّة خدمةٍ لم
             * تقع، ويظهر المدعوّ في تقارير الأقدميّة قبل أن يُفتَح ملفُّه.
             */
            $table->timestamp('started_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['invited_at', 'activated_at']);
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->dropIndex(['status', 'draft_goal_id']);
            $table->dropConstrainedForeignId('draft_goal_id');
        });
    }
};
