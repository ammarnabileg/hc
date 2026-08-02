<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * لوحة الريفيرال والسفراء (24.2).
     *
     * الدستور يشترط للصرف: **استكمال بيانات المدعوّ + موافقة الأدمن** — ومعنى
     * ذلك أنّ للمكافأة حالةَ مراجعة مستقلّة عن كونها صُرفت أو لا. ومن غير هذه
     * الأعمدة يصير «مراجعة مكافآت معلّقة» كلامًا بلا أثر في قاعدة البيانات.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // pending · paid · held — والمعلّق يبقى معلّقًا حتّى قرارٍ صريح
            $table->string('payout_status', 16)->default('pending')->after('welcome_ticket_granted')->index();
            $table->text('payout_note')->nullable()->after('payout_status');
            // «مشبوه/تكرار» — وسمٌ يدويّ من التدقيق لا حكمٌ آليّ
            $table->boolean('is_flagged')->default(false)->after('payout_note');
            $table->foreignId('reviewed_by')->nullable()->after('is_flagged')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['payout_status', 'payout_note', 'is_flagged', 'reviewed_at']);
        });
    }
};
