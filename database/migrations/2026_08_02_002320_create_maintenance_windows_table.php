<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // وضع الصيانة العامّ + تجميد كلّ المهل طوال مدّته (12.7-و-1)
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->text('message')->nullable();
            $table->unsignedInteger('planned_hours')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('expected_end_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('deadlines_recomputed')->default(false);
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
