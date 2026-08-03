<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ Audit الاعتراض (24.4-8): بانل التفاصيل ينصّ على **Audit** — ولا Audit بلا
 * جواب سؤال «مَن قرّر؟». كان الجدول يحفظ `decision_note` و`closed_at` بلا
 * صاحبٍ للقرار، فيظهر في الشاشة قرارٌ بلا اسم — وهذا نقصٌ في الأثر لا في العرض.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('objections', function (Blueprint $table) {
            if (! Schema::hasColumn('objections', 'decided_by')) {
                $table->foreignId('decided_by')->nullable()->after('decision_note')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('objections', function (Blueprint $table) {
            if (Schema::hasColumn('objections', 'decided_by')) {
                $table->dropConstrainedForeignId('decided_by');
            }
        });
    }
};
