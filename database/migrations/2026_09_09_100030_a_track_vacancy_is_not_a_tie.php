<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شغور مشرف عام المسار (رتبة 5) مستثنًى صراحةً من الترقية الفوريّة (23-0.2
 * «قاعدة نهائيّة»): يرفع دايركتورات المسار مؤقّتًا لمشرف عام التطوّع، ثمّ
 * يملؤه هو بأحد مسارين (كود مباشر أو معاينة سلّم) — قرارٌ مختلفٌ جوهريًّا
 * عن تعادل السلّم العاديّ رغم مشاركته الجدول نفسه (نفس شكل «مرشّحون + حسم
 * بمبرّر»). عمودٌ يميّز الحالتين بدل جدولٍ موازٍ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_decisions', function (Blueprint $table): void {
            $table->string('kind', 16)->default('tie')->after('position_id')->index(); // tie · track_vacancy
        });
    }

    public function down(): void
    {
        Schema::table('promotion_decisions', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
