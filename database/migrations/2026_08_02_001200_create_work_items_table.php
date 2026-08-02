<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // البند: المهامّ تُربَط به إلزاميًّا، وله وعاء VXP
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_package_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('brief')->nullable();
            $table->text('deliverable_spec')->nullable();
            $table->decimal('vxp_pool', 12, 2)->default(0);
            $table->decimal('vxp_spent', 12, 2)->default(0);
            $table->decimal('progress_percent', 5, 2)->default(0);

            // البنود المتكرّرة في المشروع التشغيليّ
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence', 24)->nullable(); // daily · weekly · monthly
            $table->unsignedInteger('relative_deadline_hours')->nullable();
            $table->string('audience_mode', 24)->nullable(); // individual · rotation · public_board
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('next_generation_at')->nullable();

            $table->boolean('is_public_board_candidate')->default(false);
            $table->timestamps();
        });
    }
};
