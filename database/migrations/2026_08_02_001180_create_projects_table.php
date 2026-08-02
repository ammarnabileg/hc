<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // المشروع التشغيليّ للكيان: وعاء دائم لا يُغلَق (23)
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 24)->default('operational'); // operational · goal
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_permanent')->default(true);
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });
    }
};
