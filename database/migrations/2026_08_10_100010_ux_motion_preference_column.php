<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تفضيل الحركة **داخل المنصّة** (2.3 · 2.14-ب).
 *
 * لماذا عمود جديد؟ لأنّ الواجهة كانت تُطفئ الأنيميشن بـ`prefers-reduced-motion`
 * — وهو **مرفوض نصًّا** مرّتين لأنّ «الأنيميشن روح المنصّة»، وأخطر أثره أنّه كان
 * يُخفي **الكونفيتي نفسه** فتُلغى ذروة 2.9-6 العاطفيّة بصمت وبلا علم المستخدم.
 * فالتحكّم يصير من إعداد المستخدم هنا، **وافتراضه التشغيل** لا الإطفاء.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('motion_enabled')->default(true)->after('sound_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('motion_enabled');
        });
    }
};
