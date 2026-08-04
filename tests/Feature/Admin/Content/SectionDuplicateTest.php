<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\LessonQuestion;
use App\Models\MediaItem;
use App\Models\Permission;
use App\Models\Section;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **تكرار/نسخ (Duplicate) لسيكشن** (12.4-هـ).
 *
 * النصّ الحاكم حرفيًّا (12.4-هـ — مزايا معتمدة على إدارة التدريب):
 *  > «**تكرار/نسخ (Duplicate)** لتدريب · سيكشن · درس **كقالب جاهز**.»
 *
 * والمرصود: **التدريب** مبنيّ (`courses.duplicate`) و**الدرس** مبنيّ
 * (`lessons.duplicate`)، و**السيكشن بلا مسار أصلًا** — فالثالث المنصوص لم يكن
 * موجودًا. و«كقالب جاهز» تعني نسخةً **كاملة**: دروس السيكشن بأسئلتها ومرفقاتها،
 * وإلّا فهو هيكلٌ فارغ يُعاد ملؤه بيدٍ.
 *
 * **والمفتاح `sections.create` منصوصٌ في 12.2.2** («إضافة سيكشن جديد داخل
 * التدريب») — ولا مفتاح `duplicate` في المصفوفة، ولا يُخترَع مفتاح. وهو نفس
 * قياس تكرار التدريب المحروس بـ`courses.create`.
 *
 * والطفرات التي يمسكها هذا الملفّ:
 *  - نسخُ السيكشن بلا دروسه ⟵ يسقط `the_copy_carries_lessons_questions_and_attachments`.
 *  - فتحُ المسار لمن لا يملك `sections.create` ⟵ يسقط `the_route_is_guarded_by_sections_create`.
 *  - عرضُ الزرّ لمن لا يملك المفتاح ⟵ يسقط `the_button_is_hidden_not_disabled`.
 *  - كسرُ الذرّيّة (نسخةٌ ناقصة تنجو) ⟵ يسقط `the_copy_lands_after_the_last_section`.
 */
class SectionDuplicateTest extends AdminContentTestCase
{
    /** ⭐ النسخة **قالبٌ جاهز**: الدروس والأسئلة والمرفقات معها. */
    public function test_the_copy_carries_lessons_questions_and_attachments(): void
    {
        [$course, $section] = $this->sectionWithContent();

        $this->actingAs($this->admin())
            ->post(route('admin.sections.duplicate', $section))
            ->assertRedirect(route('admin.courses.edit', $course));

        $copy = Section::query()->where('course_id', $course->id)->latest('id')->firstOrFail();

        $this->assertNotSame($section->id, $copy->id);
        $this->assertSame(
            $section->title_ar.setting('sections.duplicate.suffix'),
            $copy->title_ar,
            'اسم النسخة بلا لاحقة — فالقالبان يلتبسان في القائمة.',
        );

        $sourceLessons = Lesson::query()->where('section_id', $section->id)->orderBy('sort_order')->get();
        $copyLessons = Lesson::query()->where('section_id', $copy->id)->orderBy('sort_order')->get();

        $this->assertCount($sourceLessons->count(), $copyLessons,
            'النسخة بلا دروس — فهي هيكلٌ فارغ لا «قالبٌ جاهز» (12.4-هـ).');
        $this->assertSame($sourceLessons->pluck('title_ar')->all(), $copyLessons->pluck('title_ar')->all());

        $this->assertSame(
            LessonQuestion::query()->whereIn('lesson_id', $sourceLessons->pluck('id'))->count(),
            LessonQuestion::query()->whereIn('lesson_id', $copyLessons->pluck('id'))->count(),
            'أسئلة الدروس لم تُنسَخ — فالقالب يحتاج بناءً من جديد.',
        );

        $this->assertSame(
            LessonAttachment::query()->whereIn('lesson_id', $sourceLessons->pluck('id'))->count(),
            LessonAttachment::query()->whereIn('lesson_id', $copyLessons->pluck('id'))->count(),
            'مرفقات الدروس لم تُنسَخ.',
        );

        // ⛔ ولا يُنسَخ الأصل نفسه: الأصل يبقى كما هو بدروسه
        $this->assertCount(2, $sourceLessons->fresh());
    }

    /** ⭐ النسخة تقف **بعد** آخر سيكشن، فلا تدهس ترتيبًا قائمًا. */
    public function test_the_copy_lands_after_the_last_section(): void
    {
        [$course, $section] = $this->sectionWithContent();

        $last = Section::create([
            'course_id' => $course->id, 'title_ar' => 'السيكشن الأخير', 'sort_order' => 7,
        ]);

        $this->actingAs($this->admin())->post(route('admin.sections.duplicate', $section))->assertRedirect();

        $copy = Section::query()->where('course_id', $course->id)->latest('id')->firstOrFail();

        $this->assertGreaterThan((int) $last->sort_order, (int) $copy->sort_order,
            'النسخة نزلت فوق ترتيبٍ قائم — فترتيب السيكشنز يتشوّش عند كلّ تكرار.');
    }

