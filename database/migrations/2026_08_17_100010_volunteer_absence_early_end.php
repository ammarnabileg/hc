<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الإنهاء المبكّر لوضع «غائب» (الدستور 23-6 · 24).
 *
 * لماذا عمودٌ مستقلّ ولا نكتفي بتقصير `to_date`؟ لأنّ تقصير التاريخ **يمحو
 * الحقيقة**: لا يبقى في السجلّ أنّ الغياب كان أطول وأُنهي مبكّرًا، ولا مَن
 * أنهاه ولا لماذا — وشاشة الإدارة مطلوبٌ منها **سجلّ تدقيق** لا جدولٌ يُعاد
 * كتابته. والأهمّ: **تجميد الساعات** يُفَكّ بمدّة الغياب الفعليّة (من البداية
 * حتى لحظة الإنهاء) لا بالمدّة المعلَنة أوّلًا — وإلّا أخذ العائد مبكّرًا
 * إزاحةً على أيّامٍ عمل فيها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_absences', function (Blueprint $table) {
            $table->timestamp('ended_at')->nullable()->after('thawed_at');
            $table->foreignId('ended_by')->nullable()->after('ended_at')->constrained('users')->nullOnDelete();
            $table->string('ended_note')->nullable()->after('ended_by');
        });
    }

    public function down(): void
    {
        Schema::table('membership_absences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ended_by');
            $table->dropColumn(['ended_at', 'ended_note']);
        });
    }
};
