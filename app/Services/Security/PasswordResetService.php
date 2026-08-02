<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * استرجاع كلمة السرّ (2.3): طلب ⟵ رمز/رابط ⟵ إعادة تعيين.
 *
 * ⭐ قاعدة أمنيّة ثابتة: **لا نكشف أبدًا إن كان البريد مسجّلًا أو لا** — الردّ واحد
 *    في الحالتين، وإلّا صارت الشاشة أداة تعداد حسابات.
 *
 * الرابط والرمز وجهان لنفس الطلب: الرابط للمُريح على الديسكتوب، والرمز الرباعيّ
 * لمن يفتح بريده على تليفون تاني — وكلاهما يفتح نفس شاشة إعادة التعيين.
 */
class PasswordResetService
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * إنشاء طلب استرجاع وإرساله. يرجع دائمًا نفس الرسالة المحايدة.
     *
     * @return array{sent:bool, message:string}
     */
    public function request(string $email): array
    {
        $email = Str::lower(trim($email));
        $neutral = (string) setting(
            'auth.password_reset.neutral_message',
            'لو البريد ده مسجّل عندنا، هتلاقي رسالة فيها رابط ورمز خلال دقايق. بصّ في «غير الهامّ» كمان.',
        );

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return ['sent' => true, 'message' => $neutral];
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        // الرمز الرباعيّ مسارٌ موازٍ للرابط — نفس الطلب ونفس الشاشة
        $this->otp->send($email, OtpService::PURPOSE_PASSWORD);

        $this->mailLink($user, $token);

        return ['sent' => true, 'message' => $neutral];
    }

    /** هل التوكن ساري لهذا البريد؟ (المهلة إعداد لا رقم محروق) */
    public function tokenIsValid(string $email, string $token): bool
    {
        $row = DB::table('password_reset_tokens')->where('email', Str::lower(trim($email)))->first();

        if (! $row) {
            return false;
        }

        if (Carbon::parse($row->created_at)->addMinutes($this->ttlMinutes())->isPast()) {
            return false;
        }

        return Hash::check($token, $row->token);
    }

    /**
     * إعادة التعيين بعد التحقّق — وتُنهي كلّ الجلسات القديمة (لو كان الاختراق سببَ الطلب).
     */
    public function reset(User $user, string $password): void
    {
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $this->otp->forget((string) $user->email, OtpService::PURPOSE_PASSWORD);

        DB::table('user_devices')->where('user_id', $user->id)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    public function ttlMinutes(): int
    {
        return max(5, (int) setting('auth.password_reset.ttl_minutes', 60));
    }

    /** رابط ذو توقيع بسيط: التوكن + البريد — والصلاحيّة تُفحَص عند الفتح */
    public function resetUrl(string $email, string $token): string
    {
        return route('password.reset', ['token' => $token, 'email' => $email]);
    }

    private function mailLink(User $user, string $token): void
    {
        $body = str_replace(
            ['{name}', '{url}', '{minutes}'],
            [$user->shortName(), $this->resetUrl((string) $user->email, $token), (string) $this->ttlMinutes()],
            (string) setting(
                'auth.password_reset.mail_body',
                "أهلًا {name}،\nده رابط تغيير كلمة السرّ: {url}\nالرابط صالح {minutes} دقيقة. لو مش إنت اللي طلبت، اهمل الرسالة.",
            ),
        );

        Mail::raw($body, fn ($message) => $message
            ->to((string) $user->email)
            ->subject((string) setting('auth.password_reset.mail_subject', 'تغيير كلمة السرّ')));
    }
}
