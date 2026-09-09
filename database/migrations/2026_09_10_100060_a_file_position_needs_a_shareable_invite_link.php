<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔧 تصحيح فجوة: «العضويّة في الملفّ بالدعوة: رابط دعوة مبنيّ على البوزشن،
 * أو إضافة مباشرة على بوزشن محدّد» (دستور اساسي.md سطر 3851 · 4301) —
 * `FileDrafts::create()` كانت تقبل الإضافة المباشرة وحدها، وتتجاهل صفًّا
 * فيه بوزشن بلا عضوٍ بعينه بصمت رغم أنّ الفورم نفسه يصف الحقل «اختياريّ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_invite_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_invite_links');
    }
};
