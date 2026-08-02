<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // محرّك التصعيد: 24 ساعة لكلّ مستوى و48 عند السقف، بتسويات آليّة (23-8)
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->string('case_type', 48)->index(); // extension · blocked · apology · no_delivery · arbitration · contributor_withdraw · broken_link · repeated_return · subtask_batch
            $table->morphs('subject');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('current_handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->timestamp('window_due_at');
            $table->boolean('is_top_level')->default(false); // 48 ساعة
            $table->string('status', 24)->default('open')->index(); // open · decided · auto_settled
            $table->string('decision', 32)->nullable();
            $table->text('decision_note')->nullable();
            $table->boolean('auto_settled')->default(false);
            $table->boolean('slowdown_penalty_applied')->default(false);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }
};
