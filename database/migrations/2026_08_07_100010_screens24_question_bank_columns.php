<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بنك الأسئلة المركزيّ (24.1-3).
     *
     * لماذا أعمدة جديدة؟ لأنّ البنك يحتاج ما لا يحتاجه سؤال الدرس المفرد:
     * **حالة** (نشط/معطّل) كي يُوقَف السؤال بلا حذفٍ يضيّع تاريخه، و**صعوبة**
     * كي يُفلتَر البنك بها، و**رابط مصدر** في أسئلة الامتحان كي يُعاد استعمال
     * السؤال الواحد في أكثر من امتحان ويُحسَب عدد استخداماته بصدق.
     */
    public function up(): void
    {
        Schema::table('lesson_questions', function (Blueprint $table) {
            // التعطيل بدل الحذف — السؤال المعطّل يخرج من الامتحان ويبقى في السجلّ
            $table->boolean('is_active')->default(true)->after('is_general');
            // easy · medium · hard — والقيم نفسها إعداد لا نصّ محروق (2.13)
            $table->string('difficulty', 16)->default('medium')->after('is_active');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            // نسخة السؤال داخل الامتحان تعرف أصلها في البنك ⟵ إعادة استخدام بلا ازدواج
            $table->foreignId('source_question_id')->nullable()->after('exam_id')
                ->constrained('lesson_questions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_question_id');
        });

        Schema::table('lesson_questions', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'difficulty']);
        });
    }
};
