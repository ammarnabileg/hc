<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // جهة الطوارئ — ظاهرة دائمًا للأبلاينز، تُضاف من الإعدادات (13.4-م)
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('relation', 64)->nullable();
            $table->timestamps();
        });
    }
};
