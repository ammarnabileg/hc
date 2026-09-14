<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ **الوضع الفاتح صار الافتراضيّ** — «الهويّة البصريّة 2.0» (2.10.1-1، v5.7).
 *
 * كان عمود `users.theme` يُنشَأ بافتراضيّ `'dark'` (2026-08-02)، لأنّ الدستور
 * وقتها كان ينصّ «الوضع الافتراضيّ: داكن». بعد اعتماد الهويّة 2.0 بأمر المالك
 * (2026-09-14) صار النصّ معكوسًا حرفيًّا: «الوضع الفاتح هو الافتراضي، ويوجد
 * وضع داكن تجريبيّ» — فالعمود يجب أن يُنشئ كلّ مستخدمٍ جديد بـ`'light'`.
 *
 * **ولماذا لا نُصحِّح الصفوف القائمة** (بخلاف سابقةٍ مشابهة في هذا المستودع —
 * migration تصحيح `attempts_allowed`/`retry_cooldown_hours`، 2026-08-26 —
 * التي بدّلت كلّ صفٍّ يحمل القيمة الافتراضيّة القديمة)؟ لأنّ الفارق جوهريّ:
 * تلك الأعمدة **ثوابت سلوك نظاميّ** لا يملك المستخدم اختيارها بنفسه، فمطابقة
 * القيمة القديمة تعني يقينًا «لم يُخصَّص». أمّا `theme` **تفضيلٌ شخصيّ** له
 * فورمٌ صريح في `account/settings` يختار منه المستخدم «داكن» عمدًا — والقيمة
 * المخزَّنة لا تُميِّز «لم يلمسها أحد» عن «اختارها المستخدم بنفسه». فتبديل كلّ
 * صفٍّ قيمته `'dark'` إلى `'light'` قد يمحو اختيارًا حقيقيًّا بلا علم صاحبه.
 * **القرار:** يتغيّر افتراضيّ العمود لكلّ مستخدمٍ **جديد** فقط؛ ومَن له صفٌّ
 * موجودٌ بالفعل يبقى على قيمته الحاليّة — سواء كانت افتراضيّةً موروثة أو
 * اختيارًا واعيًا، فلا فرق بينهما في القاعدة، ولا سبيل آمنًا للتمييز.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'theme')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 16)->default('light')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'theme')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 16)->default('dark')->change();
        });
    }
};
