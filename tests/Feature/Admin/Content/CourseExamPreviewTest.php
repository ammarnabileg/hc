<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\Section;
use App\Services\Admin\Content\CourseFormService;
use App\Services\AdminScreens\QuestionBank;

/**
 * ⭐ **معاينة الامتحان النهائيّ كما سيُبنى** (12.4-هـ).
 *
 * النصّ الحاكم حرفيًّا (12.4-هـ — مزايا معتمدة على إدارة التدريب):
 *  > «**مؤشّر «الأسئلة العامّة» مقابل حدّ الامتحان** (تحذير لو أقلّ من العدد
 *  > المطلوب) + **معاينة الامتحان النهائيّ كما سيُبنى**.»
 *
 * والمرصود قبل هذا الملفّ: **المؤشّر وحده** كان مبنيًّا، و«المعاينة» المذكورة
 * معه في نفس الجملة غائبة. وما كان موجودًا شيئان آخران يلتبسان بها:
 *  - `admin/courses/preview.blade.php` وهي **«معاينة كطالب»** — محتوى التدريب
 *    لا امتحانه، وهي بندٌ آخر في نفس الفقرة.
 *  - `admin.question-bank.preview` وهي معاينة **البنك كلّه** (24.1-3) — تجيب عن
 *    «هل بنك المنصّة يكفي؟» ولا تعرف تدريبًا بعينه ولا قواعده.
 *
 * و«**كما سيُبنى**» تُؤخَذ حرفيًّا: المعاينة تقرأ **نفس الميثود** التي يقرؤها
 * المبنيّ (`QuestionBank::builtQuestions()`)، فلا تستطيع أن تَعِد الأدمن
 * بامتحانٍ غير الذي يمتحنه الناس.
 *
 * والطفرات التي يمسكها هذا الملفّ:
 *  - معاينةٌ تتجاهل `exams.questions_count` ⟵ يسقط `the_preview_stops_at_the_configured_count`.
 *  - تعديل قاعدةٍ لا ينعكس على المعاينة ⟵ يسقط `changing_the_configured_count_changes_the_preview`.
 *  - تدريبٌ بلا امتحانٍ محفوظ يكسر الشاشة ⟵ يسقط `a_course_without_an_exam_shows_guidance_not_an_error`.
 *  - معاينةٌ تنسخ الاختيار لنفسها فتفترق عن المبنيّ ⟵ يسقط `the_preview_and_the_trainee_exam_read_one_source`.
 *  - نقصٌ صامت بلا مرشّحين ⟵ يسقط `a_short_exam_names_its_shortfall_and_its_candidates`.
 */
class CourseExamPreviewTest extends AdminContentTestCase
{
    /** ⭐ المعاينة تقف عند **العدد المحفوظ للتدريب** لا عند كلّ ما ضُمّ للامتحان. */
    public function test_the_preview_stops_at_the_configured_count(): void
    {
        [$course, $exam] = $this->courseWithExam(questionsCount: 2);
        $this->attach($exam, ['السؤال الأوّل', 'السؤال الثاني', 'السؤال الثالث']);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.courses.exam-preview', $course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('السؤال الأوّل', $html);
        $this->assertStringContainsString('السؤال الثاني', $html);
        $this->assertStringNotContainsString('السؤال الثالث', $html,
            'المعاينة عرضت سؤالًا خارج العدد المحفوظ — فهي لا تعرض الامتحان «كما سيُبنى» (12.4-هـ).');

        // والقواعد المحفوظة نفسها معروضة — فالأدمن يقرأ «على أساس إيه» لا الناتج وحده
        $this->assertStringContainsString((string) $exam->pass_score, $html);
    }

