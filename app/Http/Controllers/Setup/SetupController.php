<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\StepsThroughSetup;
use App\Services\Setup\SetupSettings;
use App\Services\Setup\SetupState;
use App\Services\Setup\SetupToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * باب المعالج وبوّابة التوكن (الدستور 2.2).
 * التنصيب كلّه من المتصفّح: بلا تيرمينال وبلا أيّ تحكّم من داخل السيرفر.
 */
class SetupController extends Controller
{
    use StepsThroughSetup;

    public function __construct(private readonly SetupToken $token) {}

    /** ‎/setup‎ ترجّعك دائمًا لأوّل خطوة ناقصة — فلا تضيع مكانك لو قفلت المتصفّح */
    public function index(SetupState $state): RedirectResponse
    {
        if (! $state->tokenVerified()) {
            return redirect()->route('setup.token');
        }

        return redirect()->route($this->routeFor($state->nextStep()));
    }

    public function token(SetupState $state): View|RedirectResponse
    {
        if ($state->tokenVerified()) {
            return redirect()->route('setup.index');
        }

        $token = $this->token->ensure();

        return view('setup.token', [
            'stepper' => $this->stepper($state, 'requirements'),
            // نعرض التوكن على الشاشة فقط في وضع التطوير؛ وفي الإنتاج نرشد لمكان الملفّ
            'visibleToken' => config('app.debug') ? $token : null,
            'tokenFile' => $this->token->file(),
            'unwritable' => $token === null,
        ]);
    }

    public function verifyToken(Request $request, SetupState $state): RedirectResponse
    {
        $data = $request->validate(
            ['token' => ['required', 'string', 'max:190']],
            [],
            ['token' => (string) setting('setup.flow.verify_token_msg', 'توكن التنصيب')],
        );

        if (! $this->token->matches($data['token'])) {
            return back()->withErrors([
                'token' => SetupSettings::text(
                    'setup.texts.token_mismatch',
                    'التوكن مش مطابق. افتح الملفّ '.$this->token->file().' من مدير الملفّات وانسخ السطر اللي جوّاه كما هو.',
                ),
            ]);
        }

        $state->markTokenVerified();

        return redirect()->route('setup.requirements');
    }
}
