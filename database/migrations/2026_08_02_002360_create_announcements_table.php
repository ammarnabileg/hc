<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // التعليمات: قناة بثّ اتجاه واحد (13.2)
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('media_path')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->json('audience')->nullable(); // الكلّ · مسار · تدريب · دور · مستخدم
            $table->boolean('reactions_enabled')->default(false);
            $table->boolean('requires_acknowledge')->default(false);
            $table->unsignedInteger('acknowledge_xp')->default(0);
            $table->boolean('push_to_notifications')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
