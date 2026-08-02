<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // موافقة إظهار بيانات التواصل: 72 ساعة صلاحيّة الطلب · 72 ساعة تبريد · 30 يومًا صلاحيّة (13.4-م)
        Schema::create('consent_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('field', 32); // phone · email
            $table->string('status', 24)->default('pending')->index(); // pending · granted · expired · revoked
            $table->timestamp('request_expires_at');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('consent_expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'requester_id', 'field']);
        });
    }
};
