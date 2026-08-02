<?php

namespace App\Services\Security;

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

        $request->session()->put(self::SESSION_PENDING, $request->except(['_token']));

        return redirect()->route('register.verify');
    }
}
