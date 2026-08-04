<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\StepsThroughSetup;
use App\Services\Setup\Installer;
use App\Services\Setup\SetupSettings;
use App\Services\Setup\SetupState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * الخطوة 5 — حساب مالك المنصّة (2.2).
 * يُنشَأ **مفعَّلًا** ويأخذ دور ‎platform_owner‎ (12.2.3): أعلى الجميع ويملك
 * المجموعة المحميّة والماليّات — فلا معنى لأن ينتظر اعتمادًا من أحد.
 */
class OwnerController extends Controller
{
    use StepsThroughSetup;

    public function __construct(private readonly Installer $installer) {}

    public function show(SetupState $state): View|RedirectResponse
    {
        if ($redirect = $this->guardStep($state, 'owner')) {
            return $redirect;
        }

        return view('setup.owner', [
            'stepper' => $this->stepper($state, 'owner'),
            'draft' => [
                'name' => (string) $state->draft('owner_name', ''),
                'email' => (string) $state->draft('owner_email', ''),
                'phone' => (string) $state->draft('owner_phone', ''),
            ],
        ]);
    }

    public function store(Request $request, SetupState $state): RedirectResponse
    {
        if ($redirect = $this->guardStep($state, 'owner')) {
            return $redirect;
        }

        $minimum = (int) SetupSettings::number('setup.owner.min_password', 8);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?\d[\d\s\-]{6,}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min($minimum)],
        ], [
            'phone.regex' => (string) setting('setup.owner.store_denied', 'رقم الموبايل مش مكتوب صحّ. اكتبه أرقامًا مع مفتاح الدولة، مثال: ‎+201000000000‎.'),
            'email.unique' => (string) setting('setup.owner.store_msg', 'البريد ده متسجّل قبل كده. جرّب بريدًا تانيًا، أو ادخل بحسابك لو أنت أنشأته.'),
            'password.confirmed' => (string) setting('setup.owner.store_denied_2', 'تأكيد كلمة السرّ مش مطابق. اكتب نفس الكلمة في الخانتين.'),
        ], [
            'name' => (string) setting('setup.owner.store_msg_2', 'الاسم'),
            'email' => (string) setting('setup.owner.store_msg_3', 'البريد'),
            'phone' => (string) setting('setup.owner.store_msg_4', 'رقم الموبايل'),
            'password' => (string) setting('setup.owner.store_msg_5', 'كلمة السرّ'),
        ]);

        $state->remember([
            'owner_name' => $data['name'],
            'owner_email' => $data['email'],
            'owner_phone' => $data['phone'],
        ]);

        try {
            $owner = $this->installer->createOwner($data);
        } catch (\Throwable $exception) {
            return back()->withErrors(['email' => $exception->getMessage()]);
        }

        $state->remember(['owner_code' => $owner->code]);
        $state->complete('owner');

        return redirect()->route('setup.finish');
    }
}
