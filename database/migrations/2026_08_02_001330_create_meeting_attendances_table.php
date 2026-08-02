<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // registered · excused_absence · unexcused_absence
            $table->string('status', 32)->default('registered')->index();
            $table->timestamp('registered_at')->nullable();
            $table->unsignedInteger('hours_after_end')->nullable();
            $table->decimal('rep_value', 5, 2)->default(0);
            $table->text('excuse_reason')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['meeting_id', 'user_id']);
        });
    }
};
