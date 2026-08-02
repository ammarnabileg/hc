<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // الستريك ونادي الخامسة صباحًا (7.2)
        Schema::create('streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_days')->default(0);
            $table->unsignedInteger('best_days')->default(0);
            $table->date('last_active_date')->nullable();
            $table->unsignedInteger('club_5am_count')->default(0);
            $table->timestamps();
            $table->unique('user_id');
        });
    }
};
