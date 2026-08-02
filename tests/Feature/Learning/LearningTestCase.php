<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\LearningDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أساس اختبارات مجال التعلّم: بذرة الإعدادات + متدرّب بصلاحيّة enrollments.view
 * بنطاق SELF — وهي بالضبط ما يمنحه دور المتدرّب في RolePermissionSeeder.
 */
abstract class LearningTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // العملات لازمة: إتمام الدرس صار يمنح XP وتذاكر في دفتر المحفظة (7 · 7.1)
        $this->seed(CoreSeeder::class);
        $this->seed(LearningDemoSeeder::class);
    }

    /** صلاحيّات دور المتدرّب في هذا المجال — بنفس نطاق SELF الذي يمنحه RolePermissionSeeder */
    protected const TRAINEE_PERMISSIONS = [
        'enrollments.view',
        'lesson_quiz.view',
        'video_comments.view', 'video_comments.create', 'video_comments.edit', 'video_comments.delete',
        'course_notes.view', 'course_notes.create', 'course_notes.edit', 'course_notes.delete', 'course_notes.export',
    ];

    /** صلاحيّات الإشراف على التعليقات (3.1) — بنطاق ALL كأدمن المحتوى */
    protected const MODERATOR_PERMISSIONS = [
        'video_comments.archive', 'video_comments.restore', 'video_comments.delete',
    ];

    protected function trainee(string $name = 'متدرّب'): User
    {
        return $this->userWithRole($name, 'trainee', 'متدرّب', self::TRAINEE_PERMISSIONS, 'SELF');
    }

    /** مشرف تعليقات: يملك الإخفاء والإظهار والحذف على تعليقات الجميع */
    protected function moderator(string $name = 'مشرف'): User
    {
        return $this->userWithRole(
            $name,
            'content_admin',
            'أدمن المحتوى',
            array_merge(self::TRAINEE_PERMISSIONS, self::MODERATOR_PERMISSIONS),
            'ALL',
        );
    }

    private function userWithRole(string $name, string $roleKey, string $roleLabel, array $permissions, string $scope): User
    {
        $user = User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);

        $role = Role::firstOrCreate(['key' => $roleKey], [
            'name_ar' => $roleLabel,
            'layer' => 'user',
        ]);

        foreach ($permissions as $key) {
            [$resource, $action] = explode('.', $key, 2);

            $permission = Permission::firstOrCreate(['key' => $key], [
                'resource' => $resource,
                'action' => $action,
                'group' => 'التعلّم',
                'label_ar' => $key,
                'allowed_scopes' => ['SELF', 'TEAM', 'ENTITY', 'ALL'],
            ]);

            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'scope' => $scope,
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('role_user')->insert([
            'role_id' => $role->id,
            'user_id' => $user->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** تدريب بسيط بسيكشن واحد وعدد دروس — لعزل الاختبار عن بيانات البذرة */
    protected function makeCourse(int $lessons = 3, bool $forcedOrder = true, array $overrides = []): Course
    {
        $course = Course::create(array_merge([
            'slug' => 'c-'.str()->random(8),
            'name_ar' => 'تدريب اختباريّ',
            'xp_before_half' => 40,
            'xp_after_half' => 20,
            'forced_order' => $forcedOrder,
            'status' => 'published',
            'published_at' => Carbon::now()->subDay(),
        ], $overrides));

        $section = Section::create([
            'course_id' => $course->id,
            'title_ar' => 'القسم الأوّل',
            'sort_order' => 1,
        ]);

        for ($i = 1; $i <= $lessons; $i++) {
            Lesson::create([
                'section_id' => $section->id,
                'title_ar' => 'الدرس '.$i,
                'type' => 'document',
                'content' => 'محتوى الدرس '.$i,
                'duration_minutes' => 5,
                'sort_order' => $i,
            ]);
        }

        return $course;
    }

    protected function enroll(User $user, Course $course, ?Carbon $startedAt = null, ?Carbon $deadlineAt = null): Enrollment
    {
        return Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'started_at' => $startedAt ?? Carbon::now()->subDays(2),
            'deadline_at' => $deadlineAt ?? Carbon::now()->addDays(8),
            'status' => 'active',
        ]);
    }

    /** @return Collection<int, Lesson> */
    protected function lessonsOf(Course $course)
    {
        return Lesson::query()
            ->whereIn('section_id', Section::where('course_id', $course->id)->pluck('id'))
            ->orderBy('sort_order')
            ->get();
    }

    protected function attachToPath(Course $course, LearningPath $path, int $order = 1): void
    {
        DB::table('course_learning_path')->insert([
            'course_id' => $course->id,
            'learning_path_id' => $path->id,
            'sort_order' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
