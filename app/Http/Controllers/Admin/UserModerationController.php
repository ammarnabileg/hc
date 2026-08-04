<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\Impersonator;
use App\Services\Security\UserDataExport;
use App\Services\Security\UserModeration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        private readonly UserDataExport $export,
    ) {}

    public function ban(Request $request, User $user): RedirectResponse
    {
        if ($guard = $this->guard($request, $user)) {
            return $guard;
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => (string) setting('admin_users.moderation.ban_must', 'اكتب سبب الحظر — المستخدم لازم يعرف حصل إيه.'),
        ]);

        $this->moderation->ban($request->user(), $user, $data['reason']);

        return $this->back($user, (string) setting('admin_users.moderation.ban_ok', 'الحساب اتحظر ✓ — وكلّ جلساته اتقفلت.'));
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
            'reason.required' => (string) setting('admin_users.moderation.suspend_must', 'اكتب سبب التعليق — المستخدم لازم يعرف حصل إيه.'),
            'days.required' => (string) setting('admin_users.moderation.suspend_msg', 'حدّد مدّة التعليق بالأيّام.'),
            'days.max' => strtr((string) setting('admin_users.moderation.suspend_msg_2', 'أقصى مدّة تعليق :a1 يوم.'), [':a1' => (string) ((int) setting('admin.moderation.suspend_max_days', 90))]),
        ]);

        $this->moderation->suspend($request->user(), $user, $data['reason'], (int) $data['days']);

        return $this->back($user, strtr((string) setting('admin_users.moderation.suspend_ok', 'الحساب اتعلّق :a1 يوم ✓ — وبيرجع لوحده بعدها.'), [':a1' => (string) ($data['days'])]));
    }

    public function release(Request $request, User $user): RedirectResponse
    {
        $this->moderation->release($request->user(), $user);

        return $this->back($user, (string) setting('admin_users.moderation.release_ok', 'الاحتواء اترفع ✓ — الحساب رجع شغّال.'));
    }

    public function endSessions(Request $request, User $user): RedirectResponse
    {
        $count = $this->moderation->endAllSessions($request->user(), $user);

        return $this->back($user, $count > 0
            ? strtr((string) setting('admin_users.moderation.end_sessions_ok', 'قفلنا :a1 جلسة ✓ — لازم يدخل تاني من كلّ أجهزته.'), [':a1' => (string) ($count)])
            : (string) setting('admin_users.moderation.end_sessions_empty', 'مافيش جلسات مفتوحة — بس أبطلنا «فكّرني» للأمان.'), 'security');
    }

    public function verifyEmail(Request $request, User $user): RedirectResponse
    {
        $this->moderation->verifyEmail($request->user(), $user);

        return $this->back($user, (string) setting('admin_users.moderation.verify_email_ok', 'بريده اتأكّد يدويًّا ✓'), 'security');
    }

    /** الرابط يُعرَض مرّة واحدة في الصفحة بزرّ نسخ — ولا يُخزَّن في أيّ مكان تاني */
    public function passwordLink(Request $request, User $user): RedirectResponse
    {
        $link = $this->moderation->passwordLink($request->user(), $user);

        return $this->back($user, (string) setting('admin_users.moderation.password_link_msg', 'الرابط جاهز — انسخه وابعته له على قناة موثوقة.'), 'security')
            ->with('moderation_password_link', $link);
    }

    /**
     * ⭐ **تعديل بيانات المستخدم يدويًّا** + **Checkbox تأكيد الإيميل** +
     * **الملاحظات الإداريّة الداخليّة** (12.1-المعلومات الأساسيّة).
     *
     * كان تاب البيانات للقراءة فقط — فمَن أخطأ في اسمه أو بريده عند التسجيل
     * ماكانش قدّامه غير حساب جديد. والحارس `admin_user_detail.edit` على المسار.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($user->id)],
            'gender' => ['nullable', 'string', 'max:16'],
            'birthdate' => ['nullable', 'date'],
            'governorate_id' => ['nullable', 'exists:governorates,id'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => (string) setting('admin_users.moderation.update_msg', 'الاسم مايصحّش يفضل فاضي.'),
            'email.email' => (string) setting('admin_users.moderation.update_denied', 'الصيغة دي مش بريد صحيح — راجعها وجرّب تاني.'),
            'email.unique' => (string) setting('admin_users.moderation.update_msg_2', 'البريد ده على حساب تاني — اختر غيره.'),
            'phone.unique' => (string) setting('admin_users.moderation.update_msg_3', 'الموبايل ده على حساب تاني — اختر غيره.'),
        ]);

        $old = $user->only(array_keys($data));

        $user->forceFill($data)->save();

        // تأكيد الإيميل يدويًّا بـCheckbox — والإلغاء يرجّعه لغير مؤكَّد كذلك
        $verified = $request->boolean('email_verified');

        if ($verified && ! $user->email_verified_at) {
            $this->moderation->verifyEmail($request->user(), $user);
        } elseif (! $verified && $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => null])->save();
            $this->moderation->log($request->user(), $user, 'user.email.unverify');
        }

        $this->moderation->log($request->user(), $user, 'user.updated', $old, $data);

        return $this->back($user, (string) setting('admin_users.moderation.update_ok', 'اتحفظت بيانات الحساب ✓'), 'profile');
    }

    /** تثبيت/تصحيح الدولة يدويًّا — تعلو الكشف التلقائيّ ولا تُدهَس (12.1-متقدّم-5) */
    public function pinCountry(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'country_id' => ['nullable', 'exists:countries,id'],
        ]);

        $countryId = $data['country_id'] === null ? null : (int) $data['country_id'];

        $this->moderation->pinCountry($request->user(), $user, $countryId);

        return $this->back($user, $countryId === null
            ? (string) setting('admin_users.moderation.pin_country_msg', 'شِلنا التثبيت — الدولة رجعت للكشف التلقائيّ.')
            : (string) setting('admin_users.moderation.pin_country_ok', 'الدولة اتثبّتت ✓ — الكشف التلقائيّ مش هيدهسها تاني.'));
    }

    /**
     * **تصدير بيانات المستخدم كملفّ** (12.1-متقدّم-6) — بصلاحيّته `admin_user_detail.export`.
     *
     * الملفّ نصّيّ مقروء (JSON) لا نسخة قاعدة بيانات: بياناته وأرصدته ومعاملاته
     * وشهاداته ودعواته — وما يخرج منه محدود بما تعرضه الشاشة نفسها، فلا يصير
     * زرُّ التصدير بابًا خلفيًّا يتجاوز حراسة الصفحة.
     */
    public function export(Request $request, User $user): StreamedResponse
    {
        $payload = $this->export->for($user);

        $filename = str_replace(
            ['{code}', '{date}'],
            [(string) $user->code, now()->format('Ymd-His')],
            (string) setting('admin.users.export_filename', 'user-{code}-{date}.json'),
        );

        $this->moderation->log($request->user(), $user, 'user.data.export', [], ['file' => $filename]);

        return response()->streamDownload(
            function () use ($payload) {
                echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            },
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
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

        return redirect()->route('admin.users.index')->with('status', (string) setting('admin_users.moderation.stop_impersonating_ok', 'رجعت لحسابك ✓'));
    }

    // ------------------------------------------------------------------ داخليّ

    /** ⛔ حارس: لا احتواء لمالك المنصّة ولا للمرء نفسه */
    private function guard(Request $request, User $user): ?RedirectResponse
    {
        if (! $this->moderation->isProtected($request->user(), $user)) {
            return null;
        }

        return $this->back($user, (string) setting('admin_users.moderation.guard_msg', 'الحساب ده محميّ — مايتحظرش ولا يتعلّق من هنا.'));
    }

    private function back(User $user, string $status, string $tab = 'advanced'): RedirectResponse
    {
        return redirect()
            ->route('admin.users.show', ['user' => $user, 'tab' => $tab])
            ->with('status', $status);
    }
}
