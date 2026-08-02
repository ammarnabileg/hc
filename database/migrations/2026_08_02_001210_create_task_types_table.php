<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('task_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name_ar');
            $table->string('color', 16)->nullable();
            $table->string('icon')->nullable();
            $table->text('default_brief')->nullable();
            $table->text('default_deliverable_spec')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
