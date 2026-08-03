<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عدّاد محاولات المعالجة على صفّ التصعيد (الدستور 23 — القسم 5).
 *
 * لماذا عمودٌ جديد لا تعديل القديم؟ لأنّ العزل بلا عدّاد كان يخلط بين خطأٍ
 * **عابر** (`database is locked` لحظةَ ازدحام) وعطبٍ **دائم** في الصفّ نفسه،
 * فيقتل الحالة الصحيحة إلى الأبد — وهو إلغاءٌ صامت لقاعدة الـ24 ساعة نفسها:
 * «فاتت ⟵ الحالة تطلع للأبلاين الأعلى» (23-5). والحالة المعزولة لا تطلع لأحد.
 *
 * والعدّاد يحرس الطرفين: العابر يُعاد في الدورة التالية، والدائم يُعزَل **بعد
 * استنفاد السقف** فلا يعود يُسقِط الدورة كلّ خمس دقائق. والسبب والتوقيت
 * محفوظان ليكون العزل **مرئيًّا** لا صامتًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalations', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('slowdown_penalty_applied');
            $table->timestamp('last_failure_at')->nullable()->after('attempts');
            $table->text('last_failure_reason')->nullable()->after('last_failure_at');
        });
    }

    public function down(): void
    {
        Schema::table('escalations', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_failure_at', 'last_failure_reason']);
        });
    }
};
