<?php

namespace Tests\Feature\Exams;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\LearningPath;
use App\Models\Permission;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\ExamDemoSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * أرضيّة مشتركة لاختبارات مجال الامتحانات والشهادات:
 * العملات وأنواع الشهادات والاحتفالات من CoreSeeder، والإعدادات من سيدر المجال.
 */
abstract class ExamTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('settings');
        $this->seed(CoreSeeder::class);
        $this->seed(ExamDemoSeeder::class);

        // الصلاحيّتان اللتان تحرسان مسارات المجال (12.2.1)
        $this->permission('course_exam.view', 'course_exam');
        $this->permission('certificates.view', 'certificates');
    }

    protected function permission(string $key, string $resource): Permission
    {
        return Permission::firstOrCreate(['key' => $key], [
            'resource' => $resource,
            'action' => 'view',
            'group' => 'التعلّم والمحتوى والشهادات',
            'label_ar' => $key,
            'allowed_scopes' => ['SELF', 'ENTITY', 'ALL'],
        ]);
    }

    /** متدرّب مفعَّل يملك صلاحيّات مجاله بنطاق SELF */
    protected function trainee(string $name = 'سلمى عبد الرحمن'): User
    {
        $user = User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);

        foreach (['course_exam.view', 'certificates.view'] as $key) {
            $permission = Permission::where('key', $key)->first();

            $user->permissionOverrides()->attach($permission->id, [
                'scope' => 'SELF',
                'effect' => 'allow',
            ]);
        }

        app(AccessEngine::class)->forget($user);

        return $user;
    }

    /** امتحان تدريب بأسئلة معلومة الإجابات — ثلاثة أسئلة بأوزان متساوية */
    protected function courseExam(int $passScore = 70, int $durationMinutes = 30): Exam
    {
        $course = Course::create([
            'slug' => 'test-course-'.str()->random(6),
            'name_ar' => 'تدريب الاختبار',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $exam = Exam::create([
            'examable_type' => $course->getMorphClass(),
            'examable_id' => $course->id,
            'title_ar' => 'امتحان تدريب الاختبار',
            'duration_minutes' => $durationMinutes,
            'attempts_allowed' => 3,
            'retry_cooldown_hours' => 0,
            'pass_score' => $passScore,
            'price_coins' => 0,
            'is_active' => true,
        ]);

        $this->seedQuestions($exam);

        return $exam;
    }

    /** امتحان شهادة المسار — مدفوع بالكوينز (24.5) */
    protected function pathExam(float $price = 150, int $durationMinutes = 45): Exam
    {
        $path = LearningPath::create([
            'slug' => 'test-path-'.str()->random(6),
            'name_ar' => 'مسار الاختبار',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $exam = Exam::create([
            'examable_type' => $path->getMorphClass(),
            'examable_id' => $path->id,
            'title_ar' => 'امتحان شهادة مسار الاختبار',
            'duration_minutes' => $durationMinutes,
            'attempts_allowed' => 2,
            'retry_cooldown_hours' => 0,
            'pass_score' => 70,
            'price_coins' => $price,
            'is_active' => true,
        ]);

        $this->seedQuestions($exam);

        return $exam;
    }

    private function seedQuestions(Exam $exam): void
    {
        $rows = [
            ['choice', 'ما عاصمة مصر؟', ['القاهرة', 'الإسكندريّة', 'أسوان'], 'القاهرة'],
            ['text', 'اكتب كلمة «صحّ»', null, 'صحّ'],
            ['number', 'كام يوم في الأسبوع؟', null, '7'],
        ];

        foreach ($rows as $index => [$type, $prompt, $options, $correct]) {
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'type' => $type,
                'prompt' => $prompt,
                'options' => $options,
                'correct_answer' => $correct,
                'weight' => 1,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
