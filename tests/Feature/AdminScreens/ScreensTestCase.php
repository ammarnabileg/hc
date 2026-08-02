<?php

namespace Tests\Feature\AdminScreens;

use App\Models\Course;
use App\Models\Entity;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Referral;
use App\Models\Role;
use App\Models\Section;
use App\Models\Track;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\AdminScreens24DemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أساس اختبارات شاشات القسم 24 الناقصة.
 *
 * البيانات تُبنى هنا بيد الاختبار لا من سيدر مجال آخر — فاختبارٌ يعتمد على
 * بيانات غيره ينكسر بتغييرٍ لا علاقة له به.
 */
abstract class ScreensTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(RoleSeeder::class);

        // السيدر ينسب جدولاته لأوّل مستخدم — فلا بدّ من واحدٍ قبله كما في التشغيل الحقيقيّ
        $this->makeUser('حساب النظام');

        $this->seed(AdminScreens24DemoSeeder::class);
    }

    /** مالك المنصّة — يعلو الجميع ويملك المجموعة المحميّة (12.2.1) */
    protected function owner(): User
    {
        $user = $this->makeUser('مالك المنصّة');
        $user->assignRole(Role::query()->where('key', config('access.owner_role'))->firstOrFail());

        app(AccessEngine::class)->forget($user);

        return $user;
    }

    /** أدمن بصلاحيّات محدّدة — وبلا أيّ صلاحيّة ماليّة */
    protected function admin(array $permissionKeys, string $name = 'أدمن'): User
    {
        $user = $this->makeUser($name);
        $this->grant($user, $permissionKeys);

        return $user;
    }

    protected function grant(User $user, array $permissionKeys): void
    {
        foreach ($permissionKeys as $key) {
            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $this->permission($key)->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);
    }

    protected function permission(string $key): Permission
    {
        [$resource, $action] = array_pad(explode('.', $key, 2), 2, 'view');

        return Permission::firstOrCreate(['key' => $key], [
            'resource' => $resource,
            'action' => $action,
            'group' => 'اختبار',
            'label_ar' => $key,
            'allowed_scopes' => ['ALL'],
        ]);
    }

    protected function makeUser(string $name = 'مستخدم اختبار', string $status = 'active'): User
    {
        return User::create([
            'name' => $name,
            'email' => Str::lower(Str::random(12)).'@test.local',
            'password' => 'secret-password',
            'code' => Str::upper(Str::random(8)),
            'status' => $status,
        ]);
    }

    // ------------------------------------------------------------ بيانات البنك

    protected function makeLesson(string $courseName = 'تدريب الاختبار'): Lesson
    {
        $course = Course::create([
            'slug' => Str::slug($courseName).'-'.Str::random(5),
            'name_ar' => $courseName,
            'status' => 'published',
        ]);

        $section = Section::create([
            'course_id' => $course->id,
            'title_ar' => 'سيكشن أوّل',
            'sort_order' => 1,
        ]);

        return Lesson::create([
            'section_id' => $section->id,
            'title_ar' => 'درس أوّل',
            'type' => 'video',
            'sort_order' => 1,
        ]);
    }

    protected function makeQuestion(Lesson $lesson, array $attributes = []): LessonQuestion
    {
        return LessonQuestion::create($attributes + [
            'lesson_id' => $lesson->id,
            'type' => 'choice',
            'prompt' => 'سؤال اختبار '.Str::random(4),
            'options' => ['أ', 'ب'],
            'correct_answer' => 'أ',
            'is_general' => true,
            'is_active' => true,
            'difficulty' => 'medium',
        ]);
    }

    protected function makeExam(Course $course): Exam
    {
        return Exam::create([
            'examable_type' => $course->getMorphClass(),
            'examable_id' => $course->id,
            'title_ar' => 'امتحان '.$course->name_ar,
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------ بيانات الدعوات

    protected function makeReferral(User $referrer, ?User $referred = null): Referral
    {
        return Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referred?->id,
            'code' => $referrer->code,
            'commission_percent' => 7,
            'commission_earned' => 120.50,
            'payout_status' => 'pending',
        ]);
    }

    // ------------------------------------------------------------ بيانات الاجتماعات

    protected function makeEntity(): Entity
    {
        return Entity::create([
            'track_id' => Track::query()->where('key', 'department')->value('id'),
            'name_ar' => 'قسم '.Str::random(4),
            'status' => 'active',
        ]);
    }

    protected function makeMeeting(User $owner, ?Entity $entity = null, array $attributes = []): Meeting
    {
        $entity ??= $this->makeEntity();

        Membership::firstOrCreate([
            'user_id' => $owner->id,
            'entity_id' => $entity->id,
        ], [
            'position_id' => Position::query()->value('id'),
            'is_primary' => true,
            'started_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        return Meeting::create($attributes + [
            'title' => 'اجتماع الاختبار',
            'entity_id' => $entity->id,
            'audience' => 'entity',
            'owner_id' => $owner->id,
            'scheduled_at' => now()->subHours(2),
            'status' => 'scheduled',
        ]);
    }
}
