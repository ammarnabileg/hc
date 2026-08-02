<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // خصوصيّة كلّ حقل (10 · 13.4-م) — والمحافظة عامّة دائمًا ولا تُدرَج هنا (12.14-د)
        Schema::create('user_privacy_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('field', 64);
            $table->string('visibility', 32)->default('supervisors'); // all_users · all_volunteers · supervisors
            $table->timestamps();
            $table->unique(['user_id', 'field']);
        });
    }
};
