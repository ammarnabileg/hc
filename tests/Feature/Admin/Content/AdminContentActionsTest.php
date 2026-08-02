<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Course;
use App\Models\CourseLearningPath;
use App\Models\Exam;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\Section;
use Illuminate\Http\UploadedFile;

/**
 * مزايا إدارة التدريب المعتمَدة (12.4-هـ): ترتيب · تكرار · نقل · إجراءات جماعيّة · استيراد CSV.
 */
class AdminContentActionsTest extends AdminContentTestCase
{
    /** سحب الصفوف لترتيب المسارات — والترتيب يُحفَظ على الخادم (12.4-أ). */
    public function test_paths_can_be_reordered(): void
    {
        $ids = LearningPath::query()->orderBy('sort_order')->pluck('id')->all();
        $reversed = array_reverse($ids);

        $this->actingAs($this->admin())
            ->postJson(route('admin.paths.reorder'), ['ids' => $reversed])
            ->assertOk();

        $this->assertSame($reversed, LearningPath::query()->orderBy('sort_order')->pluck('id')->all());
    }

    /** سعر امتحان شهادة المسار بالكوينز — لكلّ مسار على حدة (12.4-أ). */
    public function test_path_exam_price_is_stored_and_synced_to_the_exam(): void
    {
        $this->actingAs($this->admin())->post(route('admin.paths.store'), [
            'name_ar' => 'مسار المهارات الرقميّة',
            'status' => 'published',
            'exam_price_coins' => 175,
            'forced_order' => 1,
        ])->assertRedirect();

        $path = LearningPath::query()->where('name_ar', 'مسار المهارات الرقميّة')->firstOrFail();

        $this->assertSame(175.0, (float) $path->exam_price_coins);
        $this->assertTrue((bool) $path->forced_order);

        $exam = Exam::query()
            ->where('examable_type', $path->getMorphClass())
            ->where('examable_id', $path->id)
            ->firstOrFail();

        $this->assertSame(175.0, (float) $exam->price_coins);
    }

    /** تكرار التدريب ينسخ سيكشنزه ودروسه وأسئلته كمسودّة (12.4-هـ). */
    public function test_duplicating_a_course_copies_its_content_as_draft(): void
    {
        $course = Course::query()->firstOrFail();
        $lessons = Lesson::query()
            ->whereIn('section_id', Section::query()->where('course_id', $course->id)->pluck('id'))
            ->count();

        $this->actingAs($this->admin())
            ->post(route('admin.courses.duplicate', $course))
            ->assertRedirect();

        $copy = Course::query()->where('id', '!=', $course->id)
            ->where('name_ar', 'like', $course->name_ar.'%')
            ->latest('id')->firstOrFail();

        $this->assertSame('draft', $copy->status);
        $this->assertSame(
            $lessons,
            Lesson::query()->whereIn('section_id', Section::query()->where('course_id', $copy->id)->pluck('id'))->count(),
        );
    }

    /** إجراءات جماعيّة: نشر ونقل لمسار (12.4-هـ). */
    public function test_bulk_actions_publish_and_move_to_path(): void
    {
        $admin = $this->admin();
        $target = LearningPath::query()->latest('id')->firstOrFail();
        $ids = Course::query()->take(2)->pluck('id')->all();

        $this->actingAs($admin)->post(route('admin.courses.bulk'), [
            'action' => 'publish',
            'ids' => $ids,
        ])->assertRedirect();

        foreach ($ids as $id) {
            $this->assertSame('published', Course::query()->find($id)->status);
        }

        $this->actingAs($admin)->post(route('admin.courses.bulk'), [
            'action' => 'move_path',
            'ids' => $ids,
            'path_id' => $target->id,
        ])->assertRedirect();

        foreach ($ids as $id) {
            $this->assertTrue(
                CourseLearningPath::query()->where('course_id', $id)->where('learning_path_id', $target->id)->exists(),
            );
        }
    }

    /** نقل الدرس بين السيكشنز (12.4-هـ). */
    public function test_lesson_moves_between_sections(): void
    {
        $course = Course::query()->firstOrFail();
        $sections = Section::query()->where('course_id', $course->id)->orderBy('sort_order')->get();
        $lesson = Lesson::query()->where('section_id', $sections->first()->id)->firstOrFail();

        $this->actingAs($this->admin())->postJson(route('admin.lessons.move', $lesson), [
            'section_id' => $sections->last()->id,
        ])->assertOk();

        $this->assertSame($sections->last()->id, $lesson->refresh()->section_id);
    }

    /** استيراد أسئلة CSV — بتقرير صفّ-بصفّ لما فشل (12.4-هـ). */
    public function test_csv_question_import_reports_bad_rows(): void
    {
        $course = Course::query()->firstOrFail();
        $section = Section::query()->where('course_id', $course->id)->firstOrFail();
        $lesson = Lesson::query()->where('section_id', $section->id)->firstOrFail();
        $before = LessonQuestion::query()->where('lesson_id', $lesson->id)->count();

        $csv = "type,prompt,placeholder,options,correct_answer,is_general\n"
            ."otp,كام درس في السيكشن؟,اكتب الرقم,,2,1\n"
            ."choice,اختر الإجابة,اختر,أ|ب|ج,أ,0\n"
            .",,,,,\n";

        $this->actingAs($this->admin())
            ->post(route('admin.questions.import', $lesson), [
                'file' => UploadedFile::fake()->createWithContent('questions.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('import_errors');

        $this->assertSame($before + 2, LessonQuestion::query()->where('lesson_id', $lesson->id)->count());

        $imported = LessonQuestion::query()->where('lesson_id', $lesson->id)->latest('id')->first();
        $this->assertSame(['أ', 'ب', 'ج'], $imported->options);
    }

    /** الدرس: استخراج ID اليوتيوب تلقائيًّا من الرابط (12.4-ج). */
    public function test_youtube_id_is_extracted_from_any_link_shape(): void
    {
        $course = Course::query()->firstOrFail();
        $section = Section::query()->where('course_id', $course->id)->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.lessons.store', $section), [
            'title_ar' => 'درس بفيديو',
            'type' => 'video',
            'video_url' => 'https://www.youtube.com/watch?v=abcd1234XY&t=30',
        ])->assertRedirect();

        $lesson = Lesson::query()->where('title_ar', 'درس بفيديو')->firstOrFail();

        $this->assertSame('abcd1234XY', $lesson->video_id);
        $this->assertSame('youtube', $lesson->video_provider);
    }
}
