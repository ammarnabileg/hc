<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * أدوات احتواء الحساب المسيء (12.1).
 *
 * كانت `banned/suspended` **تسميتَي عرض بلا أثر** — هنا صارت أفعالًا:
 * حظر بحالة ورسالة · تعليق مؤقّت بمدّة تنتهي وحدها · إنهاء كلّ الجلسات ·
 * تأكيد البريد يدويًّا · رابط تغيير كلمة السرّ قابل للنسخ.
 *
 * وكلّ فعل هنا يُسجَّل في `audit_logs` — الاحتواء بلا أثرٍ مكتوب بابُ ظلم.
 */
class UserModeration
{
    public function __construct(private readonly PasswordResetService $passwords) {}

    /** حظر دائم: الحالة + رسالة يقرأها المستخدم عند محاولة الدخول */
    public function ban(User $actor, User $subject, string $reason): void
    {
        $this->apply($actor, $subject, [
            'status' => 'banned',
            'containment_reason' => $reason,
            'suspended_until' => null,
        ], 'user.ban');

        $this->endAllSessions($actor, $subject, audit: false);
    }

    /** تعليق مؤقّت بمدّة — ينتهي وحده بلا تدخّل، والحدّ الأقصى من الإعدادات */
    public function suspend(User $actor, User $subject, string $reason, int $days): void
    {
        $max = max(1, (int) setting('admin.moderation.suspend_max_days', 90));
        $days = max(1, min($days, $max));

        $this->apply($actor, $subject, [
            'status' => 'suspended',
            'containment_reason' => $reason,
            'suspended_until' => now()->addDays($days),
        ], 'user.suspend');

        $this->endAllSessions($actor, $subject, audit: false);
    }

    /** رفع الاحتواء: يرجع الحساب معتمَدًا وتُمسَح آثار المنع */
    public function release(User $actor, User $subject): void
    {
        $this->apply($actor, $subject, [
            'status' => 'active',
            'containment_reason' => null,
            'suspended_until' => null,
        ], 'user.release');
    }

    /** إنهاء كلّ جلساته فورًا — الأجهزة والسيشن معًا وإلّا فضل داخلًا */
    public function endAllSessions(User $actor, User $subject, bool $audit = true): int
    {
        $devices = DB::table('user_devices')->where('user_id', $subject->id)->count();

        DB::table('user_devices')->where('user_id', $subject->id)->delete();
        DB::table('sessions')->where('user_id', $subject->id)->delete();

        // إبطال «فكّرني» وإلّا رجع بالكوكي بعد إنهاء الجلسة
        $subject->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if ($audit) {
            $this->log($actor, $subject, 'user.sessions.end_all', [], ['devices' => $devices]);
        }

        return $devices;
    }

    /** تأكيد البريد يدويًّا — لمن اتعطّل عنده الرمز فلا يفضل حبيس شاشة تحقّق */
    public function verifyEmail(User $actor, User $subject): void
    {
        $this->apply($actor, $subject, ['email_verified_at' => now()], 'user.email.verify');
    }

    /**
     * رابط تغيير كلمة السرّ **قابل للنسخ** يعطيه الدعم للمستخدم على قناة موثوقة.
     * ولا يُغيَّر له الباسورد نيابةً عنه — التغيير يفضل بإيده.
     */
    public function passwordLink(User $actor, User $subject): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $subject->email],
            ['token' => bcrypt($token), 'created_at' => now()],
        );

        $this->log($actor, $subject, 'user.password.link', [], ['ttl_minutes' => $this->passwords->ttlMinutes()]);

        return $this->passwords->resetUrl((string) $subject->email, $token);
    }

    /** أسباب الحظر الجاهزة — قائمة إعدادات لا قيم محروقة (2.13) */
    public function reasons(): array
    {
        $reasons = setting('admin.moderation.reasons');

        return is_array($reasons) && $reasons !== [] ? array_values($reasons) : [
            'إساءة لمستخدم تاني',
            'محتوى مخالف',
            'محاولة اختراق أو تلاعب',
            'حساب مكرّر',
        ];
    }

    /** هل الحساب محتوًى الآن؟ (والتعليق ينتهي وحده بمرور مدّته) */
    public function isContained(User $subject): bool
    {
        if ($subject->status === 'banned') {
            return true;
        }

        return $subject->status === 'suspended'
            && $subject->suspended_until !== null
            && now()->lessThan($subject->suspended_until);
    }

    /**
     * ⛔ لا يُحتوى مالك المنصّة ولا يُحتوى المرء نفسه — حارس ضدّ قفل المنصّة بالغلط.
     */
    public function isProtected(User $actor, User $subject): bool
    {
        return $subject->id === $actor->id || $subject->isPlatformOwner();
    }

    public function log(User $actor, User $subject, string $action, array $old = [], array $new = []): AuditLog
    {
        $request = request() instanceof Request ? request() : null;

        return AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    private function apply(User $actor, User $subject, array $changes, string $action): void
    {
        $old = collect($changes)->keys()->mapWithKeys(fn ($key) => [$key => $subject->{$key}])->all();

        $subject->forceFill($changes + [
            'contained_by' => $actor->id,
            'contained_at' => now(),
        ])->save();

        $this->log($actor, $subject, $action, $old, $changes);
    }
}
