<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الدرجة الأخيرة من سلّم العتبات: **التعليق عند −10** (23-0.2 — البند 4).
 *
 * النصّ حرفيًّا: «**عند بلوغ −10 (التعليق قبل أيّ إنهاء):** **تعليق الحساب
 * بالكامل فورًا** — كلّ العضويّات والدخول للوحة التطوّع … مهامه المفتوحة ⟵ مسار
 * عدم التسليم عند أبلاينها **بلا خصومات إضافيّة أثناء التعليق** (عقوبته الآن هي
 * التعليق ذاته) · ومساهماته تُسحَب بلا أثر … **⭐ تغطية بوزشنه فورًا:** لو كان له
 * **داونلاين**، تنتقل **مسؤوليّاته الإشرافيّة تلقائيًّا لأبلاينه المباشر** (تفويض
 * مؤقّت: المراجعات · نوافذ محرّك التصعيد · دفعات الصب-تاسكات) لحظة التعليق — فلا
 * يبقى فريق بلا مراجِع طوال مدّة التحقيق. ويعود التفويض تلقائيًّا عند إعادة
 * التفعيل، أو **يتحوّل شغورًا حقيقيًّا** (سلّم الترقية) عند قرار الإقصاء».
 *
 * ================== لماذا صفٌّ لا عَلَمٌ على المستخدم؟ ==================
 * لأنّ التعليق **حالةٌ لها عكس**: «(أ) فرصة: … **ويُعاد تفعيل حسابه وعضويّة
 * قسمه**». وإعادة التفعيل تحتاج أن تعرف **ما الذي كان نشِطًا قبلها بالضبط** —
 * أيّ عضويّات عُلِّقت ومَن كان يغطّي كلّ بوزشن — وإلّا صارت «إعادة التفعيل»
 * تخمينًا يعيد للمرء ما لم يكن له أو يسقط عنه ما كان له.
 *
 * والنصّ يقطع كذلك بأنّ الحالة **لا تُفَكّ بخوارزميّة**: «**التصفير الشهري لا
 * يفكّ التعليق** — الدرجة رقمٌ يتصفّر، والتعليق **حالة حساب** لا تُلغى بخوارزميّة
 * تقويم». فلا `deprived_until` هنا ولا موعد انتهاءٍ محسوب — بخلاف صفّ البتر
 * تمامًا. الإفراج قرارٌ بشريّ يُكتَب في `released_*`، وما عداه فالصفّ مفتوح.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('threshold', 6, 2);
            $table->decimal('rep_at_suspension', 6, 2);

            $table->unsignedInteger('memberships_suspended')->default(0);
            $table->unsignedInteger('tasks_handed_over')->default(0);
            $table->unsignedInteger('contributions_withdrawn')->default(0);
            $table->unsignedInteger('positions_covered')->default(0);

            /*
             | ⭐ **خريطة التغطية**: لكلّ عضويّة عُلِّقت ⟵ مَن يحمل مسؤوليّاتها
             | الإشرافيّة الآن. تُقرأ حيّةً في `HandlerChain` (فتُوجَّه نوافذ
             | داونلاينه للأبلاين المباشر) وتُقرأ عند الإفراج (فيرجع كلٌّ لمكانه).
             */
            $table->json('coverage')->nullable();
            $table->json('detail')->nullable();

            // إحالة لجنة التحقيق المولودة مع التعليق — «التعليق قبل أيّ إنهاء»
            $table->unsignedBigInteger('referral_id')->nullable()->index();

            // الإفراج: قرارٌ بشريّ لا انقضاء مدّة (فرصة اللجنة · أو إنهاء بالإقصاء)
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('release_reason', 64)->nullable();
            $table->string('release_note')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_suspensions');
    }
};
