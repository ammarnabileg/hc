<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->string('layer', 24)->default('platform'); // platform · volunteer · user
            $table->boolean('is_system')->default(false);
            $table->boolean('is_deletable')->default(true);   // دور مالك المنصّة غير قابل للحذف
            $table->boolean('requires_membership')->default(false); // أدوار التطوّع تُسنَد داخل عضويّة
            $table->timestamps();
        });
    }
};
