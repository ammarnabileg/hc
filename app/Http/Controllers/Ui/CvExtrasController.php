<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Cv;
use App\Services\Library\AtsPdfWriter;
use App\Services\Library\CvBuilder;
use App\Services\Library\CvExport;
use App\Services\Library\CvImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * ما نقص من القسم 9: **استيراد وتحليل CV** · **تصدير PDF متوافق مع ATS** ·
 * **رابط سيرة عامّ**.
 */
class CvExtrasController extends Controller
{
    public function __construct(
        private readonly CvBuilder $builder,
        private readonly CvImporter $importer,
        private readonly AtsPdfWriter $pdf,
        private readonly CvExport $export,
    ) {}

    // ---------------------------------------------------- الاستيراد والتحليل

    /**
     * رفع CV جاهز ⟵ استخراج بياناته بالكامل ⟵ **معاينة قبل الحفظ** (9).
     * ولا نكتب شيئًا هنا إطلاقًا: الكتابة في `applyImport` بعد سؤال
     * «تبديل ولا إضافة؟» — لأنّ الاستيراد فعل غير قابل للتراجع بطبيعته.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.$this->importer->maxKb()],
        ], [
            'file.required' => (string) setting('cv.extras.import_msg', 'اختار ملفّ الأوّل.'),
            'file.max' => strtr((string) setting('cv.extras.import_msg_2', 'الملفّ كبير شويّة — أقصى حجم :a1 كيلوبايت.'), [':a1' => (string) ($this->importer->maxKb())]),
        ], ['file' => (string) setting('cv.extras.import_msg_3', 'الملفّ')]);

        try {
            $text = $this->importer->extractText($request->file('file'));
            $parsed = $this->importer->parse($text);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        // نحتفظ بالتحليل في الجلسة حتى يقرّر المستخدم — بلا مساس ببياناته
        $request->session()->put('cv.import.preview', $parsed);

        return response()->json([
            'ok' => true,
            'preview' => $parsed,
            'counts' => [
                'experience' => count($parsed['experience'] ?? []),
                'education' => count($parsed['education'] ?? []),
                'languages' => count($parsed['languages'] ?? []),
            ],
            'question' => (string) setting('cv.import.question', 'نبدّل بياناتك بالملفّ ولا نضيف عليها؟'),
        ]);
    }

    /** ⭐ «تبديل ولا إضافة؟» — ولا كتابة قبل الإجابة (9) */
    public function applyImport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:replace,append'],
            'preview' => ['nullable', 'array'],
        ]);

        $parsed = (array) ($data['preview'] ?? $request->session()->get('cv.import.preview', []));

        if ($parsed === []) {
            return response()->json([
                'ok' => false,
                'message' => (string) setting('cv.extras.apply_import_empty', 'مفيش تحليل محفوظ — ارفع الملفّ تاني وراجع المعاينة.'),
            ], 422);
        }

        $cv = $this->builder->forUser($request->user());
        $current = array_replace($this->builder->blank(), (array) $cv->data);

        $merged = $this->importer->apply($current, $parsed, (string) $data['mode']);

        $cv->data = $merged;
        $cv->completion_percent = $this->builder->completion($merged);
        $cv->save();

        $request->session()->forget('cv.import.preview');

        return response()->json([
            'ok' => true,
            'message' => (string) setting('cv.extras.apply_import_ok', 'اتحفظ ✓'),
            'completion' => $cv->completion_percent,
        ]);
    }

    // ---------------------------------------------------- الاستخراج ATS

    /**
     * ⭐ الاستخراج النهائيّ: PDF متوافق مع ATS — يُولَّد على الخادم بلا مكتبات.
     * ولو غاب الخطّ المضمَّن نحوّل للنسخة القابلة للطباعة **بسطر يشرح ماذا
     * حدث وماذا يفعل** بدل صفحة خطأ (2.17-ب).
     */
    public function atsPdf(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $cv = $this->builder->forUser($user);
        $data = array_replace($this->builder->blank(), (array) $cv->data);

        if (! $this->pdf->available()) {
            return redirect()->route('cv.download')->with('status', $this->pdf->unavailableReason());
        }

        // ⭐ **لحظة الخصم** هنا لا عند اختيار القالب (9) — وبلا تأكيد نعرض
        // المعاينة الموسومة بدل أن نخصم من غير علم صاحب التذاكر.
        $decision = $this->export->resolve($user, $cv, $data, $request->boolean('confirm'));

        if (! $decision['clean']) {
            return redirect()
                ->route('cv.download')
                ->with('status', $decision['notice']);
        }

        if ($decision['data'] !== $data) {
            $cv->data = $decision['data'];
            $cv->save();
        }

        $binary = $this->pdf->build(
            (string) $user->name,
            $this->headline($decision['data']),
            $this->sections($user, $decision['data']),
            // ⭐ القالب المدفوع يظهر أثره في المخرَج — لا في المعاينة وحدها (9)
            $decision['template']?->atsOptions() ?? [],
        );

        $name = 'CV-'.$user->code.'.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    // ---------------------------------------------------- الرابط العامّ

    /** فتح/غلق الرابط العامّ — والقفل فوريّ فلا يبقى منشورًا بعد الإغلاق (9) */
    public function togglePublic(Request $request): JsonResponse
    {
        $cv = $this->builder->forUser($request->user());

        $enable = $request->boolean('enabled');

        if ($enable && ! $cv->public_slug) {
            $cv->public_slug = Str::lower(Str::random((int) setting('cv.public.slug_length', 12)));
        }

        $cv->is_public = $enable;
        $cv->save();

        return response()->json([
            'ok' => true,
            'enabled' => (bool) $cv->is_public,
            'url' => $cv->public_slug ? route('cv.public', ['slug' => $cv->public_slug]) : null,
            'message' => $enable ? (string) setting('cv.extras.toggle_public_ok', 'الرابط شغّال ✓') : (string) setting('cv.extras.toggle_public_ok_2', 'الرابط اتقفل ✓'),
        ]);
    }

    /** الصفحة العامّة — بلا تسجيل، وبلا أيّ بيان حسّاس (موبايل/بريد لا يخرجان) */
    public function publicShow(string $slug): View
    {
        $cv = Cv::query()
            ->with('user')
            ->where('public_slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        // ⚠️ صاحب السيرة قد يكون محذوفًا Soft فـ`$cv->user` تُرجِع null.
        // الـ500 هنا **يُثبت وجود الرابط** بينما ترجّع كلّ الواجهات الأخرى 404
        // نظيفًا — فيصير الخطأ نفسه تسريبًا. نُوحّد السلوك: 404.
        $user = $cv->user;

        abort_if($user === null, 404);

        $cv->increment('public_views');
        $data = array_replace($this->builder->blank(), (array) $cv->data);

        // الرابط العامّ لا يحمل الموبايل ولا البريد — قاعدة خصوصيّة (10 · 13.4-م)
        $data['profile'] = collect((array) ($data['profile'] ?? []))
            ->except(['phone', 'email'])
            ->all();

        return view('cv.public', [
            'owner' => $user,
            'cv' => $cv,
            'data' => $data,
            'sections' => $this->sections($user, $data, public: true),
            'headline' => $this->headline($data),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    private function headline(array $data): string
    {
        $profile = (array) ($data['profile'] ?? []);

        return trim(implode(' — ', array_filter([
            (string) ($profile['job_title'] ?? ''),
            (string) ($profile['company'] ?? ''),
        ])));
    }

    /**
     * أقسام السيرة بعناوين قياسيّة يعرفها كلّ محلّل ATS.
     *
     * @return array<int, array{heading:string, lines:array<int,string>}>
     */
    private function sections($user, array $data, bool $public = false): array
    {
        $profile = (array) ($data['profile'] ?? []);
        $pulled = $this->builder->pulled($user, $data);

        $contact = array_filter([
            (string) ($profile['city'] ?? ''),
            (string) ($pulled['profile']['governorate'] ?? ''),
            (string) ($pulled['profile']['country'] ?? ''),
            $public ? '' : (string) ($profile['email'] ?? $pulled['profile']['email'] ?? ''),
            $public ? '' : (string) ($profile['phone'] ?? $pulled['profile']['phone'] ?? ''),
        ], fn ($v) => trim((string) $v) !== '');

        $sections = [];

        if ($contact !== []) {
            $sections[] = ['heading' => (string) setting('cv.section.contact_label', 'بيانات التواصل'), 'lines' => [implode(' · ', $contact)]];
        }

        $lang = CvBuilder::lang($data);
        $summary = CvBuilder::text($profile, 'summary', $lang);

        if ($summary !== '') {
            $sections[] = ['heading' => (string) setting('cv.section.summary_label', 'الملخّص المهنيّ'), 'lines' => [$summary]];
        }

        $experience = [];

        foreach ((array) ($data['experience'] ?? []) as $row) {
            $experience[] = $this->entryLine((array) $row, 'title', 'company');

            if (($note = CvBuilder::text((array) $row, 'description', $lang)) !== '') {
                $experience[] = $note;
            }
        }

        if ($experience !== []) {
            $sections[] = ['heading' => (string) setting('cv.section.experience_label', 'الخبرة العمليّة'), 'lines' => $experience];
        }

        // 💖 الخبرة التطوّعيّة — بندٌ صريح في القسم 9 وكان غائبًا عن المخرَج
        $volunteering = [];

        foreach ((array) ($data['volunteering'] ?? []) as $row) {
            $volunteering[] = $this->entryLine((array) $row, 'role', 'organization');

            if (($note = CvBuilder::text((array) $row, 'description', $lang)) !== '') {
                $volunteering[] = $note;
            }
        }

        if ($volunteering !== []) {
            $sections[] = ['heading' => (string) setting('cv.section.volunteering_label', 'الخبرة التطوّعيّة'), 'lines' => $volunteering];
        }

        $education = [];

        foreach ((array) ($data['education'] ?? []) as $row) {
            // التخصّص لا المدينة — صفوف التعليم لا تحمل `city` (9)
            $education[] = $this->entryLine($row, 'degree', 'institution', 'major');
        }

        if ($education !== []) {
            $sections[] = ['heading' => (string) setting('cv.section.education_label', 'التعليم'), 'lines' => $education];
        }

        // 🎓 الدورات التدريبيّة: اسم · جهة · تاريخ · رقم · رابط (9)
        $courses = [];

        foreach ((array) ($data['courses'] ?? []) as $row) {
            $row = (array) $row;
            $line = trim(implode(' — ', array_filter([
                (string) ($row['name'] ?? ''),
                (string) ($row['provider'] ?? ''),
                (string) ($row['date'] ?? ''),
                filled($row['serial'] ?? null)
                    ? (string) setting('cv.section.certificate_number_prefix', 'رقم').' '.$row['serial']
                    : '',
                (string) ($row['url'] ?? ''),
            ])));

            if ($line !== '') {
                $courses[] = $line;
            }
        }

        if ($courses !== []) {
            $sections[] = ['heading' => (string) setting('cv.section.courses_label', 'الدورات التدريبيّة'), 'lines' => $courses];
        }

        if (trim((string) ($data['skills'] ?? '')) !== '') {
            $sections[] = ['heading' => (string) setting('cv.section.skills_label', 'المهارات'), 'lines' => [(string) $data['skills']]];
        }

        $languages = [];

        foreach ((array) ($data['languages'] ?? []) as $row) {
            $line = trim(implode(' — ', array_filter([
                (string) ($row['language'] ?? ''),
                (string) ($row['level'] ?? ''),
            ])));

            if ($line !== '') {
                $languages[] = $line;
            }
        }

        if ($languages !== []) {
            $sections[] = ['heading' => (string) setting('cv.section.languages_label', 'اللغات'), 'lines' => $languages];
        }

        // ⭐ الربط التلقائيّ: التدريبات المكتملة تُضاف تلقائيًّا (9)
        $trainings = ($pulled['trainings'] ?? collect())
            ->map(fn ($enrollment) => trim((string) ($lang === 'en'
                ? ($enrollment->course?->name_en ?: $enrollment->course?->name_ar)
                : $enrollment->course?->name_ar)))
            ->filter()
            ->values()
            ->all();

        if ($trainings !== []) {
            $sections[] = ['heading' => (string) setting('cv.section.trainings_label', 'تدريبات المنصّة المكتملة'), 'lines' => $trainings];
        }

        // ⭐ رقم الشهادة عمودُه **`code`** — لا `serial` (عمودٌ لا وجود له،
        // فكان الرقم يخرج فارغًا دائمًا في الـPDF وفي الرابط العامّ).
        $certificates = $pulled['certificates']->map(
            fn ($certificate) => trim(implode(' — ', array_filter([
                $certificate->certificate_type?->name_ar ?? (string) setting('cv.section.certificate_fallback', 'شهادة'),
                (string) ($certificate->issued_at?->format('Y/m') ?? ''),
                filled($certificate->code)
                    ? (string) setting('cv.section.certificate_number_prefix', 'رقم').' '.$certificate->code
                    : '',
            ])))
        )->all();

        if ($certificates !== []) {
            $sections[] = ['heading' => (string) setting('cv.section.certificates_label', 'الشهادات'), 'lines' => $certificates];
        }

        return $sections;
    }

    /**
     * سطر صفٍّ متكرّر.
     *
     * ⚠️ `$extra` صريحٌ لأنّ الأعمدة تختلف بين الأقسام: صفوف **التعليم** لا
     * تحمل `city` أصلًا بل `major` — وكان السطر يطبع مدينةً غير موجودة
     * ويُسقط التخصّص، فيخرج المؤهّل بلا تخصّصه.
     */
    private function entryLine(array $row, string $first, string $second, string $extra = 'city'): string
    {
        $period = trim(implode(' – ', array_filter([
            (string) ($row['from'] ?? ''),
            ($row['current'] ?? false)
                ? (string) setting('cv.until_now_label', 'حتى الآن')
                : (string) ($row['to'] ?? ''),
        ])));

        return trim(implode(' — ', array_filter([
            (string) ($row[$first] ?? ''),
            (string) ($row[$second] ?? ''),
            (string) ($row[$extra] ?? ''),
            $period,
        ])));
    }
}
