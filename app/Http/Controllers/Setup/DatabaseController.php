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
 * الخطوة 2 — قاعدة البيانات (2.2): الاسم · المستخدم · السرّ فقط (ثمانية حقول
 * التنصيب بالضبط) — أمّا المضيف والمنفذ فثابتان من إعدادات المنصّة ولا يُدخِلهما
 * المستخدم، و**[اختبار الاتّصال] قبل الحفظ** — فلا يُكتَب ‎.env‎ إلّا ببيانات مجرَّبة.
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
        $data = $this->withFixedConnection($this->validated($request));

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
        $data = $this->withFixedConnection($this->validated($request));

        $state->remember($data);

        // شرط حاسم: ‎.env‎ لا يُكتَب قبل نجاح اختبار الاتّصال بنفس البيانات بالحرف
        if (! $state->connectionVerified($data)) {
            return back()->withErrors([
                'connection' => (string) SetupSettings::text('setup.database.store_denied', 'اضغط [اختبار الاتّصال] الأوّل. مش هنكتب الإعدادات قبل ما نتأكّد إنّها شغّالة، عشان مانكسرش الموقع.'),
            ]);
        }

        if (! $this->installer->writeDatabaseEnv($data)) {
            return back()->withErrors([
                'connection' => (string) SetupSettings::text('setup.database.store_msg', 'مقدرناش نكتب ملفّ ‎.env‎ في مجلّد المشروع. اضبط صلاحيّة الكتابة على مجلّد المشروع (775) وجرّب تاني.'),
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
            $log[] = ['label' => (string) SetupSettings::text('setup.database.run_migrations_msg', 'إنشاء جداول المنصّة'), 'ok' => $migration['ok'], 'output' => $migration['output']];

            if (! $migration['ok']) {
                throw new \RuntimeException((string) SetupSettings::text('setup.database.run_migrations_msg_2', 'فشل تنفيذ المايجريشنز.'));
            }

            $seed = $this->installer->seed();
            $log[] = ['label' => (string) SetupSettings::text('setup.database.run_migrations_msg_3', 'تحميل البيانات الأساسيّة (الأدوار والصلاحيّات والإعدادات)'), 'ok' => $seed['ok'], 'output' => $seed['output']];

            if (! $seed['ok']) {
                throw new \RuntimeException((string) SetupSettings::text('setup.database.run_migrations_msg_4', 'فشل تحميل البيانات الأساسيّة.'));
            }
        } catch (\Throwable $exception) {
            return back()
                ->with('setup_log', $log)
                ->withErrors([
                    'migrate' => strtr((string) SetupSettings::text('setup.database.run_migrations_msg_5', 'التجهيز وقف في النصّ. الرسالة من الخادم: :a1 — راجع بيانات قاعدة البيانات وتأكّد إنّ المستخدم له صلاحيّة إنشاء الجداول، وبعدين اضغط «جرّب تاني».'), [':a1' => (string) (Str::limit($exception->getMessage(), 200))]),
                ]);
        }

        $state->complete('migrate');

        return redirect()->route('setup.migrate')->with('setup_log', $log);
    }

    /**
     * المضيف والمنفذ لم يعودا مُدخَلين من المستخدم (الدستور 2.2: ثمانية حقول
     * بالضبط) — بل ثابتان من إعدادات المنصّة، فيُشتقّان هنا لا يُقرَآن من الجلسة.
     */
    private function fixedConnection(): array
    {
        return [
            'db_host' => (string) SetupSettings::text('setup.database.default_host', '127.0.0.1'),
            'db_port' => (string) SetupSettings::number('setup.database.default_port', 3306),
        ];
    }

    /** حقن المضيف/المنفذ الثابتين بعد التحقّق مباشرة، قبل الاختبار أو الحفظ */
    private function withFixedConnection(array $data): array
    {
        return $this->fixedConnection() + $data;
    }

    /** @return array<string, string> */
    private function draft(SetupState $state): array
    {
        return $this->fixedConnection() + [
            'db_database' => (string) $state->draft('db_database', ''),
            'db_username' => (string) $state->draft('db_username', ''),
            'db_password' => (string) $state->draft('db_password', ''),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'db_username' => ['required', 'string', 'max:64'],
            'db_password' => ['nullable', 'string', 'max:190'],
        ], [
            'db_database.regex' => (string) SetupSettings::text('setup.database.validated_msg', 'اسم قاعدة البيانات يقبل حروفًا إنجليزيّة وأرقامًا و«_» و«-» فقط — انسخه من لوحة الاستضافة كما هو.'),
        ], [
            'db_database' => (string) SetupSettings::text('setup.database.validated_msg_4', 'اسم قاعدة البيانات'),
            'db_username' => (string) SetupSettings::text('setup.database.validated_msg_5', 'مستخدم قاعدة البيانات'),
            'db_password' => (string) SetupSettings::text('setup.database.validated_msg_6', 'كلمة سرّ قاعدة البيانات'),
        ]);
    }
}
