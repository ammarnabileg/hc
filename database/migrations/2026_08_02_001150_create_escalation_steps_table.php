<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('escalation_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('level');
            $table->timestamp('opened_at');
            $table->timestamp('due_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('outcome', 32)->nullable(); // decided · timed_out
            $table->timestamps();
        });
    }
};
