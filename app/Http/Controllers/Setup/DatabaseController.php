<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\StepsThroughSetup;
use App\Services\Setup\DatabaseTester;
use App\Services\Setup\Installer;
use App\Services\Setup\SetupSettings;
use App\Services\Setup\SetupState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * الخطوة 2 — قاعدة البيانات (2.2): المضيف · المنفذ · الاسم · المستخدم · السرّ،
 * و**[اختبار الاتّصال] قبل الحفظ** — فلا يُكتَب ‎.env‎ إلّا ببيانات مجرَّبة.
 * ثمّ الخطوة 3: المايجريشنز والبيانات الأساسيّة من داخل الويب بلا تيرمينال.
 */
class DatabaseController extends Controller
{
    use StepsThroughSetup;

    public function __construct(
        private readonly DatabaseTester $tester,
        private readonly Installer $installer,
    ) {}

    public function show(SetupState $state): View|RedirectResponse
    {
        if ($redirect = $this->guardStep($state, 'database')) {
            return $redirect;
        }

        return view('setup.database', [
            'stepper' => $this->stepper($state, 'database'),
            'draft' => $this->draft($state),
            'verified' => $state->connectionVerified($this->draft($state)),
        ]);
    }

    /** الاختبار أوّلًا: ردّ فوريّ يقول نجح أو فشل ولماذا (2.17-ب) */
    public function test(Request $request, SetupState $state): RedirectResponse
    {
        $data = $this->validated($request);

        $state->remember($data);

        $result = $this->tester->test($data);

        if (! $result['ok']) {
            return back()->withErrors(['connection' => $result['message']]);
        }

        $state->markConnectionVerified($data);

        return back()->with('setup_success', $result['message']);
    }

    public function store(Request $request, SetupState $state): RedirectResponse
    {
        $data = $this->validated($request);

        $state->remember($data);

        // شرط حاسم: ‎.env‎ لا يُكتَب قبل نجاح اختبار الاتّصال بنفس البيانات بالحرف
        if (! $state->connectionVerified($data)) {
            return back()->withErrors([
                'connection' => 'اضغط [اختبار الاتّصال] الأوّل. مش هنكتب الإعدادات قبل ما نتأكّد إنّها شغّالة، عشان مانكسرش الموقع.',
            ]);
        }

        if (! $this->installer->writeDatabaseEnv($data)) {
            return back()->withErrors([
                'connection' => 'مقدرناش نكتب ملفّ ‎.env‎ في مجلّد المشروع. اضبط صلاحيّة الكتابة على مجلّد المشروع (775) وجرّب تاني.',
            ]);
        }

        $state->complete('database');

        return redirect()->route('setup.migrate');
    }

    public function migrate(SetupState $state): View|RedirectResponse
    {
        if ($redirect = $this->guardStep($state, 'migrate')) {
            return $redirect;
        }

        return view('setup.migrate', [
            'stepper' => $this->stepper($state, 'migrate'),
            'done' => $state->completed('migrate'),
            'log' => (array) session('setup_log', []),
        ]);
    }

    /** التشغيل الفعليّ: مايجريشنز ثمّ بيانات أساسيّة — والتقدّم معروض بعد كلّ مرحلة */
    public function runMigrations(SetupState $state): RedirectResponse
    {
        if ($redirect = $this->guardStep($state, 'migrate')) {
            return $redirect;
        }

        $this->installer->useConnection($this->draft($state));

        $log = [];

        try {
            $migration = $this->installer->migrate();
            $log[] = ['label' => 'إنشاء جداول المنصّة', 'ok' => $migration['ok'], 'output' => $migration['output']];

            if (! $migration['ok']) {
                throw new \RuntimeException('فشل تنفيذ المايجريشنز.');
            }

            $seed = $this->installer->seed();
            $log[] = ['label' => 'تحميل البيانات الأساسيّة (الأدوار والصلاحيّات والإعدادات)', 'ok' => $seed['ok'], 'output' => $seed['output']];

            if (! $seed['ok']) {
                throw new \RuntimeException('فشل تحميل البيانات الأساسيّة.');
            }
        } catch (\Throwable $exception) {
            return back()
                ->with('setup_log', $log)
                ->withErrors([
                    'migrate' => 'التجهيز وقف في النصّ. الرسالة من الخادم: '
                        .Str::limit($exception->getMessage(), 200)
                        .' — راجع بيانات قاعدة البيانات وتأكّد إنّ المستخدم له صلاحيّة إنشاء الجداول، وبعدين اضغط «جرّب تاني».',
                ]);
        }

        $state->complete('migrate');

        return redirect()->route('setup.migrate')->with('setup_log', $log);
    }

    /** @return array<string, string> */
    private function draft(SetupState $state): array
    {
        return [
            'db_host' => (string) $state->draft('db_host', SetupSettings::text('setup.database.default_host', '127.0.0.1')),
            'db_port' => (string) $state->draft('db_port', (string) SetupSettings::number('setup.database.default_port', 3306)),
            'db_database' => (string) $state->draft('db_database', ''),
            'db_username' => (string) $state->draft('db_username', ''),
            'db_password' => (string) $state->draft('db_password', ''),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'db_host' => ['required', 'string', 'max:190'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'db_username' => ['required', 'string', 'max:64'],
            'db_password' => ['nullable', 'string', 'max:190'],
        ], [
            'db_database.regex' => 'اسم قاعدة البيانات يقبل حروفًا إنجليزيّة وأرقامًا و«_» و«-» فقط — انسخه من لوحة الاستضافة كما هو.',
        ], [
            'db_host' => 'مضيف قاعدة البيانات',
            'db_port' => 'منفذ قاعدة البيانات',
            'db_database' => 'اسم قاعدة البيانات',
            'db_username' => 'مستخدم قاعدة البيانات',
            'db_password' => 'كلمة سرّ قاعدة البيانات',
        ]);
    }
}
