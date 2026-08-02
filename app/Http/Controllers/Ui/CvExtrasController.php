<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Cv;
use App\Services\Library\AtsPdfWriter;
use App\Services\Library\CvBuilder;
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
            'file.required' => 'اختار ملفّ الأوّل.',
            'file.max' => 'الملفّ كبير شويّة — أقصى حجم '.$this->importer->maxKb().' كيلوبايت.',
        ], ['file' => 'الملفّ']);

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
                'message' => 'مفيش تحليل محفوظ — ارفع الملفّ تاني وراجع المعاينة.',
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
            'message' => 'اتحفظ ✓',
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

        $binary = $this->pdf->build(
            (string) $user->name,
            $this->headline($data),
            $this->sections($user, $data),
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
            'message' => $enable ? 'الرابط شغّال ✓' : 'الرابط اتقفل ✓',
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

        $cv->increment('public_views');

        $user = $cv->user;
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
            $sections[] = ['heading' => 'بيانات التواصل', 'lines' => [implode(' · ', $contact)]];
        }

        if (trim((string) ($profile['summary'] ?? '')) !== '') {
            $sections[] = ['heading' => 'الملخّص المهنيّ', 'lines' => [(string) $profile['summary']]];
        }

        $experience = [];

        foreach ((array) ($data['experience'] ?? []) as $row) {
            $experience[] = $this->entryLine($row, 'title', 'company');

            if (trim((string) ($row['description'] ?? '')) !== '') {
                $experience[] = (string) $row['description'];
            }
        }

        if ($experience !== []) {
            $sections[] = ['heading' => 'الخبرة العمليّة', 'lines' => $experience];
        }

        $education = [];

        foreach ((array) ($data['education'] ?? []) as $row) {
            $education[] = $this->entryLine($row, 'degree', 'institution');
        }

        if ($education !== []) {
            $sections[] = ['heading' => 'التعليم', 'lines' => $education];
        }

        if (trim((string) ($data['skills'] ?? '')) !== '') {
            $sections[] = ['heading' => 'المهارات', 'lines' => [(string) $data['skills']]];
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
            $sections[] = ['heading' => 'اللغات', 'lines' => $languages];
        }

        $certificates = $pulled['certificates']->map(
            fn ($certificate) => trim(($certificate->certificate_type?->name_ar ?? 'شهادة')
                .' — '.($certificate->issued_at?->format('Y/m') ?? '')
                .' — رقم '.$certificate->serial)
        )->all();

        if ($certificates !== []) {
            $sections[] = ['heading' => 'الشهادات', 'lines' => $certificates];
        }

        return $sections;
    }

    private function entryLine(array $row, string $first, string $second): string
    {
        $period = trim(implode(' – ', array_filter([
            (string) ($row['from'] ?? ''),
            ($row['current'] ?? false) ? 'حتى الآن' : (string) ($row['to'] ?? ''),
        ])));

        return trim(implode(' — ', array_filter([
            (string) ($row[$first] ?? ''),
            (string) ($row[$second] ?? ''),
            (string) ($row['city'] ?? ''),
            $period,
        ])));
    }
}
