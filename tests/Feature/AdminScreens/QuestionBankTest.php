<?php

namespace Tests\Feature\AdminScreens;

use App\Models\Course;
use App\Models\ExamQuestion;
use App\Models\LessonQuestion;
use Illuminate\Http\UploadedFile;

/**
 * بنك الأسئلة والامتحانات (24.1-3): ترندر · الصلاحيّة تحجب · الفلاتر تشتغل ·
 * وإعادة استخدام السؤال في أكثر من امتحان — وهي علّة وجود الشاشة.
 */
class QuestionBankTest extends ScreensTestCase
{
    public function test_screen_renders_for_its_owner(): void
    {
        $lesson = $this->makeLesson();
        $this->makeQuestion($lesson);

        $this->actingAs($this->admin(['question_bank.list', 'question_bank.view']))
            ->get(route('admin.question-bank.index'))
            ->assertOk()
            ->assertSee('بنك الأسئلة والامتحانات');
    }

    /** الحالة الفارغة سطر واحد لا شاشة مكسورة (2.15-د) */
    public function test_empty_state_does_not_break_the_screen(): void
    {
        LessonQuestion::query()->delete();

        $this->actingAs($this->owner())
            ->get(route('admin.question-bank.index'))
            ->assertOk()
            ->assertSee('لسّه مافيش أسئلة في البنك');
    }

    public function test_permission_blocks_the_screen(): void
    {
        $this->actingAs($this->admin(['users.list']))
            ->get(route('admin.question-bank.index'))
            ->assertForbidden();

        $this->actingAs($this->admin(['question_bank.list']))
            ->post(route('admin.question-bank.import'))
            ->assertForbidden();
    }

    /** الفلاتر: التدريب · النوع · الصعوبة · العامّة فقط */
    public function test_filters_narrow_the_bank(): void
    {
        $lessonA = $this->makeLesson('تدريب ألف');
        $lessonB = $this->makeLesson('تدريب باء');

        $easy = $this->makeQuestion($lessonA, ['prompt' => 'سؤال سهل جدًّا', 'difficulty' => 'easy', 'type' => 'choice']);
        $hard = $this->makeQuestion($lessonB, ['prompt' => 'سؤال صعب جدًّا', 'difficulty' => 'hard', 'type' => 'text', 'is_general' => false]);

        $owner = $this->owner();
        $courseA = Course::query()->where('name_ar', 'تدريب ألف')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('admin.question-bank.index', ['course' => $courseA->id]))
            ->assertOk()
            ->assertSee($easy->prompt)
            ->assertDontSee($hard->prompt);

        $this->actingAs($owner)
            ->get(route('admin.question-bank.index', ['difficulty' => 'hard']))
            ->assertOk()
            ->assertSee($hard->prompt)
            ->assertDontSee($easy->prompt);

        $this->actingAs($owner)
            ->get(route('admin.question-bank.index', ['type' => 'text']))
            ->assertOk()
            ->assertSee($hard->prompt)
            ->assertDontSee($easy->prompt);

        $this->actingAs($owner)
            ->get(route('admin.question-bank.index', ['general' => '1']))
            ->assertOk()
            ->assertSee($easy->prompt)
            ->assertDontSee($hard->prompt);
    }

    /** ⭐ إعادة استخدام السؤال في أكثر من امتحان بلا ازدواج */
    public function test_a_question_can_be_reused_across_several_exams(): void
    {
        $lesson = $this->makeLesson();
        $question = $this->makeQuestion($lesson);

        $first = $this->makeExam(Course::query()->firstOrFail());
        $second = $this->makeExam($this->makeLesson('تدريب تاني')->section->course);

        $this->actingAs($this->owner())
            ->post(route('admin.question-bank.reuse', $question), ['exam_ids' => [$first->id, $second->id]])
            ->assertRedirect();

        $this->assertSame(2, ExamQuestion::query()->where('source_question_id', $question->id)->count());

        // نداء ثانٍ لا يكرّر السؤال في نفس الامتحان
        $this->actingAs($this->owner())
            ->post(route('admin.question-bank.reuse', $question), ['exam_ids' => [$first->id]])
            ->assertRedirect();

        $this->assertSame(2, ExamQuestion::query()->where('source_question_id', $question->id)->count());
    }

    /** التعطيل بدل الحذف — والسؤال المعطّل يخرج من معاينة الامتحان */
    public function test_disabled_questions_leave_the_exam_preview(): void
    {
        LessonQuestion::query()->delete();

        $lesson = $this->makeLesson();
        $question = $this->makeQuestion($lesson, ['prompt' => 'سؤال هيتعطّل']);

        $this->actingAs($this->owner())
            ->get(route('admin.question-bank.preview'))
            ->assertOk()
            ->assertSee('سؤال هيتعطّل');

        $this->actingAs($this->owner())
            ->post(route('admin.question-bank.active', $question))
            ->assertRedirect();

        $this->assertFalse((bool) $question->refresh()->is_active);

        $this->actingAs($this->owner())
            ->get(route('admin.question-bank.preview'))
            ->assertOk()
            ->assertDontSee('سؤال هيتعطّل');
    }

    /** الاستيراد يبلّغ عن الصفّ الفاسد ولا يُسقط الملفّ كلّه (2.17-ب) */
    public function test_csv_import_reports_bad_rows_row_by_row(): void
    {
        $lesson = $this->makeLesson();

        $csv = "lesson_id,type,prompt,placeholder,options,correct_answer,is_general,difficulty\n"
            .$lesson->id.",choice,سؤال سليم,,أ|ب,أ,1,easy\n"
            .$lesson->id.",unknown,سؤال بنوع غلط,,,,0,medium\n"
            ."999999,choice,سؤال بدرس مش موجود,,,,0,medium\n";

        $this->actingAs($this->owner())
            ->post(route('admin.question-bank.import'), [
                'file' => UploadedFile::fake()->createWithContent('bank.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('lesson_questions', ['prompt' => 'سؤال سليم']);
        $this->assertDatabaseMissing('lesson_questions', ['prompt' => 'سؤال بنوع غلط']);
        $this->assertCount(2, session('import_errors'));
    }

    /** كلّ رقم في الشاشة إعداد — والتعديل يسري فورًا (2.13) */
    public function test_settings_are_editable_and_resettable(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.question-bank.settings'), [
            'settings' => ['question_bank.exam_question_cap' => 33],
        ])->assertRedirect();

        $this->assertSame(33, (int) setting('question_bank.exam_question_cap'));

        $this->actingAs($owner)->post(route('admin.question-bank.settings.reset'))->assertRedirect();

        $this->assertSame(20, (int) setting('question_bank.exam_question_cap'));
    }
}
