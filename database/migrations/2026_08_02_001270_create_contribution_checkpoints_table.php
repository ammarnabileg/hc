<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // نقطتا تفتيش كحدّ أقصى — مهلة الردّ ساعتان داخل نافذة النشاط 9ص–12م
        Schema::create('contribution_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_contribution_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence'); // 1 أو 2
            $table->timestamp('scheduled_at');
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_body')->nullable();
            $table->string('status', 24)->default('pending')->index(); // pending · answered · missed
            $table->timestamps();
            $table->unique(['task_contribution_id', 'sequence']);
        });
    }
};
