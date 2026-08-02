<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // العودة: إعادة الامتحان إجباريّة والشهادة القديمة تصير «منتهية» (13.4-ق)
        Schema::create('reentries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offboarding_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->foreignId('expired_certificate_id')->nullable()->constrained('certificates')->nullOnDelete();
            $table->foreignId('exam_attempt_id')->nullable()->constrained('exam_attempts')->nullOnDelete();
            $table->foreignId('new_certificate_id')->nullable()->constrained('certificates')->nullOnDelete();
            $table->string('status', 24)->default('in_progress')->index();
            $table->timestamps();
        });
    }
};