    /** ⭐ تعديل قاعدةٍ في تاب «التقييم» ⟵ المعاينة تتغيّر معها فورًا. */
    public function test_changing_the_configured_count_changes_the_preview(): void
    {
        [$course, $exam] = $this->courseWithExam(questionsCount: 2);
        $this->attach($exam, ['السؤال الأوّل', 'السؤال الثاني', 'السؤال الثالث']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.courses.exam-preview', $course))
            ->assertOk()
            ->assertDontSee('السؤال الثالث');

        // نفس الباب الذي يستعمله الأدمن: حفظُ الفورم بتاب التقييم (12.4-ب)
        $this->actingAs($admin)
            ->put(route('admin.courses.update', $course), [
                'name_ar' => $course->name_ar,
                'exam_pass_score' => $exam->pass_score,
                'exam_questions_count' => 3,
            ])
            ->assertRedirect();

        $this->assertSame(3, (int) $exam->fresh()->questions_count);

        $this->actingAs($admin)
            ->get(route('admin.courses.exam-preview', $course))
            ->assertOk()
            ->assertSee('السؤال الثالث', false);
    }

    /**
     * ⭐ تدريبٌ بلا إعداد امتحانٍ محفوظ: **إرشادٌ يقول الخطوة التالية**
     * لا خطأ ولا أصفارٌ كاذبة (2.15-د · 2.17-ج).
     */
    public function test_a_course_without_an_exam_shows_guidance_not_an_error(): void
    {
        $course = Course::create([
            'name_ar' => 'تدريبٌ بلا امتحان',
            'slug' => 'course-without-exam',
            'status' => 'draft',
        ]);

        $this->assertNull(app(CourseFormService::class)->exam($course));

        $this->actingAs($this->admin())
            ->get(route('admin.courses.exam-preview', $course))
            ->assertOk()
            ->assertSee(setting('admin.courses.exam_preview.lssh_mafysh_amthan_mhfwz_lltdryb_dh_afth_tab'), false);
    }

    /**
     * ⭐ **مصدرٌ واحد لا نسختان:** ما تعرضه المعاينة هو حرفيًّا ما تبنيه شاشة
     * الامتحان — فلو نسخ أحدُهما الاستعلامَ لنفسه لافترقا بأوّل تعديل.
     */
    public function test_the_preview_and_the_trainee_exam_read_one_source(): void
    {
        [$course, $exam] = $this->courseWithExam(questionsCount: 2);
        $this->attach($exam, ['السؤال الأوّل', 'السؤال الثاني', 'السؤال الثالث']);

        $built = app(QuestionBank::class)->builtQuestions($exam);
        $preview = app(CourseFormService::class)->examPreview($course);

        $this->assertSame(
            $built->pluck('id')->all(),
            $preview['questions']->pluck('id')->all(),
            'المعاينة تعرض اختيارًا غير الذي يبنيه الامتحان — والبند يقول «كما سيُبنى» (12.4-هـ).',
        );

        // وشاشة الامتحان نفسها تقرأ من هنا: الميثود الخاصّة صارت تفويضًا لا استعلامًا
        $source = (string) file_get_contents(app_path('Http/Controllers/Trainee/ExamController.php'));
        $this->assertStringContainsString('$this->bank->builtQuestions($exam)', $source,
            'شاشة الامتحان رجعت تبني اختيارها بنفسها — فالمعاينة تفترق عن المبنيّ.');
    }

    /** ⭐ النقص يُقال برقمه، ويُقال **منين يتسدّ**: مرشّحون بالاسم لا تخمين. */
    public function test_a_short_exam_names_its_shortfall_and_its_candidates(): void
    {
        [$course, $exam] = $this->courseWithExam(questionsCount: 4);
        $this->attach($exam, ['السؤال الأوّل']);

        $lesson = $this->lessonIn($course);

        LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'choice',
            'prompt' => 'مرشّحٌ عامّ نشط',
            'options' => ['أ', 'ب'],
            'correct_answer' => 'أ',
            'is_general' => true,
            'is_active' => true,
            'difficulty' => 'hard',
            'sort_order' => 9,
        ]);

