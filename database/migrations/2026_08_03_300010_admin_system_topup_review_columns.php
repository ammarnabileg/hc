<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⛔ لا موافقة سريعة بضغطة (19.5-ب-5): الاعتماد لا يُقبَل إلّا بعد تسجيل
 * **مراجعة صورة الإيصال** فعليًّا — فنحتاج أثرًا لهذه المراجعة لا نيّةً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topup_requests', function (Blueprint $table) {
            $table->timestamp('receipt_reviewed_at')->nullable()->after('receipt_hash');
            $table->foreignId('receipt_reviewed_by')->nullable()->after('receipt_reviewed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('topup_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_reviewed_by');
            $table->dropColumn('receipt_reviewed_at');
        });
    }
};
