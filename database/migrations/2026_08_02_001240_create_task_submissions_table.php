<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('task_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->text('body')->nullable();
            $table->string('link')->nullable();
            $table->string('file_path')->nullable();
            $table->text('note')->nullable();
            $table->string('review_result', 24)->nullable(); // approved · returned
            $table->string('return_reason_code', 48)->nullable(); // من قائمة الأسباب العشرة
            $table->text('review_feedback')->nullable();
            $table->timestamp('fix_due_at')->nullable(); // مهلة إصلاح مستقلّة
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }
};
