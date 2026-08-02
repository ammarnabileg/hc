<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مايجريشن إضافيّ لمجال دورة العمل (BUILD.md §1 — لا نلمس القديم).
 *
 * لماذا؟ لأنّ الحالات التسع تختلف في «تفاصيل الطلب» (تاريخ التمديد المقترَح ·
 * الرابط المبلَّغ عنه · سبب السحب…)، والجدول الأصليّ يحفظ **القرار** ولا يحفظ
 * **الطلب**. فبلا هذا الحقل يضيع ما طلبه صاحب الحالة أصلًا (23 — القسم 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalations', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('decision_note');
        });
    }

    public function down(): void
    {
        Schema::table('escalations', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
