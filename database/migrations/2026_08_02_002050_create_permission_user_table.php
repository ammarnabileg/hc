<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // استثناءات فرديّة (منح/منع) فوق الأدوار — بنفس قاعدة Deny > Allow
        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 16)->default('SELF');
            $table->string('effect', 8)->default('allow');
            $table->json('conditions')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'user_id', 'membership_id', 'scope'], 'permission_user_unique');
        });
    }
};
