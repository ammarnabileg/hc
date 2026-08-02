<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\Setting;
use Database\Seeders\LearningDemoSeeder;
use Illuminate\Support\Facades\DB;

/**
 * بذرة المجال: إعدادات كاملة (2.13) + مسار وتدريبان بمحتوًى عربيّ واقعيّ (دليل البناء 7).
 */
class DemoSeederTest extends LearningTestCase
{
    public function test_seed_creates_one_path_two_courses_with_sections_lessons_and_questions(): void
    {
        $path = LearningPath::where('slug', 'usus-al-amal-al-tatawui')->firstOrFail();

        $this->assertSame(2, DB::table('course_learning_path')->where('learning_path_id', $path->id)->count());
        $this->assertSame(2, Course::whereIn('slug', ['maharat-al-tawasul', 'idarat-al-waqt'])->count());
        $this->assertGreaterThanOrEqual(6, Lesson::count());
        $this->assertGreaterThanOrEqual(1, LessonQuestion::where('type', 'otp')->count());
    }

    public function test_every_setting_of_the_domain_is_stored_with_a_default_value(): void
    {
        $settings = Setting::where('group', 'learning')->get();

        $this->assertGreaterThan(60, $settings->count());
        $this->assertTrue($settings->every(fn ($s) => $s->default_value !== null));
    }

    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $courses = Course::count();
        $lessons = Lesson::count();
        $settings = Setting::where('group', 'learning')->count();

        $this->seed(LearningDemoSeeder::class);

        $this->assertSame($courses, Course::count());
        $this->assertSame($lessons, Lesson::count());
        $this->assertSame($settings, Setting::where('group', 'learning')->count());
    }
}
