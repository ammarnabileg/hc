<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔧 تصحيح فجوة 24.2-أوّلًا («الاجتماعات»): جدول الشاشة منصوصٌ فيه عمودان
 * لا وجود لهما في `meetings`، وإجراء صفٍّ ثالث بلا حقلٍ يحفظه:
 *
 *  - **«التسجيل»** عمودٌ في الجدول — والاجتماع الأونلاين له `external_link`
 *    (رابط الانعقاد) لكن **لا حقل لتسجيله بعد انتهائه**. فالرابطان مختلفان:
 *    الأوّل يُدخِلك الاجتماع قبله، والثاني يُريكَ ما فاتك بعده.
 *  - **«إلغاء بسبب»** إجراء صفّ — وحالات `status` الثلاث (`scheduled` ·
 *    `running` · `ended`) لا تعرف الإلغاء، فكان الملغى إمّا يبقى «قادمًا»
 *    كذبًا أو يُنهى فتُفتَح له نافذة حضورٍ لاجتماعٍ لم ينعقد.
 *
 * ولماذا `cancel_reason` عمودٌ صريح لا `minutes`؟ لأنّ المحضر **ما دار في
 * الاجتماع**، وسببُ الإلغاء **لماذا لم يدر**. خلطهما يجعل تقرير «منتهٍ بلا
 * محضر» يعدّ الملغى موثَّقًا — وهو عكس الحقيقة تمامًا.
 *
 * والأعمدة الثلاثة **قابلة للإفراغ**: كلّ اجتماعٍ قائم يبقى كما هو حرفيًّا،
 * و`status` تبقى سلسلةً بلا قيدٍ في القاعدة كما كانت — فقيمة `cancelled`
 * الجديدة إضافةٌ لا تكسر صفًّا واحدًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('recording_url')->nullable()->after('external_link');
            $table->text('cancel_reason')->nullable()->after('minutes');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropColumn(['recording_url', 'cancel_reason', 'cancelled_at']);
        });
    }
};
