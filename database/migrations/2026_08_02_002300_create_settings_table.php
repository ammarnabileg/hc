<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // لكلّ ميزة إعدادات كاملة (2.13) — بنمط المفتاح «المجال.الميزة.المفتاح»
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group', 64)->index();
            $table->string('label_ar');
            $table->string('type', 24)->default('string'); // string · text · number · bool · json · color · media
            $table->text('value')->nullable();
            $table->text('default_value')->nullable();
            $table->text('hint')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_owner_only')->default(false);
            $table->timestamps();
        });
    }
};
