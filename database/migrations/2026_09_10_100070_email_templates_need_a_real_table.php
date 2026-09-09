<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «قوالب البريد» (email_templates.*) — صلاحيّةٌ مزروعةٌ بثمانية أفعال بلا أيّ
 * تنفيذ (لا Model ولا مايجريشن ولا شاشة). الهيدر المنصوص لشاشة الإشعارات
 * (24.3 · سطر 5065-5072) يربط بها زرّ «قوالب البريد» وعمود «نصّ القالب» —
 * وواحدةٌ فقط لكلّ نوع إشعارٍ محكوم (فهرسٌ فريد على `category` يسمح بأكثر من
 * `null` — قوالب عامّة مستوردة لم تُربَط بنوعٍ بعد).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable()->unique();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            // «مفعّل» — استخدام هذا القالب فعليًّا بدل النصّ الافتراضيّ الذي يمرّره المستدعي
            $table->boolean('is_enabled')->default(false);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
