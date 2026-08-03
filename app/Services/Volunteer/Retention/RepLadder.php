<?php

namespace App\Services\Volunteer\Retention;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\LedgerService;

/**
 * ⭐ **مدخل سلّم عتبات الهبوط الواحد** (23-0.2 — إجراء عتبات الهبوط).
 *
 * النصّ يرسم **سلّمًا بثلاث درجات لا ثلاثة أنظمة**: «**−8 إنذار · −9.5 إنهاء
 * العضويّات الاختياريّة · −10 تعليق الحساب ولجنة تحقيق**» (23-0.2-7).
 *
 * ولماذا مدخلٌ واحد؟ لسببين لا ثالث لهما:
 *
 *  1) **الترتيب شرطُ صحّة لا ذوق.** النصّ عند −10 يقول صراحةً: «*(وبحكم البند 2،
 *     المعاملة الكاسرة لـ−10 تكون في القسم بالضرورة — **الاختياري انتهى
 *     قبلها**)*». فحركةٌ واحدة تهبط من −7 إلى −10 يجب أن تُوقِع الدرجات
 *     الثلاث **بترتيبها**: إنذارٌ ثمّ بترٌ ثمّ تعليق. ولو نُوديت الدرجات من
 *     ستّة جسورٍ متفرّقة لَاختلف الترتيب بين جسرٍ وآخر بلا أن يصرخ أحد.
 *
 *  2) **«فورًا» كلمةٌ في النصّ لا في الشرح.** الدرجات الثلاث كلّها موصوفة
 *     بالوقوع اللحظيّ («تُنهى **فورًا**» · «تعليق الحساب بالكامل **فورًا**» ·
 *     «تغطية بوزشنه **فورًا**») — فمكانها **لحظة الحركة** لا مسحة الغد.
 *
 * وكلّ درجةٍ محروسةٌ بصفّها الخاصّ (`volunteer_rep_warnings` · `volunteer_optional_cuts`
 * · `volunteer_suspensions`) فلا تقع مرّتين، والمسحة اليوميّة تبقى **شبكة أمان
 * خلفها** لا مسارًا موازيًا لها.
 */
final class RepLadder
{
    /**
     * تُنادى من كلّ جسرٍ يكتب حركة Rep — بعد كتابة الحركة لا قبلها.
     *
     * محروسةٌ بشرطين رخيصين قبل أيّ استعلام: **حركة سالبة** و**عملة Rep**؛
     * فلا تلمس مسار المنح ولا مسار VXP أصلًا.
     *
     * و`report()` لا رميُ الاستثناء: فشل درجةٍ يجب ألّا يمحو **المعاملة الواقعة**
     * — لكنّه لا يمرّ صامتًا، ويلتقطه المرور التالي للمسحة اليوميّة.
     */
    public static function afterRepMovement(
        ?User $user,
        string $currencyCode,
        float $signedAmount,
        ?Transaction $transaction = null,
    ): void {
        if ($user === null || $signedAmount >= 0 || $currencyCode !== LedgerService::REP) {
            return;
        }

        // الدرجات بترتيب النصّ: −8 ثمّ −9.5 ثمّ −10
        try {
            app(WarningRung::class)->enforce($user, $transaction);
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            app(OptionalCutService::class)->enforce($user);
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            app(SuspensionService::class)->enforce($user, $transaction);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
