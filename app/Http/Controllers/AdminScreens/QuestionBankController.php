<?php

namespace App\Http\Controllers\AdminScreens;

use App\Http\Controllers\Controller;
use App\Models\LessonQuestion;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\AdminScreens\QuestionBank;
use App\Services\AdminScreens\ScreenSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بنك الأسئلة والامتحانات (24.1-3).
 *
 * سؤال واحد للشاشة: «إيه عندي في البنك، وهل يكفي الامتحان النهائيّ؟» (2.15-أ-1).
 * الفعل الرئيسيّ واحد (+ سؤال) والباقي داخل الصفّ، والتفاصيل في بوب-أب لا صفحة.
 */
class QuestionBankController extends Controller
{
    public function __construct(private readonly QuestionBank $bank) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $questions = $this->bank->paginate($filters);

        return view('admin.question-bank.index', [
            'filters' => $filters,
            'questions' => $questions,
            'usage' => $this->bank->usageCounts($questions->items()),
            'rates' => $this->bank->correctRates($questions->items()),
            'stats' => $this->bank->stats(),
            'types' => $this->bank->types(),
            'difficulties' => $this->bank->difficulties(),
            'courses' => $this->bank->courses(),
            'lessons' => $this->bank->lessons(($filters['course'] ?? '') !== '' ? (int) $filters['course'] : null),
            'exams' => $this->bank->exams(),
            'settings' => ScreenSettings::rows(ScreenSettings::SCREEN_BANK, $request->user()),
        ]);
    }

    /** معاينة الامتحان النهائيّ كما سيُبنى — رأسٌ ثابت وجسمٌ متمرّر في الواجهة */
    public function preview(Request $request): View
    {
        return view('admin.question-bank.preview', [
            'questions' => $this->bank->examPreview(),
            'cap' => $this->bank->cap(),
            'types' => $this->bank->types(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $question = LessonQuestion::create($data);

        AuditTrail::log($request->user(), 'question_bank.create', $question, [], $data);

        return back()->with('status', (string) setting('question_bank.admin.store_ok', 'اتحفظ السؤال ✓'));
    }

    public function update(Request $request, LessonQuestion $question): RedirectResponse
    {
        $data = $this->validated($request);
        $old = $question->only(array_keys($data));

        $question->update($data);

        AuditTrail::log($request->user(), 'question_bank.update', $question, $old, $data);

        return back()->with('status', (string) setting('question_bank.admin.update_ok', 'اتحفظ التعديل ✓'));
    }

    /** تبديل «سؤال عام» — صلاحيّة مستقلّة لأنّه يغيّر الامتحان النهائيّ نفسه */
    public function toggleGeneral(Request $request, LessonQuestion $question): RedirectResponse
    {
        $question->update(['is_general' => ! $question->is_general]);

        AuditTrail::log($request->user(), 'general_questions.edit', $question, [], ['is_general' => $question->is_general]);

        return back()->with('status', $question->is_general ? (string) setting('question_bank.admin.toggle_general_ok', 'بقى سؤالًا عامًّا ✓') : (string) setting('question_bank.admin.toggle_general_ok_2', 'خرج من الأسئلة العامّة ✓'));
    }

    /** التعطيل بدل الحذف — السؤال المعطّل يخرج من الامتحان ويبقى تاريخه */
    public function toggleActive(Request $request, LessonQuestion $question): RedirectResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

        AuditTrail::log($request->user(), 'question_bank.edit', $question, [], ['is_active' => $question->is_active]);

        return back()->with('status', $question->is_active ? (string) setting('question_bank.admin.toggle_active_ok', 'اترجّع للخدمة ✓') : (string) setting('question_bank.admin.toggle_active_ok_2', 'اتعطّل ✓'));
    }

    public function duplicate(Request $request, LessonQuestion $question): RedirectResponse
    {
        $copy = $question->replicate(['created_at', 'updated_at']);
        $copy->prompt = strtr((string) setting('question_bank.admin.duplicate_msg', ':a1 (نسخة)'), [':a1' => (string) ($question->prompt)]);
        $copy->is_active = false; // النسخة تبدأ معطّلة كي لا تدخل امتحانًا قبل مراجعتها
        $copy->save();

        AuditTrail::log($request->user(), 'question_bank.create', $copy, [], ['source' => $question->id]);

        return back()->with('status', (string) setting('question_bank.admin.duplicate_ok', 'اتعملت نسخة — راجعها وفعّلها ✓'));
    }

    public function move(Request $request, LessonQuestion $question): RedirectResponse
    {
        $data = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
        ], [], ['lesson_id' => (string) setting('question_bank.admin.move_msg', 'الدرس')]);

        $old = ['lesson_id' => $question->lesson_id];
        $question->update(['lesson_id' => (int) $data['lesson_id']]);

        AuditTrail::log($request->user(), 'question_bank.edit', $question, $old, $data);

        return back()->with('status', (string) setting('question_bank.admin.move_ok', 'اتنقل السؤال للدرس الجديد ✓'));
    }

    /** ⭐ إعادة استخدام السؤال في أكثر من امتحان (24.1-3) */
    public function reuse(Request $request, LessonQuestion $question): RedirectResponse
    {
        $data = $request->validate([
            'exam_ids' => ['required', 'array', 'min:1'],
            'exam_ids.*' => ['integer', 'exists:exams,id'],
        ], [], ['exam_ids' => (string) setting('question_bank.admin.reuse_msg', 'الامتحانات')]);

        // ⭐ «الأسئلة العامّة فقط» تدخل الامتحان النهائيّ (4) — والرسالة تقول ماذا يفعل (2.17-ج)
        if (! $question->is_general) {
            return back()->with('problem', (string) setting(
                'question_bank.messages.not_general',
                'السؤال ده مش معلَّم «عام»، والامتحان النهائيّ بيتبني من الأسئلة العامّة وحدها — علّمه «عام» الأوّل.',
            ));
        }

        $result = $this->bank->reuse($question, $data['exam_ids']);

        AuditTrail::log($request->user(), 'course_exam.edit', $question, [], $result);

        if ($result['attached'] === 0) {
            return back()->with('problem', (string) setting('question_bank.admin.reuse_empty', 'السؤال موجود في الامتحانات دي بالفعل — مافيش حاجة اتضافت.'));
        }

        return back()->with('status', strtr((string) setting('question_bank.admin.reuse_ok', 'اتضاف لـ:a1 امتحان ✓'), [':a1' => (string) ($result['attached'])]));
    }

    public function destroy(Request $request, LessonQuestion $question): RedirectResponse
    {
        AuditTrail::log($request->user(), 'question_bank.delete', $question, $question->only(['lesson_id', 'prompt']), []);

        $question->delete();

        return back()->with('status', (string) setting('question_bank.admin.destroy_ok', 'اتحذف السؤال ✓'));
    }

    /**
     * استيراد CSV — تقرير صفّ-بصفّ: الناجح يدخل والفاسد يُبلَّغ عنه بسطره،
     * فلا يضيع الملفّ كلّه بسبب خليّة واحدة (2.17-ب).
     */
    public function import(Request $request): RedirectResponse
    {
        abort_unless((bool) setting('question_bank.import_enabled', true), 403, (string) setting('question_bank.admin.import_msg', 'الاستيراد موقوف من إعدادات الشاشة.'));

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.max(1, (int) setting('question_bank.import_max_kb', 2048))],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
        ], [], ['file' => (string) setting('question_bank.admin.import_msg_2', 'الملفّ')]);

        $result = $this->bank->import(
            (string) file_get_contents($request->file('file')->getRealPath()),
            $data['lesson_id'] ?? null,
        );

        AuditTrail::log($request->user(), 'question_bank.import', null, [], ['imported' => $result['imported']]);

        if ($result['imported'] === 0) {
            return back()
                ->with('problem', (string) setting('question_bank.admin.import_msg_3', 'مادخلش ولا سؤال — راجع الأخطاء تحت وصلّحها في الملفّ ثمّ ارفعه تاني.'))
                ->with('import_errors', $result['errors']);
        }

        return back()
            ->with('status', strtr((string) setting('question_bank.admin.import_ok', 'اتستوردوا :a1 سؤال ✓'), [':a1' => (string) ($result['imported'])]))
            ->with('import_errors', $result['errors']);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->bank->exportRows($this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            if ($rows !== []) {
                fputcsv($handle, array_keys($rows[0]));

                foreach ($rows as $row) {
                    fputcsv($handle, array_values($row));
                }
            }

            fclose($handle);
        }, 'question-bank-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        ScreenSettings::putMany(ScreenSettings::SCREEN_BANK, $data['settings'], $request->user());

        return back()->with('status', (string) setting('question_bank.admin.save_settings_ok', 'اتحفظ ✓'));
    }

    public function resetSettings(Request $request): RedirectResponse
    {
        $count = ScreenSettings::resetScreen(ScreenSettings::SCREEN_BANK, $request->user());

        return back()->with('status', strtr((string) setting('question_bank.admin.reset_settings_ok', 'رجعت :a1 قيمة للافتراضيّ ✓'), [':a1' => (string) ($count)]));
    }

    /** @return array<string,string> */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString(),
            'course' => $request->string('course')->toString(),
            'lesson' => $request->string('lesson')->toString(),
            'type' => $request->string('type')->toString(),
            'difficulty' => $request->string('difficulty')->toString(),
            'general' => $request->string('general')->toString(),
            'state' => $request->string('state')->toString(),
        ];
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'type' => ['required', 'string', 'max:24'],
            'prompt' => ['required', 'string', 'max:500'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'options' => ['nullable', 'string', 'max:2000'],
            'correct_answer' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['required', 'string', 'max:16'],
            'is_general' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'lesson_id' => (string) setting('question_bank.admin.validated_msg', 'الدرس'),
            'type' => (string) setting('question_bank.admin.validated_msg_2', 'نوع السؤال'),
            'prompt' => (string) setting('question_bank.admin.validated_msg_3', 'نصّ السؤال'),
            'difficulty' => (string) setting('question_bank.admin.validated_msg_4', 'الصعوبة'),
        ]);

        // نوع أو صعوبة خارج الكتالوج = سؤال لن يُعرَض صحيحًا — نمنعه بدل تركه صامتًا
        abort_unless(array_key_exists($data['type'], $this->bank->types()), 422);
        abort_unless(array_key_exists($data['difficulty'], $this->bank->difficulties()), 422);

        return [
            'lesson_id' => (int) $data['lesson_id'],
            'type' => $data['type'],
            'prompt' => trim($data['prompt']),
            'placeholder' => ($data['placeholder'] ?? null) ?: null,
            'options' => ($data['options'] ?? '') !== ''
                ? array_values(array_filter(array_map('trim', explode('|', (string) $data['options']))))
                : null,
            'correct_answer' => ($data['correct_answer'] ?? null) ?: null,
            'difficulty' => $data['difficulty'],
            'is_general' => (bool) ($data['is_general'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
