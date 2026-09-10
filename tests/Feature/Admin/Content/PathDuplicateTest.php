<?php

namespace Tests\Feature\Admin\Content;

use App\Models\CourseLearningPath;
use App\Models\LearningPath;
use App\Models\Permission;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **تكرار/نسخ (Duplicate) لمسار** (12.4-هـ).
 *
 * النصّ الحاكم حرفيًّا (12.4-هـ — مزايا معتمدة على إدارة التدريب):
 *  > «**تكرار/نسخ (Duplicate)** لتدريب · سيكشن · درس · **مسار** كقالب جاهز.»
 *
 * والمرصود: **التدريب** مبنيّ (`courses.duplicate`) و**السيكشن** و**الدرس**
 * مبنيّان، و**المسار بلا نظير أصلًا** — لا دالّة في `PathCourseService`، ولا
 * مسار راوتينج، ولا زرّ في `paths.blade.php`. فالثالث/الرابع المنصوص لم يكن
 * موجودًا. وبنفس قياس تكرار التدريب المحروس بـ`courses.create`، فالمسار
 * يُحرَس بـ`paths.create` — لأنّها تُنشئ مسارًا جديدًا فعلًا (12.2.2).
 *
 * والطفرات التي يمسكها هذا الملفّ:
 *  - نسخُ المسار بلا تدريباته ⟵ يسقط `the_copy_carries_courses_as_a_draft`.
 *  - فتحُ المسار [الراوت] لمن لا يملك `paths.create` ⟵ يسقط `the_route_is_guarded_by_paths_create`.
 *  - عرضُ الزرّ لمن لا يملك المفتاح ⟵ يسقط `the_button_is_hidden_not_disabled`.
 */
class PathDuplicateTest extends AdminContentTestCase
{
    /** ⭐ النسخة **قالبٌ جاهز**: مسودّة بعنوانٍ مميَّز وتدريباتها منسوخة معها. */
    public function test_the_copy_carries_courses_as_a_draft(): void
    {
        $path = LearningPath::query()->where('status', 'published')->firstOrFail();
        $sourceCourseIds = CourseLearningPath::query()
            ->where('learning_path_id', $path->id)
            ->orderBy('sort_order')
            ->pluck('course_id');

        $this->assertNotEmpty($sourceCourseIds, 'شرط الاختبار: لازم المسار يبقى له تدريبات أصلًا');

        $this->actingAs($this->admin())
            ->post(route('admin.paths.duplicate', $path))
            ->assertRedirect(route('admin.paths.index'));

        $copy = LearningPath::query()->where('id', '!=', $path->id)
            ->where('name_ar', 'like', $path->name_ar.'%')
            ->latest('id')->firstOrFail();

        $this->assertNotSame($path->id, $copy->id);
        $this->assertSame(
            $path->name_ar.setting('paths.duplicate.suffix'),
            $copy->name_ar,
            'اسم النسخة بلا لاحقة — فالمساران يلتبسان في القائمة.',
        );
        $this->assertSame('draft', $copy->status,
            'النسخة ليست مسودّة — والتكرار المعتمَد دائمًا مسودّة تنتظر مراجعة الأدمن (12.4-هـ).');
        $this->assertNull($copy->published_at);
        $this->assertNotSame($path->slug, $copy->slug);

        $copyCourseIds = CourseLearningPath::query()
            ->where('learning_path_id', $copy->id)
            ->orderBy('sort_order')
            ->pluck('course_id');

        $this->assertSame($sourceCourseIds->all(), $copyCourseIds->all(),
            'علاقات المسار بتدريباته لم تُنسَخ — فالقالب يحتاج بناءً من جديد.');

        // ⛔ ولا يُنسَخ الأصل نفسه: يبقى بتدريباته كما هي
        $this->assertSame($sourceCourseIds->all(), CourseLearningPath::query()
            ->where('learning_path_id', $path->id)->orderBy('sort_order')->pluck('course_id')->all());
    }

    /** ⭐ المسار محروسٌ بمفتاحٍ **منصوص في 12.2.2** لا بمفتاحٍ مخترَع. */
    public function test_the_route_is_guarded_by_paths_create(): void
    {
        $path = LearningPath::query()->firstOrFail();

        /*
         | المفتاح يُقرَأ من **مصدر المصفوفة نفسه** لا من جدولٍ زرعه الاختبار:
         | 12.2.2 هو الحاكم، و`database/data/permissions.json` صورتُه في الكود.
         | فلو حرس المسارَ مفتاحٌ مخترَع لسقط هنا قبل أيّ شيء.
         */
        $keys = collect(json_decode((string) file_get_contents(database_path('data/permissions.json')), true))
            ->pluck('key');

        $this->assertTrue($keys->contains('paths.create'),
            '`paths.create` غير منصوصٍ في مصفوفة 12.2.2 — والمفتاح لا يُخترَع.');
        $this->assertFalse($keys->contains('paths.duplicate'),
            'ظهر مفتاح `paths.duplicate` — وهو اختراعٌ لا تنصّ عليه 12.2.2.');

        /*
         | ⭐ والمفتاح الذي **يحرس المسار فعلًا** يُقرَأ من المسار نفسه: مفتاحٌ
         |    مخترَعٌ يمنع الجميع فيبدو «آمنًا»، فلا يمسكه اختبار الوصول وحده.
         */
        $guards = collect(app('router')->getRoutes()->getByName('admin.paths.duplicate')?->gatherMiddleware() ?? [])
            ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'))
            ->values();

        $this->assertSame(['permission:paths.create'], $guards->all(),
            'المسار محروسٌ بمفتاحٍ غير `paths.create` — والمفتاح لا يُخترَع خارج 12.2.2.');

        $before = LearningPath::query()->count();

        $this->actingAs($this->userWithout())
            ->post(route('admin.paths.duplicate', $path))
            ->assertForbidden();

        $this->assertSame($before, LearningPath::query()->count(),
            'نسخةٌ وُلِدت لمن لا يملك المفتاح — والقرار على الخادم لا في القالب (12.2.1).');
    }

    /** ⭐ و**المحظور يُخفى لا يُعطَّل** (2.15-أ-7). */
    public function test_the_button_is_hidden_not_disabled(): void
    {
        $path = LearningPath::query()->firstOrFail();

        $withKey = $this->actingAs($this->admin())
            ->get(route('admin.paths.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.paths.duplicate', $path), $withKey,
            'رابط تكرار المسار غائبٌ عمّن يملك المفتاح — والبند ينصّ عليه (12.4-هـ).');

        // ⛔ ومن لا يملك المفتاح **لا يراه أصلًا** — لا يراه معطَّلًا (2.15-أ-7)
        $withoutKey = $this->actingAs($this->userWithout())
            ->get(route('admin.paths.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('admin.paths.duplicate', $path), $withoutKey,
            'الرابط ظاهرٌ لمن لا يملك المفتاح — والمحظور يُخفى لا يُعطَّل (2.15-أ-7).');
    }

    // ------------------------------------------------------------------ أدوات

    /** مستخدمٌ يصل لوحة المسارات ولا يملك `paths.create` — فالحصر على المفتاح لا على الباب */
    private function userWithout(): User
    {
        $user = $this->makeUser(['name' => 'محرّر بلا مفتاح']);

        foreach (['paths.list', 'paths.view'] as $key) {
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
