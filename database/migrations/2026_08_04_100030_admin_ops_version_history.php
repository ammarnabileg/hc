<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجلّ الإصدارات (12.7-هـ): إصدار المنصّة قبل كلّ ترحيل وبعده، ومَن نفّذه.
        // لماذا؟ لأنّ «استرجاع بضغطة» بلا معرفة الإصدار الذي كنّا عليه قرارٌ في الظلام.
        Schema::create('app_version_history', function (Blueprint $table) {
            $table->id();
            $table->string('version', 32);                  // SemVer مثل 1.4.2
            $table->string('previous_version', 32)->nullable();
            $table->string('event', 24)->default('release'); // release · migrate · rollback
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('migrations_count')->default(0);
            $table->json('migrations')->nullable();          // أسماء الهجرات المنفَّذة/المسترجَعة
            $table->foreignId('backup_file_id')->nullable()->constrained('backup_files')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();

            $table->index(['event', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_version_history');
    }
};
