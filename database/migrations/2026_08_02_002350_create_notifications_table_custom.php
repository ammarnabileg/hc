<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('layer', 16)->default('platform')->index(); // platform · volunteer
            $table->string('category', 48)->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url')->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('deadline_at')->nullable(); // عدّاد ملوّن
            $table->boolean('requires_action')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
    }
};
