<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->string('icon_path')->nullable();
            $table->string('color', 16)->nullable();
            $table->decimal('entry_cost', 12, 2)->default(0);
            $table->foreignId('entry_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->json('rewards')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->json('question_source')->nullable();
            $table->json('limits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('settings_locked')->default(false); // تُقفَل أثناء حرب نشطة
            $table->timestamps();
        });
    }
};
