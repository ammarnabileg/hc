<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('celebration_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('celebration_event_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('reference');
            $table->timestamp('consumed_at');
            $table->timestamps();
        });
    }
};
