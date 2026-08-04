<?php

namespace App\Http\Controllers\Setup\Concerns;

use App\Services\Setup\SetupSettings;
use App\Services\Setup\SetupState;
use Illuminate\Http\RedirectResponse;

/**
 * المعالج خطوات لا فورم طويل (2.15-ب)، فكلّ متحكّم يحتاج نفس ثلاثة أشياء:
 * أين نحن · هل يجوز فتح هذه الخطوة · وكيف نرسم شريط الخطوات.
 */
trait StepsThroughSetup
{
    /** أسماء المسارات لكلّ خطوة — مصدر واحد فلا تتفرّق التحويلات */
    protected function routeFor(string $step): string
    {
        return match ($step) {
            'requirements' => 'setup.requirements',
            'database' => 'setup.database',
            'migrate' => 'setup.migrate',
            'platform' => 'setup.platform',
            'owner' => 'setup.owner',
            default => 'setup.finish',
        };
    }

    /** لا يُقفَز لخطوة قبل إتمام ما قبلها — والرسالة تقول ليه (2.17-ب) */
    protected function guardStep(SetupState $state, string $step): ?RedirectResponse
    {
        if ($state->reachable($step)) {
            return null;
        }

        $next = $state->nextStep();

        return redirect()->route($this->routeFor($next))->withErrors([
            'setup' => strtr((string) setting('setup.steps.guard_step_must', 'لازم تخلّص «:a1» الأوّل عشان نكمل بأمان — رجّعناك لمكانها.'), [':a1' => (string) ($this->stepLabel($next))]),
        ]);
    }

    protected function stepLabel(string $step): string
    {
        return match ($step) {
            'requirements' => SetupSettings::text('setup.texts.step_requirements', 'فحص المتطلّبات'),
            'database' => SetupSettings::text('setup.texts.step_database', 'قاعدة البيانات'),
            'migrate' => SetupSettings::text('setup.texts.step_migrate', 'تجهيز الجداول'),
            'platform' => SetupSettings::text('setup.texts.step_platform', 'بيانات المنصّة'),
            'owner' => SetupSettings::text('setup.texts.step_owner', 'حساب المالك'),
            default => SetupSettings::text('setup.texts.step_finish', 'الإنهاء'),
        };
    }

    /**
     * بيانات شريط الخطوات: لكلّ خطوة **رمز مع اللون** (2.16-ب).
     *
     * @return list<array{key:string,label:string,state:string,number:int}>
     */
    protected function stepper(SetupState $state, string $current): array
    {
        $steps = [...SetupState::STEPS, 'finish'];

        return array_values(array_map(fn (string $step, int $index) => [
            'key' => $step,
            'label' => $this->stepLabel($step),
            'number' => $index + 1,
            'state' => match (true) {
                $step === $current => 'warn',
                $state->completed($step) => 'ok',
                default => 'idle',
            },
        ], $steps, array_keys($steps)));
    }
}
