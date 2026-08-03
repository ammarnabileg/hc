<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سببٌ مقروء لكلّ رفض في سجلّ الويب هوك الخام (19.5-ج-2).
 *
 * كان العمود `result` كلمةً تقنيّة واحدة بطول 32 حرفًا — تكفي لـ`credited`
 * و`duplicate`، ولا تكفي لرفضٍ أمنيّ يحتاج **ماذا نقص وماذا يفعل** (2.17-ب).
 * ورفضٌ بلا سببٍ مكتوب يخفي هجومًا خلف كلمةٍ غامضة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_webhook_logs', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('result');
        });
    }

    public function down(): void
    {
        Schema::table('gateway_webhook_logs', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
