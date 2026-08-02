<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // التحكيم: قرار نهائيّ لا يُعاد — (+)/(−)/قيمة وسط أو حفظ القضيّة (23)
        Schema::create('arbitrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_contribution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('arbiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('claim');
            $table->string('attachment_path')->nullable();
            $table->string('status', 24)->default('open')->index(); // open · messages_locked · decided
            $table->timestamp('window_due_at')->nullable();
            $table->string('decision_type', 32)->nullable(); // award · deduct · split · shelved
            $table->decimal('owner_amount', 12, 2)->nullable();
            $table->decimal('contributor_amount', 12, 2)->nullable();
            $table->text('decision_justification')->nullable(); // مبرّر إجباريّ
            $table->boolean('conflict_of_interest_skipped')->default(false);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }
};