        // ⛔ وسؤالٌ غير عامّ لا يُرشَّح أبدًا: الامتحان النهائيّ للعامّة وحدها (4 · 4.2)
        LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'prompt' => 'سؤالُ درسٍ لا يصلح للامتحان',
            'is_general' => false,
            'is_active' => true,
            'difficulty' => 'easy',
            'sort_order' => 10,
        ]);

        $preview = app(CourseFormService::class)->examPreview($course);

        $this->assertSame(3, $preview['shortfall'],
            'النقص محسوبٌ غلط — والأدمن ينشر امتحانًا ناقصًا وهو مطمئنّ.');

        $this->assertContains('مرشّحٌ عامّ نشط', $preview['candidates']->pluck('prompt')->all());
        $this->assertNotContains('سؤالُ درسٍ لا يصلح للامتحان', $preview['candidates']->pluck('prompt')->all(),
            'سؤالٌ غير عامّ رُشِّح للامتحان النهائيّ — وهو ما تمنعه 4.2.');

        $html = $this->actingAs($this->admin())
            ->get(route('admin.courses.exam-preview', $course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('مرشّحٌ عامّ نشط', $html);
    }

    /**
     * ⭐ التوزيع يقول **من أين** جاء كلّ سؤال: من دروس هذا التدريب، أم عامًّا من
     * تدريبٍ آخر، أم مكتوبًا في الامتحان مباشرةً — وهو جواب «هل امتحاني امتحانُ
     * تدريبي فعلًا؟». والسؤال بلا أصلٍ لا تُخترَع له صعوبة (2.9-7).
     */
    public function test_the_breakdown_tells_own_questions_from_borrowed_ones(): void
    {
        [$course, $exam] = $this->courseWithExam(questionsCount: 5);

        $own = LessonQuestion::create([
            'lesson_id' => $this->lessonIn($course)->id,
            'type' => 'choice', 'prompt' => 'سؤال هذا التدريب',
            'is_general' => true, 'is_active' => true, 'difficulty' => 'easy', 'sort_order' => 1,
        ]);

        $other = LessonQuestion::create([
            'lesson_id' => $this->lessonIn($this->otherCourse())->id,
            'type' => 'choice', 'prompt' => 'سؤال تدريبٍ آخر',
            'is_general' => true, 'is_active' => true, 'difficulty' => 'hard', 'sort_order' => 1,
        ]);

        foreach ([$own, $other] as $order => $source) {
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'source_question_id' => $source->id,
                'type' => $source->type,
                'prompt' => $source->prompt,
                'correct_answer' => 'أ',
                'weight' => 1,
                'sort_order' => $order + 1,
            ]);
        }

        // سؤالٌ كُتِب في الامتحان مباشرةً — بلا أصلٍ في البنك
        ExamQuestion::create([
            'exam_id' => $exam->id,
            'type' => 'text', 'prompt' => 'سؤالٌ مكتوبٌ يدويًّا',
            'weight' => 1, 'sort_order' => 3,
        ]);

        $preview = app(CourseFormService::class)->examPreview($course);

        $this->assertSame(1, $preview['by_source']['own'] ?? 0);
        $this->assertSame(1, $preview['by_source']['other'] ?? 0);
        $this->assertSame(1, $preview['by_source']['manual'] ?? 0);

        $this->assertSame(1, $preview['by_difficulty']['easy'] ?? 0);
        $this->assertSame(1, $preview['by_difficulty']['hard'] ?? 0);
        $this->assertSame(1, $preview['by_difficulty']['unknown'] ?? 0,
            'صعوبةٌ اختُرِعت لسؤالٍ بلا أصل — والأرقام صادقة أو لا تُعرَض (2.9-7).');
    }

    /** ⭐ الوصلة موجودة حيث يقرن البند المعاينةَ بالمؤشّر: تاب «التقييم» (12.4-هـ). */
    public function test_the_course_form_links_to_the_preview_next_to_the_indicator(): void
    {
        [$course] = $this->courseWithExam(questionsCount: 2);

        $this->actingAs($this->admin())
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->assertSee(route('admin.courses.exam-preview', $course), false);
    }

    /** ⭐ والصلاحيّة إلزاميّة على المسار كغيره (12.2.1). */
    public function test_the_route_is_guarded(): void
    {
        [$course] = $this->courseWithExam(questionsCount: 2);

        $guards = collect(app('router')->getRoutes()->getByName('admin.courses.exam-preview')?->gatherMiddleware() ?? [])
            ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'))
            ->values();

        $this->assertSame(['permission:courses.list,courses.view'], $guards->all(),
            'المسار بلا صلاحيّةٍ منصوصة أو بمفتاحٍ مخترَع — والصلاحيّة إلزاميّة على كلّ مسار (12.2.1).');

        $this->actingAs($this->makeUser())
            ->get(route('admin.courses.exam-preview', $course))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------ أدوات

    /** @return array{0: Course, 1: Exam} */
    private function courseWithExam(int $questionsCount): array
    {
        $course = Course::query()->firstOrFail();

        $exam = Exam::create([
            'examable_type' => $course->getMorphClass(),
            'examable_id' => $course->id,
            'title_ar' => 'امتحان '.$course->name_ar,
            'duration_minutes' => 30,
            'pass_score' => 70,
            'questions_count' => $questionsCount,
        ]);

        return [$course, $exam];
    }

    private function otherCourse(): Course
    {
        return Course::query()->where('id', '!=', Course::query()->firstOrFail()->id)->firstOrFail();
    }

    private function lessonIn(Course $course): Lesson
    {
        $section = Section::query()->where('course_id', $course->id)->first()
            ?? Section::create(['course_id' => $course->id, 'title_ar' => 'سيكشن', 'sort_order' => 1]);

        return Lesson::query()->where('section_id', $section->id)->first()
            ?? Lesson::create([
                'section_id' => $section->id,
                'title_ar' => 'درس',
                'type' => 'document',
                'content' => 'نصّ',
                'sort_order' => 1,
            ]);
    }

    /** @param  list<string>  $prompts */
    private function attach(Exam $exam, array $prompts): void
    {
        foreach ($prompts as $order => $prompt) {
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'type' => 'choice',
                'prompt' => $prompt,
                'options' => ['أ', 'ب'],
                'correct_answer' => 'أ',
                'weight' => 1,
                'sort_order' => $order + 1,
            ]);
        }
    }
}
