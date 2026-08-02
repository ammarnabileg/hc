<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الأكاديمية (13.4-ل): المسار الأكاديميّ ليس كيانًا جديدًا بل **علامة على المسار نفسه**
 * — فالكورسات هي نفس الكيانات الموجودة بلا نسخ ولا تكرار.
 * ومعه ربط المسار بالأقسام، والتسجيلات ومطالبات الـOTP (مرّة واحدة لكلّ متطوّع).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_paths', function (Blueprint $table) {
            // مسار أكاديمية = نفس جدول المسارات بعلامة، فلا ازدواج مصادر
            $table->boolean('is_academy')->default(false)->index();
            // «مسار الشهادة المستهدَف» Nullable — وفراغه يعني «تعليميّ صِرف» بلا أيّ CTA شهادة
            $table->unsignedBigInteger('target_path_id')->nullable()->index();
        });

        // ربط المسار الأكاديميّ بقسم/أكثر — وفراغ الربط يعني «الكلّ»
        Schema::create('academy_path_entity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['learning_path_id', 'entity_id'], 'academy_path_entity_unique');
        });

        Schema::create('volunteer_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url');
            $table->string('source', 24)->default('drive'); // drive · youtube
            $table->string('host_name')->nullable();
            // OTP اختياريّ — ووجوده وحده هو ما يجعل التسجيل مانحًا للنقاط
            $table->string('otp', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 24)->default('published')->index(); // published · draft
            $table->unsignedInteger('views_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('broken_reported_at')->nullable();
            $table->timestamps();
        });

        // منع الفارمينج: سجلّ واحد لكلّ (تسجيل، متطوّع) — والتحقّق Server-side
        Schema::create('volunteer_recording_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volunteer_recording_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('vxp_awarded', 12, 2)->default(0);
            $table->decimal('rep_awarded', 5, 2)->default(0);
            $table->timestamp('claimed_at');
            $table->timestamps();
            $table->unique(['volunteer_recording_id', 'user_id'], 'recording_claim_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_recording_claims');
        Schema::dropIfExists('volunteer_recordings');
        Schema::dropIfExists('academy_path_entity');

        Schema::table('learning_paths', function (Blueprint $table) {
            $table->dropColumn(['is_academy', 'target_path_id']);
        });
    }
};
