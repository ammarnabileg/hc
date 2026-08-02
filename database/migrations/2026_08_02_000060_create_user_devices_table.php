<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_label')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }
};
