<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\LessonQuestion;
use App\Models\Section;
use App\Services\Admin\Content\LessonBuilder;
use App\Services\Admin\Content\QuestionImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * بناء الدرس (12.4-ج): سيكشنز ودروس وأسئلة.
 *
 * الدرس إمّا **فيديو يوتيوب** (ID تلقائيّ + كود HTML تابع + مرفقات من المكتبة)
 * أو **نصّ** منسّق، ولكلٍّ تبويب أسئلة فيه **Placeholder داخل الحقل**
 * وتبديل **«سؤال عامّ»** الذي يُدخِله بنك الامتحان النهائيّ.
 */
class LessonAdminController extends Controller
{
    public function __construct(private readonly LessonBuilder $builder) {}

    // ------------------------------------------------------------------ السيكشنز

    public function storeSection(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'title_ar' => ['required', 'string', 'max:190'],
            'title_en' => ['nullable', 'string', 'max:190'],
        ]);

        $this->builder->addSection($course, $data['title_ar'], $data['title_en'] ?? null);

        return back()->with('status', (string) setting('lessons.admin.store_section_ok', 'اتضاف السيكشن ✓'));
    }

    public function updateSection(Request $request, Section $section): RedirectResponse
    {
        $data = $request->validate([
            'title_ar' => ['required', 'string', 'max:190'],
            'title_en' => ['nullable', 'string', 'max:190'],
        ]);

        $section->update($data);

        return back()->with('status', (string) setting('lessons.admin.update_section_ok', 'اتحفظ ✓'));
    }

    public function reorderSections(Request $request, Course $course): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        $this->builder->reorderSections($course, $data['ids']);

        return $this->respond($request, (string) setting('lessons.admin.reorder_sections_ok', 'اتظبط الترتيب ✓'));
    }

    /**
     * ⭐ **تكرار السيكشن** (12.4-هـ: «تكرار/نسخ (Duplicate) لتدريب · **سيكشن** · درس
     * كقالب جاهز») — الثالث الذي كان بلا مسار أصلًا.
     *
     * والصلاحيّة `sections.create` **منصوصةٌ في 12.2.2** («إضافة سيكشن جديد داخل
     * التدريب») — ولا مفتاح `duplicate` في المصفوفة، ولا يُخترَع مفتاح. وهو نفس
     * قياس تكرار التدريب الذي يحرسه `courses.create`.
     */
    public function duplicateSection(Section $section): RedirectResponse
    {
        $copy = $this->builder->duplicateSection($section);

        return redirect()
            ->route('admin.courses.edit', $copy->course_id)
            ->with('status', (string) setting('lessons.admin.duplicate_section_ok', 'اتعملت نسخة من السيكشن ✓'));
    }

    public function destroySection(Section $section): RedirectResponse
    {
        $section->delete();

        return back()->with('status', (string) setting('lessons.admin.destroy_section_ok', 'اتشال السيكشن ✓'));
    }

    // ------------------------------------------------------------------ الدروس

    public function show(Lesson $lesson): View
    {
        $section = Section::query()->findOrFail($lesson->section_id);
        $course = Course::query()->findOrFail($section->course_id);

        return view('admin.courses.lesson', [
            'lesson' => $lesson,
            'section' => $section,
            'course' => $course,
            'sections' => Section::query()->where('course_id', $course->id)->orderBy('sort_order')->get(),
            'questions' => LessonQuestion::query()->where('lesson_id', $lesson->id)->orderBy('sort_order')->get(),
            'attachments' => LessonAttachment::query()->with('media_item')->where('lesson_id', $lesson->id)->get(),
            /*
             | ⛔ **لا `mediaItems` بعد اليوم.** كانت قائمةً مقصوصةً بـ
             | `media.picker.limit` (12) تُطبَع في الصفحة Checkboxes بلا بحث —
             | فالملفّ الثالث عشر لا يمكن إرفاقه. والمرفقات صارت تُختار من **بوب-أب
             | المكتبة في وضعه المتعدّد** ببحثه وترقيمه (12.4-د · 12.4-هـ).
             */
            'csvColumns' => app(QuestionImporter::class)->columns(),
        ]);
    }

    public function storeLesson(Request $request, Section $section): RedirectResponse
    {
        $lesson = $this->builder->saveLesson($section, null, $this->lessonRules($request));

        return redirect()->route('admin.lessons.show', $lesson)->with('status', (string) setting('lessons.admin.store_lesson_ok', 'اتضاف الدرس ✓'));
    }

    public function updateLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $section = Section::query()->findOrFail($lesson->section_id);

        $this->builder->saveLesson($section, $lesson, $this->lessonRules($request));

        return back()->with('status', (string) setting('lessons.admin.update_lesson_ok', 'اتحفظ ✓'));
    }

    /** نقل الدرس بين السيكشنز — سحب-إفلات في الواجهة (12.4-هـ). */
    public function moveLesson(Request $request, Lesson $lesson): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'position' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->builder->move($lesson, Section::query()->findOrFail($data['section_id']), $data['position'] ?? null);

        return $this->respond($request, (string) setting('lessons.admin.move_lesson_ok', 'اتنقل الدرس ✓'));
    }

    public function duplicateLesson(Lesson $lesson): RedirectResponse
    {
        $copy = $this->builder->duplicateLesson($lesson);

        return redirect()->route('admin.lessons.show', $copy)->with('status', (string) setting('lessons.admin.duplicate_lesson_ok', 'اتعملت نسخة ✓'));
    }

    public function destroyLesson(Lesson $lesson): RedirectResponse
    {
        $lesson->delete();

        return back()->with('status', (string) setting('lessons.admin.destroy_lesson_ok', 'اتشال الدرس ✓'));
    }

    // ------------------------------------------------------------------ الأسئلة

    public function storeQuestion(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->builder->saveQuestion($lesson, null, $this->questionRules($request));

        return back()->with('status', (string) setting('lessons.admin.store_question_ok', 'اتضاف السؤال ✓'));
    }

    public function updateQuestion(Request $request, LessonQuestion $question): RedirectResponse
    {
        $lesson = Lesson::query()->findOrFail($question->lesson_id);

        $this->builder->saveQuestion($lesson, $question, $this->questionRules($request));

        return back()->with('status', (string) setting('lessons.admin.update_question_ok', 'اتحفظ ✓'));
    }

    /** تبديل «سؤال عامّ» — يدخل بنك الامتحان النهائيّ أو يخرج منه (12.4-ج). */
    public function toggleGeneral(Request $request, LessonQuestion $question): JsonResponse|RedirectResponse
    {
        $question->update(['is_general' => ! $question->is_general]);

        return $this->respond($request, $question->is_general ? (string) setting('lessons.admin.toggle_general_ok', 'بقى سؤالًا عامًّا ✓') : (string) setting('lessons.admin.toggle_general_ok_2', 'رجع سؤال درس ✓'));
    }

    public function destroyQuestion(LessonQuestion $question): RedirectResponse
    {
        $question->delete();

        return back()->with('status', (string) setting('lessons.admin.destroy_question_ok', 'اتشال السؤال ✓'));
    }

    /** استيراد أسئلة CSV — بتقرير صفّ-بصفّ لما فشل (12.4-هـ). */
    public function importQuestions(Request $request, Lesson $lesson, QuestionImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.(int) setting('lessons.questions.csv_max_kb', 2048)],
        ]);

        $result = $importer->import($lesson, $request->file('file'));

        $message = strtr((string) setting('lessons.admin.import_questions_ok', 'اتستورد :a1 سؤالًا ✓'), [':a1' => (string) ($result['imported'])]);

        if ($result['errors'] !== []) {
            $message .= strtr((string) setting('lessons.admin.import_questions_msg', ' — وفيه :a1 صفًّا محتاج مراجعة.'), [':a1' => (string) (count($result['errors']))]);
        }

        return back()->with('status', $message)->with('import_errors', $result['errors']);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<string, mixed> */
    private function lessonRules(Request $request): array
    {
        $data = $request->validate([
            'title_ar' => ['required', 'string', 'max:190'],
            'title_en' => ['nullable', 'string', 'max:190'],
            'type' => ['required', 'string', 'in:video,document'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'embed_html' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_free_preview' => ['nullable', 'boolean'],
            'attachment_ids' => ['nullable', 'array'],
            'attachment_ids.*' => ['integer', 'exists:media_items,id'],
            'attachments_managed' => ['nullable', 'boolean'],
        ]);

        /*
         | ⚠️ **فارغٌ ≠ غائب.** فورم HTML لا يرسل مصفوفةً فارغة، فإزالة آخر مرفق
         | كانت تصل الخادم **غيابًا** فيُبقي `syncAttachments` المرفق كما هو —
         | والأدمن يرى فعلًا بلا أثر (2.17-ب). فالعلَم من القالب يقول «هذا الفورم
         | يدير المرفقات»، ومعه يصير الغياب تفريغًا صريحًا.
         */
        if ($request->boolean('attachments_managed')) {
            $data['attachment_ids'] = (array) ($data['attachment_ids'] ?? []);
        }

        unset($data['attachments_managed']);

        return $data;
    }

    /** @return array<string, mixed> */
    private function questionRules(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:otp,choice,text'],
            'prompt' => ['required', 'string', 'max:500'],
            // نصّ Placeholder داخل الحقل (12.4-ج)
            'placeholder' => ['nullable', 'string', 'max:120'],
            'options' => ['nullable'],
            'correct_answer' => ['nullable', 'string', 'max:190'],
            'is_general' => ['nullable', 'boolean'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['placeholder'] = $data['placeholder'] ?? null;

        return $data;
    }

    private function respond(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }
}
