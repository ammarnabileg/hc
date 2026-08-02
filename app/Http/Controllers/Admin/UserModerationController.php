<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\Impersonator;
use App\Services\Security\UserModeration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * أدوات احتواء الحساب المسيء داخل صفحة المستخدم (12.1).
 *
 * كلّ فعل هنا **بصلاحيّته** من المصفوفة (12.2.1): `account_suspension.*` للحظر
 * والتعليق · `user_sessions.delete` لإنهاء الجلسات · `impersonation.*` للتصفّح
 * كمستخدم (مجموعة محميّة لمالك المنصّة) — والحراسة على المسار لا في الفيو وحده.
 */
class UserModerationController extends Controller
{
    public function __construct(
        private readonly UserModeration $moderation,
        private readonly Impersonator $impersonator,
    ) {}

    public function ban(Request $request, User $user): RedirectResponse
    {
        if ($guard = $this->guard($request, $user)) {
            return $guard;
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'اكتب سبب الحظر — المستخدم لازم يعرف حصل إيه.',
        ]);

        $this->moderation->ban($request->user(), $user, $data['reason']);

        return $this->back($user, 'الحساب اتحظر ✓ — وكلّ جلساته اتقفلت.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($guard = $this->guard($request, $user)) {
            return $guard;
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'days' => ['required', 'integer', 'min:1', 'max:'.(int) setting('admin.moderation.suspend_max_days', 90)],
        ], [
            'reason.required' => 'اكتب سبب التعليق — المستخدم لازم يعرف حصل إيه.',
            'days.required' => 'حدّد مدّة التعليق بالأيّام.',
            'days.max' => 'أقصى مدّة تعليق '.(int) setting('admin.moderation.suspend_max_days', 90).' يوم.',
        ]);

        $this->moderation->suspend($request->user(), $user, $data['reason'], (int) $data['days']);

        return $this->back($user, 'الحساب اتعلّق '.$data['days'].' يوم ✓ — وبيرجع لوحده بعدها.');
    }

    public function release(Request $request, User $user): RedirectResponse
    {
        $this->moderation->release($request->user(), $user);

        return $this->back($user, 'الاحتواء اترفع ✓ — الحساب رجع شغّال.');
    }

    public function endSessions(Request $request, User $user): RedirectResponse
    {
        $count = $this->moderation->endAllSessions($request->user(), $user);

        return $this->back($user, $count > 0
            ? 'قفلنا '.$count.' جلسة ✓ — لازم يدخل تاني من كلّ أجهزته.'
            : 'مافيش جلسات مفتوحة — بس أبطلنا «فكّرني» للأمان.');
    }

    public function verifyEmail(Request $request, User $user): RedirectResponse
    {
        $this->moderation->verifyEmail($request->user(), $user);

        return $this->back($user, 'بريده اتأكّد يدويًّا ✓');
    }

    /** الرابط يُعرَض مرّة واحدة في الصفحة بزرّ نسخ — ولا يُخزَّن في أيّ مكان تاني */
    public function passwordLink(Request $request, User $user): RedirectResponse
    {
        $link = $this->moderation->passwordLink($request->user(), $user);

        return $this->back($user, 'الرابط جاهز — انسخه وابعته له على قناة موثوقة.')
            ->with('moderation_password_link', $link);
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        try {
            $this->impersonator->start($request, $request->user(), $user);
        } catch (RuntimeException $exception) {
            return $this->back($user, $exception->getMessage());
        }

        return redirect()->route('dashboard');
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $actor = $this->impersonator->stop($request);

        if (! $actor) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('admin.users.index')->with('status', 'رجعت لحسابك ✓');
    }

    // ------------------------------------------------------------------ داخليّ

    /** ⛔ حارس: لا احتواء لمالك المنصّة ولا للمرء نفسه */
    private function guard(Request $request, User $user): ?RedirectResponse
    {
        if (! $this->moderation->isProtected($request->user(), $user)) {
            return null;
        }

        return $this->back($user, 'الحساب ده محميّ — مايتحظرش ولا يتعلّق من هنا.');
    }

    private function back(User $user, string $status): RedirectResponse
    {
        return redirect()
            ->route('admin.users.show', ['user' => $user, 'tab' => 'advanced'])
            ->with('status', $status);
    }
}
