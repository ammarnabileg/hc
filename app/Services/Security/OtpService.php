<?php

namespace App\Services\Security;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * رموز التحقّق الرباعيّة (2.5-ب · 2.3).
 *
 * لماذا خدمة واحدة؟ لأنّ ثلاث شاشات تستعملها بنفس القواعد: تحقّق البريد عند
 * التسجيل · تأكيد حذف الحساب · استرجاع كلمة السرّ برمز — فلو كتب كلّ واحدة
 * قواعدها اختلفت المهل والمحاولات.
 *
 * ⭐ قاعدة الدستور: **رمز التسجيل لا يتغيّر أبدًا لنفس البريد** (2.5-ب) — فيُولَّد
 *    مرّة ويُعاد إرساله كما هو. وباقي الأغراض رموزٌ مؤقّتة تُجدَّد كلّ طلب.
 */
class OtpService
{
    public const PURPOSE_REGISTER = 'register_email';

    public const PURPOSE_DELETE = 'account_delete';

    public const PURPOSE_PASSWORD = 'password_reset';

    /** الأغراض التي يبقى رمزها ثابتًا مدى الحياة (2.5-ب) */
    private const PERMANENT = [self::PURPOSE_REGISTER];

    /**
     * إصدار الرمز وإرساله — ويحترم مهلة إعادة الإرسال فلا يُرسَل مرّتين في دقيقة.
     *
     * @return array{sent:bool, wait:int, resend_seconds:int}
     */
    public function send(string $email, string $purpose): array
    {
        $email = Str::lower(trim($email));
        $row = $this->row($email, $purpose);
        $resend = $this->resendSeconds();

        if ($row && $row->sent_at !== null) {
            $wait = $resend - (int) Carbon::parse($row->sent_at)->diffInSeconds(now());

            if ($wait > 0) {
                // ردّ فوريّ مفهوم بدل صمت: «استنّى كذا ثانية» (2.17-ب)
                return ['sent' => false, 'wait' => $wait, 'resend_seconds' => $resend];
            }
        }

        $code = $this->codeFor($email, $purpose, $row);

        DB::table('security_otp_codes')->updateOrInsert(
            ['email' => $email, 'purpose' => $purpose],
            [
                'code' => Crypt::encryptString($code),
                'attempts' => 0,
                'sent_at' => now(),
                'expires_at' => $this->expiryFor($purpose),
                'verified_at' => null,
                'updated_at' => now(),
                'created_at' => $row->created_at ?? now(),
            ],
        );

        $this->mail($email, $purpose, $code);

        return ['sent' => true, 'wait' => $resend, 'resend_seconds' => $resend];
    }

    /**
     * التحقّق من الرمز — ويُقفَل بعد عدد محاولات من الإعدادات.
     *
     * @return array{ok:bool, message:string}
     */
    public function verify(string $email, string $purpose, string $code): array
    {
        $email = Str::lower(trim($email));
        $row = $this->row($email, $purpose);

        if (! $row) {
            return ['ok' => false, 'message' => (string) setting('auth.otp.error_missing', 'مابعتناش رمز لسّه — اضغط «إرسال» الأوّل.')];
        }

        $max = max(1, (int) setting('auth.otp.max_attempts', 5));

        if ((int) $row->attempts >= $max) {
            return ['ok' => false, 'message' => (string) setting('auth.otp.error_locked', 'جرّبت كتير. استنّى شويّة واطلب رمزًا جديدًا.')];
        }

        if ($row->expires_at !== null && Carbon::parse($row->expires_at)->isPast()) {
            return ['ok' => false, 'message' => (string) setting('auth.otp.error_expired', 'الرمز ده انتهت صلاحيّته. اطلب رمزًا جديدًا.')];
        }

        if (! hash_equals($this->plain($row->code), trim($code))) {
            DB::table('security_otp_codes')->where('id', $row->id)
                ->update(['attempts' => (int) $row->attempts + 1, 'updated_at' => now()]);

            return ['ok' => false, 'message' => (string) setting('auth.otp.error_wrong', 'الرمز مش مظبوط. راجع بريدك وجرّب تاني.')];
        }

        DB::table('security_otp_codes')->where('id', $row->id)
            ->update(['verified_at' => now(), 'attempts' => 0, 'updated_at' => now()]);

        return ['ok' => true, 'message' => (string) setting('auth.otp.success', 'اتأكّد ✓')];
    }

    /** الثواني المتبقّية قبل السماح بإعادة الإرسال — لعدّاد الشاشة */
    public function secondsUntilResend(string $email, string $purpose): int
    {
        $row = $this->row(Str::lower(trim($email)), $purpose);

        if (! $row || $row->sent_at === null) {
            return 0;
        }

        return max(0, $this->resendSeconds() - (int) Carbon::parse($row->sent_at)->diffInSeconds(now()));
    }

    public function resendSeconds(): int
    {
        return max(5, (int) setting('auth.otp.resend_seconds', 60));
    }

    public function length(): int
    {
        return max(4, (int) setting('auth.otp.length', 4));
    }

    /** إسقاط رمز مؤقّت بعد استعماله — رموز التسجيل الدائمة لا تُمسَح */
    public function forget(string $email, string $purpose): void
    {
        if (in_array($purpose, self::PERMANENT, true)) {
            return;
        }

        DB::table('security_otp_codes')
            ->where('email', Str::lower(trim($email)))
            ->where('purpose', $purpose)
            ->delete();
    }

    // ------------------------------------------------------------------ داخليّ

    private function row(string $email, string $purpose): ?object
    {
        return DB::table('security_otp_codes')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->first();
    }

    /** رمز التسجيل يُعاد كما هو أبدًا (2.5-ب)، وغيره يُولَّد جديدًا كلّ مرّة */
    private function codeFor(string $email, string $purpose, ?object $row): string
    {
        if ($row && in_array($purpose, self::PERMANENT, true)) {
            $existing = $this->plain($row->code);

            if ($existing !== '') {
                return $existing;
            }
        }

        return $this->generate();
    }

    private function generate(): string
    {
        $length = $this->length();

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function plain(?string $encrypted): string
    {
        if ($encrypted === null || $encrypted === '') {
            return '';
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return '';
        }
    }

    private function expiryFor(string $purpose): ?Carbon
    {
        if (in_array($purpose, self::PERMANENT, true)) {
            return null;
        }

        return now()->addMinutes(max(1, (int) setting('auth.otp.ttl_minutes', 15)));
    }

    /** نصوص الرسالة من الإعدادات — ولا نصّ محروق (2.13) */
    private function mail(string $email, string $purpose, string $code): void
    {
        $subjects = [
            self::PURPOSE_REGISTER => (string) setting('auth.otp.subject_register', 'رمز تأكيد بريدك'),
            self::PURPOSE_DELETE => (string) setting('auth.otp.subject_delete', 'رمز تأكيد حذف حسابك'),
            self::PURPOSE_PASSWORD => (string) setting('auth.otp.subject_password', 'رمز استرجاع كلمة السرّ'),
        ];

        $body = str_replace(
            ['{code}', '{minutes}'],
            [$code, (string) (int) setting('auth.otp.ttl_minutes', 15)],
            (string) setting('auth.otp.body', "رمز التأكيد بتاعك: {code}\nلو مش إنت اللي طلبته، اهمل الرسالة دي."),
        );

        Mail::raw($body, fn ($message) => $message->to($email)->subject($subjects[$purpose] ?? $subjects[self::PURPOSE_REGISTER]));
    }
}
