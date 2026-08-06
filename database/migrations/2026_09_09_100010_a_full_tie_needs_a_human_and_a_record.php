<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سلّم الترقية الفوريّ (23-0.2 · القسم 0): تعادلٌ كاملٌ عبر المعايير الستّة
 * ونوافذها المتناقصة كلّها لا يُحسَم بخوارزميّة بل بقرار الدايركتور — أو
 * مشرف عام التطوّع إن كان الشاغر بوزشن الدايركتور نفسه — بمبرّرٍ مكتوب.
 * وهذا سجلّ ذلك القرار: مَن رُشِّح، ومَن حسم، ولماذا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vacated_membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            // مرشّحون تعادلوا في كلّ المعايير — لا فائز آليّ بينهم (23-0.2)
            $table->json('candidate_user_ids');
            $table->string('status', 24)->default('awaiting_decision')->index(); // awaiting_decision · decided
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decision_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_decisions');
    }
};