    /** ⭐ المسار محروسٌ بمفتاحٍ **منصوص في 12.2.2** لا بمفتاحٍ مخترَع. */
    public function test_the_route_is_guarded_by_sections_create(): void
    {
        [, $section] = $this->sectionWithContent();

        /*
         | المفتاح يُقرَأ من **مصدر المصفوفة نفسه** لا من جدولٍ زرعه الاختبار:
         | 12.2.2 هو الحاكم، و`database/data/permissions.json` صورتُه في الكود.
         | فلو حرس المسارَ مفتاحٌ مخترَع لسقط هنا قبل أيّ شيء.
         */
        $keys = collect(json_decode((string) file_get_contents(database_path('data/permissions.json')), true))
            ->pluck('key');

        $this->assertTrue($keys->contains('sections.create'),
            '`sections.create` غير منصوصٍ في مصفوفة 12.2.2 — والمفتاح لا يُخترَع.');
        $this->assertFalse($keys->contains('sections.duplicate'),
            'ظهر مفتاح `sections.duplicate` — وهو اختراعٌ لا تنصّ عليه 12.2.2.');

        /*
         | ⭐ والمفتاح الذي **يحرس المسار فعلًا** يُقرَأ من المسار نفسه: مفتاحٌ
         |    مخترَعٌ يمنع الجميع فيبدو «آمنًا»، فلا يمسكه اختبار الوصول وحده.
         */
        $guards = collect(app('router')->getRoutes()->getByName('admin.sections.duplicate')?->gatherMiddleware() ?? [])
            ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'))
            ->values();

        $this->assertSame(['permission:sections.create'], $guards->all(),
            'المسار محروسٌ بمفتاحٍ غير `sections.create` — والمفتاح لا يُخترَع خارج 12.2.2.');

        $before = Section::query()->count();

        $this->actingAs($this->userWithout())
            ->post(route('admin.sections.duplicate', $section))
            ->assertForbidden();

        $this->assertSame($before, Section::query()->count(),
            'نسخةٌ وُلِدت لمن لا يملك المفتاح — والقرار على الخادم لا في القالب (12.2.1).');
    }

    /** ⭐ و**المحظور يُخفى لا يُعطَّل** (2.15-أ-7). */
    public function test_the_button_is_hidden_not_disabled(): void
    {
        [$course, $section] = $this->sectionWithContent();

        $withKey = $this->actingAs($this->admin())
            ->get(route('admin.courses.edit', $course))->assertOk()->getContent();

        $this->assertStringContainsString('section-duplicate-'.$section->id, $withKey,
            'زرّ تكرار السيكشن غائبٌ عمّن يملك المفتاح — والبند ينصّ عليه (12.4-هـ).');
        $this->assertStringContainsString(route('admin.sections.duplicate', $section), $withKey);

        // ⛔ ومن لا يملك المفتاح **لا يراه أصلًا** — لا يراه معطَّلًا (2.15-أ-7)
        $withoutKey = $this->actingAs($this->userWithout())
            ->get(route('admin.courses.edit', $course))->assertOk()->getContent();

        $this->assertStringNotContainsString('section-duplicate-'.$section->id, $withoutKey,
            'الزرّ ظاهرٌ لمن لا يملك المفتاح — والمحظور يُخفى لا يُعطَّل (2.15-أ-7).');
    }

    // ------------------------------------------------------------------ أدوات

    /** @return array{0: Course, 1: Section} */
    private function sectionWithContent(): array
    {
        $course = Course::query()->firstOrFail();

        $section = Section::create([
            'course_id' => $course->id,
            'title_ar' => 'قالب البداية',
            'sort_order' => 1,
        ]);

        $media = MediaItem::create([
            'name' => 'مرفق القالب', 'disk' => 'public', 'path' => 'media/template.pdf',
            'mime' => 'application/pdf', 'size' => 512, 'hash' => hash('sha256', 'template'),
        ]);

        foreach (['الدرس الأوّل', 'الدرس الثاني'] as $index => $title) {
            $lesson = Lesson::create([
                'section_id' => $section->id,
                'title_ar' => $title,
                'type' => 'document',
                'content' => 'نصّ الدرس',
                'sort_order' => $index + 1,
            ]);

            LessonQuestion::create([
                'lesson_id' => $lesson->id,
                'type' => 'otp',
                'prompt' => 'سؤال '.$title,
                'correct_answer' => '1234',
                'sort_order' => 1,
            ]);

            LessonAttachment::create([
                'lesson_id' => $lesson->id,
                'media_item_id' => $media->id,
                'sort_order' => 1,
            ]);
        }

        return [$course, $section->fresh()];
    }

    /** مستخدمٌ يصل لوحة التدريب ولا يملك `sections.create` — فالحصر على المفتاح لا على الباب */
    private function userWithout(): User
    {
        $user = $this->makeUser(['name' => 'محرّر بلا مفتاح']);

        foreach (['courses.list', 'courses.view', 'courses.edit', 'sections.view'] as $key) {
            [$resource, $action] = explode('.', $key);

            $permission = Permission::firstOrCreate(['key' => $key], [
                'resource' => $resource, 'action' => $action,
                'group' => 'التعلّم والمحتوى والشهادات', 'label_ar' => $resource,
                'allowed_scopes' => ['ALL'],
            ]);

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id, 'user_id' => $user->id, 'membership_id' => null,
                'scope' => 'ALL', 'effect' => 'allow', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);

        return $user->refresh();
    }
}
