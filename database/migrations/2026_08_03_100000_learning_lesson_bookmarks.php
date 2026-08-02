<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حفظ درس (Bookmark) — اقتراح العرض المعتمَد 3.4-34.
 *
 * جدولٌ جديد لا تعديلَ على قائم (دليل البناء 1). والقيد الفريد على
 * (المستخدم، الدرس) هو الحارس: الحفظ حالة لا سجلّ متكرّر، فالضغط مرّتين
 * لا ينشئ صفّين والقرار كلّه في الخادم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_bookmarks');
    }
};
