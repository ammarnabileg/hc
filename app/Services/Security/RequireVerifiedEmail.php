<?php

namespace App\Services\Security;

use App\Http\Controllers\Auth\AuthController;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * ⭐ لا تسجيل بلا تحقّق OTP للبريد (2.5-ب).
 *
 * الحارس بديلٌ عن تعديل متحكّم التسجيل: يوقف الإنشاء لحدّ ما البريد يتأكّد،
 * ويحفظ مدخلات الفورم في السيشن ثمّ يرجّعها كما هي بعد التأكيد — فالمستخدم
 * ما يعيدش كتابة حاجة (2.17-ب).
 *
 * وبريدٌ **غير صالح أو مستعمَل** يعدّي كما هو ليقول له المتحكّم رسالة الخطأ
 * الصحيحة، فلا نرسل رمزًا لبريد مرفوض أصلًا.
 *
 * ⭐ والقاعدة نفسها تسري على **كلّ** الفورم لا على البريد وحده: الحارس يسبق
 * تحقّق المتحكّم، فلو مرّرنا حمولةً ناقصة كان المستخدم يؤكّد بريده ثمّ تسقط
 * الإعادة في التحقّق فيُرَدّ للفورم بعد أن مشى الطريق كلّه. فنسأل **بنفس
 * قواعد المتحكّم حرفيًّا** أوّلًا، والفاشل يعدّي ليقرأ خطأه فورًا (2.17-ب).
 */
class RequireVerifiedEmail
{
    public const SESSION_VERIFIED = 'security.verified_email';

    public const SESSION_PENDING = 'security.pending_registration';

    public function handle(Request $request, Closure $next): Response
    {
        $email = Str::lower(trim((string) $request->input('email')));

        if ($email === '' || Validator::make(['email' => $email], ['email' => ['email', 'max:190']])->fails()) {
            return $next($request);
        }

        if (User::query()->where('email', $email)->withTrashed()->exists()) {
            return $next($request);
        }

        if (Str::lower((string) $request->session()->get(self::SESSION_VERIFIED)) === $email) {
            return $next($request);
        }

        $pending = $request->except(['_token']);

        // ⭐ لا رمز لفورمٍ لن يكتمل: أيّ خطأ تحقّقٍ آخر يعدّي للمتحكّم ليقوله في
        // الفورم فورًا، بدل تأكيدٍ للبريد ينتهي بردّ المستخدم من حيث أتى (2.17-ب)
        if (app(AuthController::class)->registrationValidator($pending)->fails()) {
            return $next($request);
        }

        $request->session()->put(self::SESSION_PENDING, $pending);

        return redirect()->route('register.verify');
    }
}
