<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\StepsThroughSetup;
use App\Services\Setup\Installer;
use App\Services\Setup\SetupState;
use App\Services\Setup\SetupToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * الخطوة 6 — الإنهاء (2.2): مفتاح تطبيق جديد · ملفّ القفل · شاشة نجاح بزرّ الدخول.
 * ملاحظة مقصودة: شاشة النجاح تُعرَض **من نفس الطلب** لا بتحويلة، لأنّ كتابة
 * القفل تُغلق كلّ مسارات ‎/setup‎ فورًا (404) — وده المطلوب أمنيًّا.
 */
class FinishController extends Controller
{
    use StepsThroughSetup;

    public function __construct(
        private readonly Installer $installer,
        private readonly SetupToken $token,
    ) {}

    public function show(SetupState $state): View|RedirectResponse
    {
        if ($redirect = $this->guardStep($state, 'finish')) {
            return $redirect;
        }

        return view('setup.finish', [
            'stepper' => $this->stepper($state, 'finish'),
            'summary' => [
                'اسم المنصّة' => (string) $state->draft('app_name', ''),
                'رابط المنصّة' => (string) $state->draft('app_url', ''),
                'المنطقة الزمنيّة' => (string) $state->draft('timezone', ''),
                'قاعدة البيانات' => (string) $state->draft('db_database', ''),
                'مالك المنصّة' => (string) $state->draft('owner_name', ''),
                'بريد المالك' => (string) $state->draft('owner_email', ''),
            ],
        ]);
    }

    public function install(SetupState $state): View|RedirectResponse
    {
        if ($redirect = $this->guardStep($state, 'finish')) {
            return $redirect;
        }

        $this->installer->rotateAppKey();
        $this->installer->linkStorage();

        $locked = $this->installer->lock([
            'app_name' => $state->draft('app_name'),
            'owner_email' => $state->draft('owner_email'),
        ]);

        if (! $locked) {
            return back()->withErrors([
                'finish' => 'مقدرناش نكتب ملفّ القفل ‎storage/installed.lock‎، ومن غيره تفضل صفحة التنصيب مفتوحة للكلّ. اضبط صلاحيّة مجلّد ‎storage‎ على 775 واضغط «أنهِ التنصيب» تاني.',
            ]);
        }

        $data = [
            'appName' => (string) $state->draft('app_name', config('app.name')),
            'ownerEmail' => (string) $state->draft('owner_email', ''),
            'ownerCode' => (string) $state->draft('owner_code', ''),
        ];

        // التوكن والمسوّدة (وفيها كلمة سرّ قاعدة البيانات) ما بقاش ليهم لزوم
        $this->token->forget();
        $state->flush();

        return view('setup.done', $data);
    }
}
