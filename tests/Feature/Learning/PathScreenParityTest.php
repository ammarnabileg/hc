<?php

namespace Tests\Feature\Learning;

use App\Models\CelebrationConsumption;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * صفحة المسار (3.3 · 3.4) — الحقائق التي لا يجوز أن تفترق فيها عن صفحة التدريب.
 *
 * القاعدة المختبَرة أوّلًا: **حكمٌ واحد للإتاحة في كلّ الشاشات** (5). كانت صفحة
 * المسار تحسب الإتاحة بساعة **الخادم** بينما صفحة التدريب تحسبها بساعة المستخدم،
 * فتقول الأولى «مقفول — يفتح الاثنين» والثانية مفتوحةٌ ودروسها متاحة. والرسالة
 * تقول «بتوقيتك» فتصير الكذبة أشدّ ضررًا: يقرأ المتدرّب أنّ تدريبه مغلق فيؤجّل.
 */
class PathScreenParityTest extends LearningTestCase
{
    /** تدريب بنافذة يوميّة 05:00→07:00 — المثال الحرفيّ في الدستور 5 */
    private function dawnCourse(): Course
    {
        return $this->makeCourse(2, true, [
            'name_ar' => 'تدريب الفجر',
            'daily_open_at' => '05:00',
            'daily_close_at' => '07:00',
            'published_at' => Carbon::parse('2020-01-01'),
        ]);
    }

    private function path(): LearningPath
    {
        return LearningPath::where('slug', 'usus-al-amal-al-tatawui')->firstOrFail();
    }

    /** متدرّب بمنطقة زمنيّة يختارها بنفسه — والاختيار اليدويّ يعلو أيّ كشف (5) */
    private function traineeIn(string $timezone, string $name): User
    {
        $user = $this->trainee($name);
        $user->forceFill(['timezone' => $timezone])->save();

        return $user->fresh();
    }

    /**
     * هل تعترف الشاشة بأنّ التدريب مقفول؟ العلامة هي **نصّ سبب القفل** نفسه،
     * وهو ما تراه العين فعلًا — لا حالةٌ داخليّة قد تختلف عمّا يُعرَض.
     */
    private function saysClosed(string $html): bool
    {
        $marker = str_replace(
            [':from', ':to'],
            ['05:00', '07:00'],
            (string) setting('learning.lock.outside_daily_reason'),
        );

        return str_contains($html, $marker);
    }

    public function test_the_path_page_gives_the_same_availability_verdict_as_the_course_page(): void
    {
        $course = $this->dawnCourse();
        $path = $this->path();
        $this->attachToPath($course, $path);

        // 03:00 UTC = 06:00 بالقاهرة (داخل النافذة) و16:00 بميدواي أمس (خارجها)
        Carbon::setTestNow(Carbon::parse('2026-07-15 03:00:00', 'UTC'));

        $verdicts = [];

        foreach (['Africa/Cairo' => 'متدرّب القاهرة', 'Pacific/Midway' => 'متدرّب ميدواي'] as $timezone => $name) {
            $user = $this->traineeIn($timezone, $name);
            $this->enroll($user, $course);

            $onCourse = $this->saysClosed(
                $this->actingAs($user)->get(route('learning.course', $course))->assertOk()->getContent(),
            );

            $onPath = $this->saysClosed(
                $this->actingAs($user)->get(route('learning.path', $path))->assertOk()->getContent(),
            );

            $this->assertSame(
                $onCourse,
                $onPath,
                "صفحة المسار وصفحة التدريب لازم تقولا نفس الحكم لمستخدم بتوقيت {$timezone}.",
            );

            $verdicts[$timezone] = $onPath;
        }

        // وإلّا لكان الاتّفاق صدفةً: الحكم لازم يختلف باختلاف ساعة المستخدم لا يثبت
        $this->assertFalse($verdicts['Africa/Cairo'], 'التدريب مفتوح الساعة 6 صباحًا بتوقيت القاهرة.');
        $this->assertTrue($verdicts['Pacific/Midway'], 'التدريب مقفول الساعة 4 عصرًا بتوقيت ميدواي.');

        Carbon::setTestNow();
    }

    /**
     * ⭐ إكمال درس **يُطلِق احتفالًا** (2.14 · 4.1 · 2.9-6).
     *
     * كانت `celebration_events` مضبوطةً ومفعَّلة ولا `fire()` في مجال التعلّم
     * كلّه، فيسقط كونفيتي 4.1 وأنيميشن Level Up — أي كلّ لحظات الذروة.
     */
    public function test_completing_a_lesson_fires_a_celebration(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3);
        $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $this->actingAs($user)
            ->post(route('learning.lesson.complete', [$course, $lesson]))
            ->assertRedirect()
            ->assertSessionHas('celebration');

        $consumed = CelebrationConsumption::query()
            ->where('user_id', $user->id)
            ->join('celebration_events', 'celebration_events.id', '=', 'celebration_consumptions.celebration_event_id')
            ->pluck('celebration_events.key')
            ->all();

        $this->assertContains('lesson.completed', $consumed, 'إكمال الدرس لازم يستهلك حدث lesson.completed.');
    }

    /** والاحتفال لا يتكرّر بإعادة الطلب — الاستهلاك مسجَّل في الخادم (2.14) */
    public function test_the_lesson_celebration_is_consumed_once_only(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3);
        $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $this->actingAs($user)->post(route('learning.lesson.complete', [$course, $lesson]));
        $this->actingAs($user)
            ->post(route('learning.lesson.complete', [$course, $lesson]))
            ->assertSessionMissing('celebration');

        $this->assertSame(1, DB::table('celebration_consumptions')
            ->where('celebration_consumptions.user_id', $user->id)
            ->join('celebration_events', 'celebration_events.id', '=', 'celebration_consumptions.celebration_event_id')
            ->where('celebration_events.key', 'lesson.completed')
            ->count());
    }

    /** ⭐ لافتة التهنئة **عند** نصّ التدريب لا طوال نصفه الثاني (3.4-19) */
    public function test_the_congratulation_banner_shows_at_the_middle_of_the_course_only(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 4);
        $this->enroll($user, $course);
        $lessons = $this->lessonsOf($course);
        $title = str_replace(':name', $user->shortName(1), (string) setting('learning.course.half_banner_title'));

        // قبل النصّ: لا لافتة
        $this->actingAs($user)->get(route('learning.course', $course))->assertOk()->assertDontSee($title);

        $this->actingAs($user)->post(route('learning.lesson.complete', [$course, $lessons[0]]));
        $this->actingAs($user)->post(route('learning.lesson.complete', [$course, $lessons[1]]));

        // درسان من أربعة = نصّ الطريق بالضبط
        $this->actingAs($user)->get(route('learning.course', $course))->assertOk()->assertSee($title, false);

        // ومع الدرس التالي تختفي — التهنئة لحظةٌ لا لافتةٌ دائمة
        $this->actingAs($user)->post(route('learning.lesson.complete', [$course, $lessons[2]]));
        $this->actingAs($user)->get(route('learning.course', $course))->assertOk()->assertDontSee($title, false);
    }
}
