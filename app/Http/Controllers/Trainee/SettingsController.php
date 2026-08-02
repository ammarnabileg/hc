<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\ConsentRequest;
use App\Models\Country;
use App\Models\EmergencyContact;
use App\Models\Governorate;
use App\Models\UserDevice;
use App\Models\UserPrivacySetting;
use App\Services\Account\AccountDataExport;
use App\Services\Account\ConsentDirectory;
use App\Services\Account\PrivacyFields;
use App\Services\Account\SettingsAutosave;
use App\Services\Security\AccountDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * حسابي ← الإعدادات + الخصوصيّة والأمان (الدستور 24.5 · 13.4-م · 12.14-د).
 *
 * صفحة واحدة بتابات جانبيّة (2.15-د) وبحث داخلها، و**حفظ تلقائيّ** لكلّ حقل
 * مع «اتحفظ ✓» بجواره (2.17-ب) — وعند الخطأ يُحتفَظ بالمُدخَل كما هو.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsAutosave $autosave,
        private readonly ConsentDirectory $consents,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('account.settings.index', [
            'user' => $user,
            'countries' => Country::where('is_active', true)->orderBy('sort_order')->get(['id', 'name_ar']),
            'governorates' => Governorate::where('is_active', true)
                ->when($user->country_id, fn ($q) => $q->where('country_id', $user->country_id))
                ->orderBy('sort_order')
                ->get(['id', 'name_ar']),
            'emergencyContacts' => EmergencyContact::where('user_id', $user->id)->get(),
            'emergencyMax' => max(1, (int) setting('account.emergency_contacts.max', 2)),
            // تنبيه تغيير البريد/الموبايل: «هيتوقف عرض بياناتك لـ N أشخاص» (13.4-م)
            'activeConsents' => $this->autosave->activeConsentCount($user),
            'avatarMaxKb' => $this->autosave->avatarMaxKb(),
            'tab' => $request->string('tab')->toString() ?: 'account',
        ]);
    }

    public function privacy(Request $request): View
    {
        $user = $request->user();

        $stored = UserPrivacySetting::where('user_id', $user->id)->pluck('visibility', 'field')->all();

        $fields = [];

        // ⭐ المحافظة حقل عامّ دائمًا ولا يجوز إخفاؤها — فهي غير مدرَجة أصلًا (12.14-د)
        foreach (PrivacyFields::all() as $key => $label) {
            $fields[] = [
                'key' => $key,
                'label' => $label,
                'sensitive' => PrivacyFields::isSensitive($key),
                'value' => $stored[$key] ?? PrivacyFields::defaultVisibility($key),
            ];
        }

        $consents = $this->consents->activeFor($user);

        return view('account.settings.privacy', [
            'user' => $user,
            'fields' => $fields,
            'visibilities' => PrivacyFields::visibilityLabels(),
            'allowed' => PrivacyFields::allowedVisibilities(),
            'consents' => $consents,
            'consentBars' => $consents->mapWithKeys(
                fn ($c) => [$c->id => $this->consents->remainingPercent($c)]
            ),
            'devices' => UserDevice::where('user_id', $user->id)->orderByDesc('last_active_at')->get(),
            'currentSessionId' => $request->session()->getId(),
        ]);
    }

    /** حفظ تلقائيّ لحقل واحد — ردّ فوريّ بـ«اتحفظ ✓» (2.17-ب) */
    public function updateField(Request $request): JsonResponse|RedirectResponse
    {
        $field = $request->string('field')->toString();

        try {
            $result = $this->autosave->save($request->user(), $field, $request->input('value'));
        } catch (ValidationException $e) {
            // ماذا حدث + ماذا تفعل — والمُدخَل يفضل كما هو في الواجهة (2.17-ب)
            if ($request->wantsJson()) {
                return response()->json([
                    'saved' => false,
                    'message' => collect($e->errors())->flatten()->first() ?? 'تعذّر الحفظ.',
                ], 422);
            }

            return back()->withInput()->withErrors($e->errors());
        }

        $message = 'اتحفظ ✓';

        if ($result['revoked'] > 0) {
            // شفافيّة مسبقة: البيانات الجديدة لا ترث موافقة قديمة (13.4-م)
            $message = 'اتحفظ ✓ — ووقفنا عرض بياناتك لـ '.$result['revoked'].' من اللي كانوا شايفينها.';
        }

        if ($request->wantsJson()) {
            return response()->json(['saved' => true, 'message' => $message, 'value' => $result['value']]);
        }

        return back()->with('status', $message);
    }

    /** الأفاتار بقصّ ومعاينة (24.5) */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['nullable', 'image', 'max:'.$this->autosave->avatarMaxKb()],
            'avatar_data' => ['nullable', 'string'],
        ], [
            'avatar.image' => 'الملفّ ده مش صورة — اختار صورة وجرّب تاني.',
            'avatar.max' => 'الصورة كبيرة شوية — اختار صورة أصغر.',
        ], ['avatar' => 'الصورة']);

        $saved = $this->autosave->storeAvatar(
            $request->user(),
            $request->string('avatar_data')->toString() ?: null,
            $request->file('avatar'),
        );

        return back()->with('status', $saved ? 'اتحفظ ✓' : 'اختار صورة الأوّل.');
    }

    /** جهة الطوارئ (اختياريّ) — ظاهرة للأبلاينز دائمًا (13.4-م) */
    public function storeEmergency(Request $request): RedirectResponse
    {
        $max = max(1, (int) setting('account.emergency_contacts.max', 2));

        if (EmergencyContact::where('user_id', $request->user()->id)->count() >= $max) {
            return back()->with('status', 'وصلت للحدّ الأقصى ('.$max.'). امسح واحدة قبل ما تضيف جديدة.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'relation' => ['nullable', 'string', 'max:64'],
        ], [
            'required' => 'الحقل ده مطلوب — اكتبه وجرّب تاني.',
            'max' => 'القيمة دي أطول من المسموح.',
        ], [
            'name' => 'الاسم',
            'phone' => 'رقم الموبايل',
            'relation' => 'صلة القرابة',
        ]);

        EmergencyContact::create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('status', 'اتحفظ ✓');
    }

    public function destroyEmergency(Request $request, EmergencyContact $contact): RedirectResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 403);

        $contact->delete();

        return back()->with('status', 'اتمسحت ✓');
    }

    /** خصوصيّة حقل واحد — والمحافظة ممنوع إخفاؤها فتُرفَض هنا صراحةً (12.14-د) */
    public function updatePrivacyField(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'field' => ['required', 'string', 'max:64'],
            'visibility' => ['required', 'in:'.implode(',', PrivacyFields::VISIBILITIES)],
        ], [
            'required' => 'الحقل ده مطلوب.',
            'in' => 'الاختيار ده مش من الخيارات المتاحة.',
        ]);

        // ⭐ حارس صريح: المحافظة عامّة دائمًا ولا تُدرَج ولا تُحفَظ (12.14-د)
        if (! PrivacyFields::isControllable($data['field'])) {
            return $this->refuse($request, 'الحقل ده بيفضل عامّ على طول — مش بيتغيّر.');
        }

        // لا يُسمح بأوسع من حدّ الأدمن (24.5)
        if (PrivacyFields::rank($data['visibility']) < PrivacyFields::rank(PrivacyFields::adminFloor())) {
            return $this->refuse($request, 'الإعداد ده أوسع من المسموح — اختار خيار تاني.');
        }

        UserPrivacySetting::updateOrCreate(
            ['user_id' => $request->user()->id, 'field' => $data['field']],
            ['visibility' => $data['visibility']],
        );

        if ($request->wantsJson()) {
            return response()->json(['saved' => true, 'message' => 'اتحفظ ✓']);
        }

        return back()->with('status', 'اتحفظ ✓');
    }

    /** رفض مفهوم: نفس الرسالة في JSON وفي الصفحة العاديّة (2.17-ب) */
    private function refuse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['saved' => false, 'message' => $message], 422);
        }

        return back()->withErrors(['visibility' => $message]);
    }

    /** سحب الموافقة: يقطع الرؤية فورًا — و**بلا إشعار للطرف الآخر** (13.4-م) */
    public function revokeConsent(Request $request, ConsentRequest $consent): RedirectResponse
    {
        abort_unless($consent->owner_id === $request->user()->id, 403);

        $this->consents->revoke($consent);

        return back()->with('status', 'اتسحبت ✓ — بياناتك مبقتش ظاهرة له.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'required' => 'الحقل ده مطلوب.',
            'password.confirmed' => 'الكلمتان مش متطابقتين — راجعهم وجرّب تاني.',
            'password.min' => 'كلمة السرّ لازم تبقى 8 خانات على الأقلّ.',
        ], [
            'current_password' => 'كلمة السرّ الحاليّة',
            'password' => 'كلمة السرّ الجديدة',
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            // ماذا حدث + ماذا تفعل (2.17-ب)
            return back()->withErrors([
                'current_password' => 'كلمة السرّ الحاليّة مش مظبوطة. جرّب تاني أو اعمل استعادة.',
            ]);
        }

        $request->user()->forceFill(['password' => $data['password']])->save();

        return back()->with('status', 'اتغيّرت ✓ — كلمة السرّ بقت جديدة.');
    }

    /** إنهاء جلسة من الجلسات النشطة (24.5) */
    public function endSession(Request $request, UserDevice $device): RedirectResponse
    {
        abort_unless($device->user_id === $request->user()->id, 403);

        $isCurrent = $device->session_id === $request->session()->getId();
        $device->delete();

        if ($isCurrent) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'قفلنا الجلسة دي. سجّل دخولك تاني.');
        }

        return back()->with('status', 'اتقفلت ✓ — الجهاز ده مبقاش داخل على حسابك.');
    }

    /** منطقة الخطر (2.3): خطوة 1 — رمز تأكيد رباعيّ يوصل بريد صاحب الحساب نفسه */
    public function sendDeletionCode(Request $request, AccountDeletion $deletion): RedirectResponse
    {
        $result = $deletion->requestCode($request->user());

        return back()->with('status', $result['sent']
            ? 'بعتنا رمز التأكيد على بريدك.'
            : 'استنّى '.$result['wait'].' ثانية قبل ما تطلب رمزًا تاني.');
    }

    /** منطقة الخطر (2.3): خطوة 2 — Soft-delete بعد التأكيد، والحساب يفضل قابل للاسترجاع */
    public function destroyAccount(Request $request, AccountDeletion $deletion): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ], ['code.required' => 'اطلب الرمز الأوّل واكتبه هنا.']);

        $user = $request->user();
        $result = $deletion->verify($user, $data['code']);

        if (! $result['ok']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        $deletion->delete($user);

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'حسابك اتقفل. لو غيّرت رأيك خلال '.$deletion->graceDays().' يوم كلّم الدعم.');
    }

    /** [تحميل بياناتي] — JSON (24.5) */
    public function exportData(Request $request, AccountDataExport $export): Response
    {
        $user = $request->user();

        return response(
            json_encode($export->for($user), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            200,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="'.$export->filename($user).'"',
            ],
        );
    }
}
