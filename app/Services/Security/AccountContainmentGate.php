<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * ⭐ **جدار الاحتواء**: مَن يمرّ وهو محظور/معلَّق، وماذا يرى مَن لا يمرّ (12.1-متقدّم-1·3).
 *
 * الفجوة التي يسدّها هذا الملفّ: `UserModeration::isContained()` كانت دالّةً صحيحة
 * **لا يستدعيها أحد** — فحسابٌ `status='banned'` كان يفتح `/dashboard` بـ200 عاديًّا.
 * الحظر بلا جدارٍ عند الباب تسميةُ عرضٍ لا عقوبة.
 *
 * القواعد المنصوصة التي يطبّقها:
 *  · **المحظور لا يفتح أيّ صفحة** — يرى صفحة ثابتة «تم حظر الحساب» وتحتها سطر
 *    التواصل مع الدعم، وتحتها **كارت «رسالة إداريّة»** لو الأدمن كتب رسالة.
 *  · **التعليق المؤقّت ينتهي وحده** بانقضاء مدّته فيعود الحساب بلا تدخّل — والفحص
 *    هنا لأنّ أوّل طلبٍ بعد المدّة أعدلُ من انتظار كرون قد يقف.
 *  · **الخروج يفضل مفتوحًا** دائمًا وإلّا حبسنا المحظور في جلسةٍ لا يقدر يقفلها.
 */
class AccountContainmentGate
{
    public function __construct(
        private readonly UserModeration $moderation,
        private readonly Impersonator $impersonator,
    ) {}

    /**
     * هل يُمنَع صاحب هذا الطلب؟ (ويرفع التعليق المنتهي في طريقه)
     */
    public function blocks(Request $request): bool
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return false;
        }

        // المدّة انقضت؟ الحساب يرجع دلوقتي حالًا ويكمل طلبه بلا لفّة (12.1-متقدّم-3)
        if ($this->moderation->expireIfDue($user)) {
            return false;
        }

        if (! $this->moderation->isContained($user)) {
            return false;
        }

        return ! $this->passes($request);
    }

    /**
     * الاستثناءات — قليلة ومقصودة:
     *  1) **صفحة الاحتواء نفسها** وإلّا صار التحويل حلقةً لا تنتهي.
     *  2) **الخروج** فحقّ المستخدم يقفل جلسته مهما كانت حالته.
     *  3) **إنهاء الانتحال**: لو أدمن كان بيتصفّح كحساب اتحظر أثناء تصفّحه،
     *     لازم يقدر يرجع لحسابه — الجدار للمحظور لا لمَن يفحصه.
     *  4) مسارات الصحّة والويب هوك — خارج جلسة المستخدم أصلًا.
     */
    public function passes(Request $request): bool
    {
        if ($this->impersonator->isImpersonating($request)) {
            return true;
        }

        foreach ($this->exemptPaths() as $pattern) {
            if ($request->is($pattern) || $request->is(ltrim($pattern, '/'))) {
                return true;
            }
        }

        return false;
    }

    /** المسارات المستثناة — قائمة إعدادات لا قيم محروقة (2.13) */
    public function exemptPaths(): array
    {
        $configured = setting('account.containment.exempt_paths');

        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(array_map('strval', $configured)));
        }

        return ['account/blocked', 'logout', 'impersonate/stop', 'up', 'webhooks/*'];
    }

    /**
     * الجدار: صفحة ثابتة بلا ليَاوت ولا سايد بار — المحظور ما بيتصفّحش المنصّة.
     * والكود 403 لا 200 حتى لا تُفهرَس ولا تُخزَّن ولا تُقرَأ كصفحةٍ عاديّة.
     */
    public function wall(Request $request): Response
    {
        $user = $request->user();
        $banned = $user->status === 'banned';

        $payload = [
            'user' => $user,
            'banned' => $banned,
            'title' => $banned
                ? (string) setting('account.containment.banned_title', 'تم حظر الحساب')
                : (string) setting('account.containment.suspended_title', 'الحساب موقوف مؤقّتًا'),
            'support' => (string) setting(
                'account.containment.support_line',
                'إذا كنت تعتقد أنه بالخطأ رجاء التواصل مع دعم المنصة',
            ),
            'noticeTitle' => (string) setting('account.containment.notice_title', 'رسالة إداريّة'),
            'message' => (string) ($user->containment_reason ?? ''),
            'until' => $banned || $user->suspended_until === null
                ? null
                : Carbon::parse($user->suspended_until),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'contained' => true,
                'title' => $payload['title'],
                'message' => $payload['message'],
            ], 403);
        }

        return response()->view('security.contained', $payload, 403);
    }
}
