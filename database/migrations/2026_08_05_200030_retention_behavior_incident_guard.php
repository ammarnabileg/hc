<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قفل «معاملة واحدة لكلّ واقعة» + ربط الجسيمة بمحرّك التصعيد (13.4-ن-هـ).
 *
 * لماذا قيدٌ في قاعدة البيانات لا فحصٌ في الكود وحده؟ لأنّ الفحص وحده يسقط
 * عند نداءَين متزامنَين، والدستور يقول «**معاملة واحدة لكلّ واقعة**» — فتُخصَم
 * الواقعة الواحدة مرّتين ويصير الرقم كذبًا. والقيد على الثلاثيّ:
 * (العضو · المخالفة · مرجع الواقعة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('behavior_transactions', function (Blueprint $table) {
            $table->string('incident_ref', 64)->nullable()->after('behavior_violation_id');
            $table->foreignId('escalation_id')->nullable()->after('transaction_id')
                ->constrained()->nullOnDelete();
        });

        // الصفوف القديمة تأخذ مرجعًا فريدًا لكلّ صفّ — فلا يبطل القيد على تاريخٍ سابق
        foreach (DB::table('behavior_transactions')->pluck('id') as $id) {
            DB::table('behavior_transactions')->where('id', $id)->update(['incident_ref' => 'legacy:'.$id]);
        }

        Schema::table('behavior_transactions', function (Blueprint $table) {
            $table->unique(['user_id', 'behavior_violation_id', 'incident_ref'], 'behavior_incident_unique');
        });
    }

    public function down(): void
    {
        Schema::table('behavior_transactions', function (Blueprint $table) {
            $table->dropUnique('behavior_incident_unique');
            $table->dropConstrainedForeignId('escalation_id');
            $table->dropColumn('incident_ref');
        });
    }
};
