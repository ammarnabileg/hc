<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * منطقة الخطر: حذف الحساب (2.3).
 *
 * ثلاث قواعد:
 *  1) **تأكيد برمز رباعيّ** يوصل بريد صاحب الحساب — فلا يُحذَف حساب بجهاز مفتوح.
 *  2) **Soft-delete** لا محو فوريّ: مهلة تراجع، والسجلّات المرتبطة (شهادات ·
 *     معاملات) لا تتفكّك من تحت غيرها.
 *  3) **رسالة تشرح بالضبط** ما يحدث للبيانات قبل الضغط لا بعده.
 */
class AccountDeletion
{
    public function __construct(private readonly OtpService $otp) {}

    /** إرسال رمز التأكيد لبريد صاحب الحساب نفسه */
    public function requestCode(User $user): array
    {
        return $this->otp->send((string) $user->email, OtpService::PURPOSE_DELETE);
    }

    public function verify(User $user, string $code): array
    {
        return $this->otp->verify((string) $user->email, OtpService::PURPOSE_DELETE, $code);
    }

    /**
     * التنفيذ: Soft-delete + إنهاء كلّ الجلسات + تعليم لحظة الطلب.
     * والبريد يُترَك كما هو فمهلة التراجع تحتاجه للتعرّف على الحساب.
     */
    public function delete(User $user): void
    {
        $user->forceFill([
            'deletion_requested_at' => now(),
            'status' => 'suspended',
        ])->save();

        DB::table('user_devices')->where('user_id', $user->id)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->otp->forget((string) $user->email, OtpService::PURPOSE_DELETE);

        $user->delete(); // Soft-delete — العمود موجود في جدول المستخدمين
    }

    /** أيّام مهلة التراجع — إعداد لا رقم محروق */
    public function graceDays(): int
    {
        return max(1, (int) setting('account.delete.grace_days', 30));
    }

    /**
     * ما يحدث للبيانات — سطر لكلّ نوع، من الإعدادات ليقدر الأدمن يعدّله
     * لو تغيّرت سياسة الاحتفاظ بلا تعديل كود (2.13).
     *
     * @return array<int,string>
     */
    public function dataNotes(): array
    {
        $notes = setting('account.delete.data_notes');

        if (is_array($notes) && $notes !== []) {
            return array_values($notes);
        }

        return [
            'بروفايلك وبياناتك الشخصيّة هتتشال من كلّ الشاشات فورًا.',
            'شهاداتك الصادرة هتفضل قابلة للتحقّق برقمها — دي حقّ الجهة اللي استلمتها.',
            'معاملات المحفظة والمشتريات بتفضل في السجلّ الماليّ بالقانون، بلا اسمك.',
            'مساهماتك في التطوّع بتفضل باسم «عضو سابق» علشان شغل الفريق ما يتكسرش.',
            'رصيدك الحاليّ بيسقط ومش هيرجع لو رجعت تاني.',
        ];
    }
}
