<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Course;
use App\Models\CourseLearningPath;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\MediaItem;
use App\Models\Section;
use App\Services\Admin\Content\MediaLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * إدارة التدريب (12.4 · 24.1) — كلّ اختبار يقابل قاعدةً منصوصة في الدستور.
 */
class AdminContentTrainingTest extends AdminContentTestCase
{
    /** ⭐ حذف المسار **لا يحذف تدريباته** (12.4-أ). */
    public function test_deleting_a_path_keeps_its_courses(): void
    {
        $path = LearningPath::query()->orderBy('id')->first();
        $courseIds = CourseLearningPath::query()->where('learning_path_id', $path->id)->pluck('course_id');

        $this->assertGreaterThan(0, $courseIds->count(), 'لازم يكون في تدريبات مربوطة بالمسار قبل الاختبار');

        $this->actingAs($this->admin())
            ->delete(route('admin.paths.destroy', $path))
            ->assertRedirect();

        $this->assertNull(LearningPath::query()->find($path->id), 'المسار المفروض يتشال');

        foreach ($courseIds as $courseId) {
            $this->assertNotNull(
                Course::query()->find($courseId),
                'حذف المسار ما ينفعش يحذف تدريباته (12.4-أ)',
            );
        }
    }

    /** ⭐ التدريب يجوز أن يكون في **أكثر من مسار** (12.4-أ). */
    public function test_a_course_can_belong_to_more_than_one_path(): void
    {
        $admin = $this->admin();
        $paths = LearningPath::query()->orderBy('id')->take(2)->get();
        $course = Course::query()->where('slug', 'shared-communication')->firstOrFail();

        $this->assertSame(
            2,
            CourseLearningPath::query()->where('course_id', $course->id)->count(),
            'التدريب المشترك المفروض يكون في مسارين',
        );

        // والحفظ من الفورم يحافظ على الانتماء المتعدّد
        $this->actingAs($admin)->put(route('admin.courses.update', $course), [
            'name_ar' => $course->name_ar,
            'status' => 'published',
            'path_ids' => $paths->pluck('id')->all(),
        ])->assertRedirect();

        $this->assertSame(2, CourseLearningPath::query()->where('course_id', $course->id)->count());
    }

    /** الإزالة من المسار فكّ ارتباط لا حذف — التدريب يبقى (12.4-أ). */
    public function test_detaching_a_course_from_a_path_does_not_delete_it(): void
    {
        $path = LearningPath::query()->orderBy('id')->first();
        $pivot = CourseLearningPath::query()->where('learning_path_id', $path->id)->firstOrFail();
        $course = Course::query()->findOrFail($pivot->course_id);

        $this->actingAs($this->admin())
            ->delete(route('admin.paths.courses.detach', [$path, $course]))
            ->assertRedirect();

        $this->assertNotNull(Course::query()->find($course->id));
        $this->assertDatabaseMissing('course_learning_path', [
            'learning_path_id' => $path->id,
            'course_id' => $course->id,
        ]);
    }

    /** «حفظ واستمرار» يحفظ **مسودّة** ويبقيك في التحرير (12.4-ب). */
    public function test_save_and_continue_stores_a_draft_and_keeps_editing(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.courses.store'), [
            'name_ar' => 'تدريب تحت التجهيز',
            'status' => 'published',
            'continue' => 1,
        ]);

        $course = Course::query()->where('name_ar', 'تدريب تحت التجهيز')->firstOrFail();

        $response->assertRedirect(route('admin.courses.edit', $course));
        $this->assertSame('draft', $course->status, '«حفظ واستمرار» لازم يحفظ درافت مهما كانت الحالة المختارة');
    }

    /** الحفظ التلقائيّ يردّ «اتحفظ ✓» ولا يغيّر حالة النشر (2.17-ب). */
    public function test_autosave_never_changes_publication_status(): void
    {
        $course = Course::query()->firstOrFail();
        $status = $course->status;

        $this->actingAs($this->admin())
            ->postJson(route('admin.courses.autosave', $course), ['name_ar' => 'اسم اتغيّر أثناء الكتابة'])
            ->assertOk()
            ->assertJsonStructure(['message', 'at']);

        $course->refresh();

        $this->assertSame('اسم اتغيّر أثناء الكتابة', $course->name_ar);
        $this->assertSame($status, $course->status);
    }

    /** «سؤال عامّ» يدخل بنك الامتحان النهائيّ ويظهر في المؤشّر (12.4-ج · 12.4-هـ). */
    public function test_general_question_toggle_feeds_the_exam_bank(): void
    {
        $course = Course::query()->firstOrFail();
        $section = Section::query()->where('course_id', $course->id)->firstOrFail();
        $lesson = Lesson::query()->where('section_id', $section->id)->firstOrFail();

        $question = LessonQuestion::query()->where('lesson_id', $lesson->id)->firstOrFail();
        $this->assertTrue((bool) $question->is_general);

        $this->actingAs($this->admin())
            ->post(route('admin.questions.general', $question))
            ->assertRedirect();

        $this->assertFalse((bool) $question->refresh()->is_general);
    }

    /** ⭐ مكتبة الوسائط: **Dedup بالهاش** — نفس الملفّ يعيد النسخة الموجودة (12.4-د). */
    public function test_media_library_deduplicates_by_hash(): void
    {
        Storage::fake('public');

        $library = app(MediaLibrary::class);
        $admin = $this->admin();

        $first = $library->store(UploadedFile::fake()->createWithContent('a.txt', 'نفس المحتوى'), $admin);
        $second = $library->store(UploadedFile::fake()->createWithContent('b.txt', 'نفس المحتوى'), $admin);

        $this->assertFalse($first['duplicated']);
        $this->assertTrue($second['duplicated'], 'الملفّ المتطابق المفروض يرجّع النسخة الموجودة');
        $this->assertSame($first['item']->id, $second['item']->id);
        $this->assertSame(1, MediaItem::query()->where('hash', $first['item']->hash)->count());
    }

    /** الشاشات الرئيسيّة تفتح لمن يملك الصلاحيّة، وتُمنَع عمّن لا يملكها (12.2.1). */
    public function test_main_screens_require_permission(): void
    {
        $admin = $this->admin();

        foreach (['admin.paths.index', 'admin.courses.index', 'admin.media.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }

        $stranger = $this->makeUser();

        foreach (['admin.paths.index', 'admin.courses.index', 'admin.media.index'] as $route) {
            $this->actingAs($stranger)->get(route($route))->assertForbidden();
        }
    }
}
