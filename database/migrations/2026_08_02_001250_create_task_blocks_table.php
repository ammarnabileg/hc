<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // التعثّر بنوعيه: مدّة ≤ 3 أيّام أو Blocked By (23-3.4)
        Schema::create('task_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24); // duration · dependency
            $table->unsignedTinyInteger('days')->nullable();
            $table->text('reason');
            $table->foreignId('blocking_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('resume_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
