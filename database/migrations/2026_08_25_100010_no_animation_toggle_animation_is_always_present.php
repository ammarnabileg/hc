<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رفع عمود `users.motion_enabled` — تنفيذًا لنصّ 2.3 حرفيًّا:
 *
 *   «**Toggle للصوت فقط** … **⛔ ولا يوجد Toggle للأنيميشن — الأنيميشن حاضر
 *   دائمًا لأنّه روح المنصّة** (ولا يُوقَف تلقائيًّا بـ`prefers-reduced-motion`).»
 *
 * ونصّ 2.14-ب: «**الصوت** يخضع لـ**Toggle الصوت** في إعدادات البروفايل؛
 * **الأنيميشن حاضر دائمًا** (روح المنصّة).»
 *
 * فالعمود نفسه هو **الباب**: ما دام موجودًا يبقى للإطفاء مفتاحٌ يُقرَأ ويُكتَب،
 * ولو خلت منه الواجهة. والنصّ لا ينفي زرًّا في شاشة، بل ينفي **وجود التوجّل**.
 *
 * ⚠️ وتاريخها بعد آخر هجرةٍ في الشجرة عن قصد: الترتيب بالاسم لا بالنيّة،
 * فهجرةٌ بتاريخٍ أسبق تُسقِط العمود ثمّ تعيده هجرةُ إضافته بعدها.
 *
 * وبديل التوجّل ليس `prefers-reduced-motion` — فهو **مرفوض بالاسم** في 2.3
 * مرّتين — بل **لا بديل**: الحركة حاضرة، نقطة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'motion_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('motion_enabled');
        });
    }

    /**
     * الرجوع لا يعيد التوجّل: غيابه نصٌّ حاكم لا عطبٌ يُتراجَع عنه،
     * وعمودٌ بلا كودٍ يقرؤه يعيد الوهم لا الوظيفة.
     */
    public function down(): void {}
};
