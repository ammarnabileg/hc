<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 16)->default('SELF');
            $table->string('effect', 8)->default('allow'); // allow · deny — و Deny > Allow
            $table->json('conditions')->nullable();
            $table->timestamps();
            $table->unique(['role_id', 'permission_id', 'scope'], 'permission_role_unique');
        });
    }
};
