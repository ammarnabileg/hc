<?php

namespace Tests\Feature\Growth;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\GrowthDemoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/** أساس اختبارات مجال النموّ (21.1 · 21.2 · 21.3) */
abstract class GrowthTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    protected function seedGrowth(): void
    {
        $this->seed(GrowthDemoSeeder::class);
        Cache::forget('settings');
    }

    protected function trainee(array $attributes = []): User
    {
        $user = User::create([
            'name' => 'مريم حسن',
            'email' => Str::random(8).'@test.local',
            'password' => 'secret-password',
            'code' => 'U'.Str::upper(Str::random(7)),
            'status' => 'active',
            'activated_at' => now(),
            ...$attributes,
        ]);

        $user->assignRole('trainee');

        return $user->fresh();
    }

    protected function setSetting(string $key, string $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => 'growth',
            'label_ar' => $key,
            'type' => $type,
            'default_value' => $value,
            'value' => $value,
        ]);

        Cache::forget('settings');
    }

    /** تدريب منشور بثلاثة دروس — أوّلها ضمن المعاينة */
    protected function makeCourse(int $previewLessons = 1): Course
    {
        $course = Course::create([
            'slug' => 'course-'.Str::lower(Str::random(6)),
            'name_ar' => 'أساسيّات البرمجة',
            'description_ar' => 'تدريب تجريبيّ للاختبار.',
            'status' => 'published',
            'published_at' => now(),
            'is_indexable' => true,
            'free_preview_lessons' => $previewLessons,
        ]);

        $section = Section::create([
            'course_id' => $course->id,
            'title_ar' => 'المقدّمة',
            'sort_order' => 1,
        ]);

        foreach (range(1, 3) as $i) {
            Lesson::create([
                'section_id' => $section->id,
                'title_ar' => 'الدرس '.$i,
                'type' => 'document',
                'content' => '<p>محتوى الدرس '.$i.'</p>',
                'sort_order' => $i,
            ]);
        }

        return $course->fresh();
    }
}
