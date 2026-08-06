<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔧 تصحيح فجوة: لا مسار كان يحوّل حالة طلب الإفادة من «قيد الانتظار» —
 * فتبقى للأبد رغم نصّ 24.5 («الفورم/البوب-أب: … + حالة الطلب»). الرفض
 * بسبب واضح (على غرار `InterviewScorecard.rejection_reason`) — عمودٌ
 * خاصّ لا خلطًا مع نصّ الطلب الأصليّ في `body`. سجلّ القرارات 25.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attestations', function (Blueprint $table): void {
            $table->string('rejection_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attestations', function (Blueprint $table): void {
            $table->dropColumn('rejection_reason');
        });
    }
};
