<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Course;
use App\Models\CourseLearningPath;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\MediaItem;
use App\Models\Permission;
use App\Models\Section;
use App\Models\User;
use App\Services\Admin\Content\MediaLibrary;
use App\Support\Access\AccessEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    /**
     * ⭐ مسوّدة التحرير المعلّقة **ترجع إلى حقول الفورم** (12.4-ب).
     *
     * الحفظ التلقائيّ على تدريبٍ حيّ لا يمسّ المنشور — ولو لم يرجع عمله إلى
     * الحقول لضاع لحظة ضغطه «حفظ»، لأنّ الفورم كان سيرسل قيم النسخة المنشورة
     * فوقه: فيصير «الحفظ التلقائيّ» وعدًا بلا وفاء.
     */
    public function test_a_pending_draft_comes_back_into_the_edit_form_and_saves(): void
    {
        $course = Course::query()->firstOrFail();
        $course->update(['status' => 'published', 'published_at' => now()]);

        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.courses.autosave', $course), [
            'name_ar' => 'اسم تحت التجربة',
        ])->assertOk();

        // يرجع في الفورم — فالمحرّر يراه ويكمّل عليه
        $this->actingAs($admin)
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->assertSee('اسم تحت التجربة', false);

        // وما زال المنشور بحاله حتى يضغط «حفظ»
        $this->assertSame('published', $course->refresh()->status);
    }

    /** و«تجاهل المسودّة» يشيلها وحدها — المنشور وحالته لا يُمَسّان (12.4-ب). */
    public function test_discarding_the_draft_leaves_the_published_version_untouched(): void
    {
        $course = Course::query()->firstOrFail();
        $course->update(['status' => 'published', 'published_at' => now()]);
        $original = $course->name_ar;

        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.courses.autosave', $course), ['name_ar' => 'تجربة تتشال']);
        $this->assertNotEmpty($course->refresh()->draft_payload);

        $this->actingAs($admin)
            ->delete(route('admin.courses.draft.discard', $course))
            ->assertRedirect();

        $course->refresh();

        $this->assertNull($course->draft_payload);
        $this->assertSame($original, $course->name_ar);
        $this->assertSame('published', $course->status);
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

    /**
     * ⭐ عمود «الغلاف» في جدول التدريبات: صورةٌ لمن يملك `cover_path`، وشرطةٌ لمن
     * لا يملكه — العمود كان غائبًا رغم أنّ الحقل موجودٌ ومُحرَّرٌ فعلًا (12.4-ب).
     */
    public function test_courses_index_table_shows_cover_thumbnail_or_dash(): void
    {
        $withCover = Course::query()->firstOrFail();
        $withCover->update(['cover_path' => 'media/course-cover-test.jpg']);

        $withoutCover = Course::query()->where('id', '!=', $withCover->id)->firstOrFail();
        $withoutCover->update(['cover_path' => null]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.courses.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            Storage::url('media/course-cover-test.jpg'),
            $html,
            'التدريب صاحب الغلاف لازم يظهر بصورةٍ مصغّرة في عمود الجدول (12.4-ب)',
        );

        $row = $this->firstTableRowContaining($html, $withoutCover->name_ar);
        $this->assertStringNotContainsString('<img', $row, 'التدريب بلا غلافٍ ما يظهرش له img');
        $this->assertStringContainsString('—', $row, 'التدريب بلا غلافٍ يظهر له شرطة بدل الصورة');
    }

    /**
     * ⭐ عمود «صورة مصغّرة» في جدول المسارات: نفس القاعدة — صورةٌ أو شرطة (12.4-أ).
     */
    public function test_paths_index_table_shows_cover_thumbnail_or_dash(): void
    {
        $withCover = LearningPath::query()->firstOrFail();
        $withCover->update(['cover_path' => 'media/path-cover-test.jpg']);

        $withoutCover = LearningPath::query()->where('id', '!=', $withCover->id)->firstOrFail();
        $withoutCover->update(['cover_path' => null]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.paths.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            Storage::url('media/path-cover-test.jpg'),
            $html,
            'المسار صاحب الغلاف لازم يظهر بصورةٍ مصغّرة في عمود الجدول (12.4-أ)',
        );

        $row = $this->firstTableRowContaining($html, $withoutCover->name_ar);
        $this->assertStringNotContainsString('<img', $row, 'المسار بلا غلافٍ ما يظهرش له img');
        $this->assertStringContainsString('—', $row, 'المسار بلا غلافٍ يظهر له شرطة بدل الصورة');
    }

    /** يستخرج أوّل صفّ جدول (`<tr>…</tr>`) يحوي هذا النصّ — لعزل عمود صفٍّ بعينه عن باقي الصفحة. */
    private function firstTableRowContaining(string $html, string $needle): string
    {
        $needlePos = mb_strpos($html, $needle);
        $this->assertNotFalse($needlePos, 'النصّ المطلوب لازم يظهر في الصفحة أصلًا');

        $rowStart = mb_strrpos(mb_substr($html, 0, $needlePos), '<tr');
        $this->assertNotFalse($rowStart, 'لازم يكون النصّ داخل صفّ جدول');

        $rowEndOffset = mb_strpos($html, '</tr>', $needlePos);
        $this->assertNotFalse($rowEndOffset, 'لازم يُقفَل صفّ الجدول بعد النصّ');

        return mb_substr($html, $rowStart, $rowEndOffset - $rowStart);
    }

    /**
     * ⭐ الحجب والمجّانيّة 🔒 (12.2.2: paywall.edit/manage — مالك المنصّة فقط).
     * محرِّر محتوى عاديّ يملك `courses.edit` يقدر يعدّل اسم التدريب ووصفه، لكن
     * السعر النهائيّ وقاعدة المجّانيّة **يتجاهلهما الخادم** مهما أرسل — لا
     * يكفي إخفاء الحقل في الواجهة، فالحارس على الخادم لا العميل.
     */
    public function test_only_the_platform_owner_can_change_pricing_fields(): void
    {
        $course = Course::query()->where('slug', 'shared-communication')->firstOrFail();
        $originalPrice = (float) $course->price_coins;
        $editor = $this->editorWith('courses.edit');

        $this->actingAs($editor)->put(route('admin.courses.update', $course), [
            'name_ar' => 'اسم معدَّل من محرِّر عاديّ',
            'status' => $course->status,
            'is_free' => true,
            'price_coins' => 999999,
            'offer_price_coins' => 1,
            'free_first_time' => true,
        ])->assertRedirect();

        $course->refresh();

        $this->assertSame('اسم معدَّل من محرِّر عاديّ', $course->name_ar, 'الحقول العاديّة يعدّلها محرِّر المحتوى بلا مشكلة');
        $this->assertSame($originalPrice, (float) $course->price_coins, 'السعر لازم يفضل زيّ ما هو — محرِّر عاديّ مالوش صلاحيّة تغييره');
        $this->assertFalse((bool) $course->free_first_time, 'قاعدة المجّانيّة أوّل مرّة معزولة لمالك المنصّة فقط');

        $owner = $this->admin();

        $this->actingAs($owner)->put(route('admin.courses.update', $course), [
            'name_ar' => $course->name_ar,
            'status' => $course->status,
            'is_free' => true,
            'price_coins' => 777,
        ])->assertRedirect();

        $this->assertSame(777.0, (float) $course->refresh()->price_coins, 'مالك المنصّة يقدر يغيّر السعر فعليًّا');
    }

    private function editorWith(string ...$keys): User
    {
        $user = $this->makeUser(['name' => 'محرِّر محتوى عاديّ']);

        foreach ($keys as $key) {
            $permission = Permission::query()->where('key', $key)->first();

            if (! $permission) {
                [$resource, $action] = explode('.', $key);
                $permission = Permission::create([
                    'key' => $key,
                    'resource' => $resource,
                    'action' => $action,
                    'group' => 'التعلّم والمحتوى والشهادات',
                    'label_ar' => $key,
                    'allowed_scopes' => ['ALL'],
                ]);
            }

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget();

        return $user->fresh();
    }
}
