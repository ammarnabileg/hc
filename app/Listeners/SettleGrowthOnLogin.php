<?php

namespace App\Listeners;

use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Ads\AdEvents;
use App\Services\Growth\ProfileCompletion;
use App\Services\Referral\ReferralService;
use Illuminate\Auth\Events\Login;

/**
 * حلقات النموّ عند لحظة الدخول (21.1 · 21.3-أ).
 *
 * ⭐ **لماذا هنا لا في المتحكّم؟** لأنّ «يُفتَح على نفس الصفحة بعد التسجيل» كان
 *    مكسورًا: التسجيل ينتهي إلى «تحت المراجعة»، وبعد الاعتماد الإداريّ **تبدأ جلسة
 *    جديدة** فتضيع الوجهة المحفوظة في الجلسة. فتُثبَّت الوجهة على **المستخدم نفسه**
 *    لحظة تسجيله، وتُستهلك **مرّة واحدة** عند أوّل دخول بعد الاعتماد.
 */
class SettleGrowthOnLogin
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly ProfileCompletion $completion,
        private readonly AdEvents $events,
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        /*
         * «أتمّ التسجيل» (21.3-أ): الدخول الذي يلي `User::create()` مباشرةً هو
         * دخول التسجيل — و`wasRecentlyCreated` علامةٌ يضعها إلوكوينت على النسخة
         * نفسها، فنعرف اللحظة بدقّة بلا أن نزرع نداءً في متحكّم المصادقة.
         */
        if ($user->wasRecentlyCreated) {
            $this->events->record('registration_completed', $user, $user);
        }

        $this->rememberInviteLanding($user);
        $this->openInviteLandingOnce($user);

        if ($user->isActive()) {
            /*
             | بار «أكمل ملفك»: النسبة تُحدَّث هنا، والمِنح **هنا تحديدًا** لا في
             | `sync()` نفسها — `sync()` تُستدعى أيضًا من طلبات GET (الصفحة
             | وبار التذكير)، ومِنحٌ ماليٌّ (12.9) على GET يقدر يُطلَق بطلبٍ
             | مموَّه بلا ضغطة مستخدم. الدخول فعلٌ حقيقيّ من صاحب الحساب —
             | فهو الموضع الآمن، ومعه `grant()` نفسها idempotent كعادتها.
             */
            $this->completion->sync($user);
            $this->completion->grant($user);

            // الحدث الرابع من الثمانية: «فعّل الحساب» — أوّل دخولٍ بعد الاعتماد (21.3-أ)
            $this->recordActivationOnce($user);
        }
    }

    /** تثبيت وجهة الدعوة على المستخدم — من سطر الدعوة أو من الرابط المعلَّق في الجلسة */
    private function rememberInviteLanding(User $user): void
    {
        if ($user->invite_landing_url) {
            return;
        }

        $session = request()?->hasSession() ? request()->session() : null;
        $this->referrals->claimLanding($user, $session ? $session->pull('referral.pending_id') : null);

        $url = $this->referrals->landingUrlFor($user);

        if ($url) {
            $user->forceFill(['invite_landing_url' => $url])->saveQuietly();
        }
    }

    /**
     * أوّل دخولٍ بعد الاعتماد يفتح **نفس الصفحة** التي دُعي إليها — ثمّ لا يتكرّر.
     * ولا نطمس وجهةً قصدها المستخدم بنفسه (`url.intended` موجودة أصلًا).
     */
    private function openInviteLandingOnce(User $user): void
    {
        if (! $user->isActive() || ! $user->invite_landing_url || $user->invite_landing_seen_at) {
            return;
        }

        $session = request()?->hasSession() ? request()->session() : null;

        if ($session && ! $session->has('url.intended')) {
            redirect()->setIntendedUrl($user->invite_landing_url);
        }

        $user->forceFill(['invite_landing_seen_at' => now()])->saveQuietly();
    }

    /**
     * «فعّل الحساب» يُرسَل **مرّة واحدة لكلّ مستخدم**: الحارس وجودُ الحدث نفسه
     * في سجلّنا — فلا يتكرّر مع كلّ دخول ولا يعتمد على عمودٍ يكتبه غيرنا.
     */
    private function recordActivationOnce(User $user): void
    {
        if (! $user->activated_at) {
            return;
        }

        $already = TrackingEvent::query()
            ->where('user_id', $user->id)
            ->where('event', 'account_activated')
            ->exists();

        if (! $already) {
            $this->events->record('account_activated', $user, $user);
        }
    }
}
