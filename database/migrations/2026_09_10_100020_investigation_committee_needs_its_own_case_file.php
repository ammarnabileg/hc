<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ملفّ لجنة التحقيق (23-0.2-4): المسودّة الآليّة (`volunteer_committee_referrals`)
 * كانت تتوقّف عند فتح صفٍّ واحد — ولا تشكيل لمقعدَي اللجنة ولا ميتينج ولا قرار.
 * هذا الجدول هو الملفّ الفعليّ بعد ضغطة «تفعيل» مشرف عام التطوّع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->unique()->constrained('volunteer_committee_referrals')->cascadeOnDelete();
            $table->foreignId('suspension_id')->nullable()->constrained('volunteer_suspensions')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // open · verdict_recorded · recommendation_raised · decision_issued · closed
            $table->string('status', 24)->default('open')->index();

            $table->foreignId('seat_upline_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seat_dept_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seat_override_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();

            $table->timestamp('meeting_scheduled_at')->nullable();
            $table->unsignedTinyInteger('reschedule_count')->default(0);
            $table->boolean('absent_in_person')->default(false);

            // chance · recommend_dismissal
            $table->string('verdict', 24)->nullable();
            $table->text('verdict_reason')->nullable();
            $table->foreignId('verdict_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verdict_at')->nullable();

            // dismiss · reject_recommendation
            $table->string('decision', 24)->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decision_at')->nullable();

            $table->foreignId('offboarding_id')->nullable()->constrained('offboardings')->nullOnDelete();

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            // معاملات آخر 90 يومًا + توثيق التواصل عند −8 — لقطةٌ لا استعلامٌ حيّ (23-0.2-4: «النظام يطبع الحقيقة»)
            $table->json('dossier_snapshot')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_cases');
    }
};
