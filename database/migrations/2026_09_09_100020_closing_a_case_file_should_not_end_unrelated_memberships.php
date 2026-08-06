<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔧 تصحيح فجوة: «انتهاء كيان مؤقّت (ملفّ)» (23-0.2 · §1518 · §3813) يقول
 * إنّ إنهاء الملفّ **يُقفل عضويّاته هو وحدها** — بينما `complete()` كانت
 * تُنهي **كلّ** عضويّات المستخدم النشِطة بلا تفريق، فمن له عضويّة قسمٍ
 * أخرى معه كانت تُقفَل ظلمًا لمجرّد أنّه شارك في ملفّ انتهى.
 *
 * عمودٌ اختياريّ: null = السلوك القديم (استقالة/إقصاء — كلّ العضويّات)،
 * ومحدَّدٌ = الحصر بكيانٍ واحد (انتهاء ملفّ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offboardings', function (Blueprint $table): void {
            $table->foreignId('entity_id')->nullable()->after('user_id')->constrained('entities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offboardings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('entity_id');
        });
    }
};
