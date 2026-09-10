<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الاستهداف/الدعوة بشريحة + إشعار» (12.11) — دعوة أعضاء شريحة جمهورٍ محفوظة
 * (AdAudience) لفعاليّة، بإشعارٍ فعليّ، لا الإشعار القديم الذي كان يصل
 * **مسجَّلي الفعاليّة وحدهم** أيًّا كان مصدر الإشعار.
 *
 * `segment_id` نُللَبل: صفٌّ بلا قيمة = إشعار المسجّلين العاديّ كما كان؛
 * وبقيمة = دعوة شريحة، ومصدر المستلمين يتحوّل في `ReminderScheduler` تبعًا
 * لهذا العمود لا بجدولٍ جديد يكرّر منطق التسليم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_notices', function (Blueprint $table) {
            $table->foreignId('segment_id')->nullable()->after('event_id')
                ->constrained('ad_audiences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_notices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('segment_id');
        });
    }
};
