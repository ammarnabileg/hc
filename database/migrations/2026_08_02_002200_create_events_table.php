<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('mode', 16)->default('online'); // online · offline · hybrid
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('join_link')->nullable();
            $table->string('recording_link')->nullable();
            $table->decimal('price_coins', 12, 2)->default(0);
            $table->decimal('price_tickets', 12, 2)->default(0);
            $table->string('attendance_code', 32)->nullable();
            $table->unsignedInteger('xp_reward')->default(0);
            $table->unsignedInteger('ticket_reward')->default(0);
            $table->foreignId('certificate_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamps();
        });
    }
};
