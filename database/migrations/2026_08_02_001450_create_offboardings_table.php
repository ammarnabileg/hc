<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // ثلاثة أنواع فقط، والإقصاء حصرًا عبر سلّم العتبات (13.4-س)
        Schema::create('offboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index(); // resignation · entity_ended · exclusion
            $table->text('reason')->nullable(); // لا يُنشَر للفريق
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notice_until')->nullable(); // 7 أيّام (تُصفَّر في الإقصاء)
            $table->json('clearance_checklist')->nullable();
            $table->boolean('exit_interview_done')->default(false);
            $table->text('exit_interview_notes')->nullable();
            $table->boolean('honorable_certificate_issued')->default(false);
            $table->timestamp('cooldown_until')->nullable(); // شهر · 3 شهور · لا عودة
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
};
