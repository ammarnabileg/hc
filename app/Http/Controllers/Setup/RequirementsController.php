<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\StepsThroughSetup;
use App\Services\Setup\RequirementsChecker;
use App\Services\Setup\SetupSettings;
use App\Services\Setup\SetupState;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * الخطوة 1 — فحص المتطلّبات (2.2): PHP · الامتدادات · صلاحيّات الكتابة.
 * ولا متابعة إن نقص إلزاميّ، لأنّ التنصيب سيفشل في منتصفه ويترك الخادم نصّ نصّ.
 */
class RequirementsController extends Controller
{
    use StepsThroughSetup;

    public function __construct(private readonly RequirementsChecker $checker) {}

    public function show(SetupState $state): View|RedirectResponse
    {
        if ($redirect = $this->guardStep($state, 'requirements')) {
            return $redirect;
        }

        return view('setup.requirements', [
            'stepper' => $this->stepper($state, 'requirements'),
            'groups' => $this->checker->groups(),
            'passed' => $this->checker->passed(),
        ]);
    }

    public function store(SetupState $state): RedirectResponse
    {
        if (! $this->checker->passed()) {
            return back()->withErrors([
                'requirements' => strtr((string) SetupSettings::text('setup.requirements.store_msg', 'لسّه ناقص: :a1. صحّحها من لوحة الاستضافة، وبعدين اضغط «أعد الفحص».'), [':a1' => (string) (implode(' · ', $this->checker->missing()))]),
            ]);
        }

        $state->complete('requirements');

        return redirect()->route('setup.database');
    }
}
