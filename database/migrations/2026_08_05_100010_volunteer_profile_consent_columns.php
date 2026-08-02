<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أعمدة مسار موافقة إظهار التواصل (13.4-م-2) — ولا يُمَسّ المايجريشن القديم.
     *
     * لماذا هذه الأعمدة بالذات؟
     *  · `reason`       — «سبب الطلب» سطرٌ **اختياريّ** يظهر في إشعار صاحب البروفايل،
     *                     يرفع نسبة القبول ويقلّل الرفض العشوائيّ.
     *  · `responded_at` — لحظة الحسم (موافقة أو رفض)، وبها يُحسَب **متوسّط زمن الردّ**
     *                     في مؤشّرات الثقة، ومنها يبدأ **التبريد** لا من لحظة الإنشاء.
     *  · `decided_by`   — مَن حسم الطلب (صاحب البيانات دائمًا) — أثرٌ للتدقيق.
     */
    public function up(): void
    {
        Schema::table('consent_requests', function (Blueprint $table) {
            $table->string('reason', 300)->nullable()->after('field');
            $table->timestamp('responded_at')->nullable()->after('granted_at');
            $table->foreignId('decided_by')->nullable()->after('responded_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consent_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['reason', 'responded_at']);
        });
    }
};
