<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ مصدر الاكتساب **على المستخدم نفسه** — تنفيذًا لسلسلة 21.2-ح حرفيًّا:
 *
 *   «⭐ **لوحة مصادر الاكتساب** في الإحصائيّات: المصدر ⟵ التسجيل ⟵ التفعيل ⟵
 *    الشراء — **فلا يُصرَف على قناةٍ لا نعرف عائدها**.»
 *
 * ولماذا أعمدة على `users` لا صفوفٌ في `tracking_events` وحدها؟ لأنّ الوصلة
 * الأولى كانت مقطوعة: الحدث يقرأ الـUTM من **الطلب الجاري** (`request()->query`)،
 * و`POST /register` يأتي بلا query — فالمسجّل يُسجَّل بمصدرٍ `NULL`. النتيجة
 * المقيسة قبل هذه الهجرة: `visits=2 · registered=0`. فالمصدر يجب أن **يعبر**
 * من الزيارة إلى الحساب، ويبقى عليه بعد التفعيل وبعد الشراء.
 *
 * والحقول **الأربعة هي عين ما يولّده `UtmBuilder`** وما يخزّنه `tracking_events`
 * (21.2-ح: «UTM موحّد على كلّ رابط تولّده المنصّة») — بلا حقلٍ مخترَع لا يذكره النصّ.
 *
 * ⛔ ولا يُكتَب أيٌّ منها إلّا بموافقة غرض **«قياس داخليّ»** (21.3-د) — والحارس
 *    في `App\Services\Growth\AcquisitionSource` لا في هذه الهجرة.
 *
 * ⚠️ وتاريخها **بعد آخر هجرةٍ في الشجرة** عن قصد: الترتيب بالاسم لا بالنيّة،
 *    وهجرةٌ بتاريخٍ أسبق تُنفَّذ قبل ما تعتمد عليه — وقد وقع هذا هنا فعلًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // المصدر هو مفتاح صفّ اللوحة — فعليه فهرس، والباقي وصفٌ له
            $table->string('acquisition_utm_source')->nullable()->index();
            $table->string('acquisition_utm_medium')->nullable();
            $table->string('acquisition_utm_campaign')->nullable();
            $table->string('acquisition_utm_content')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'acquisition_utm_source',
                'acquisition_utm_medium',
                'acquisition_utm_campaign',
                'acquisition_utm_content',
            ]);
        });
    }
};
