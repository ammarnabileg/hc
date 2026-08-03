<?php

namespace Tests\Feature\Learning;

use App\Models\Country;
use App\Models\Course;
use App\Models\CourseAvailabilityPeriod;
use App\Models\CourseNote;
use App\Models\Exam;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ **الإتاحة تحكم الملاحظات كما تحكم الدروس والامتحان** (الدستور 5 · 3.2 · 24.5).
 *
 * القسم 5 حرفيًّا: «**عدة فترات إتاحة للتدريب الواحد** … المتدرب يوصل للتدريب
 * فقط أثناء إحدى هذه الفترات» و«**أوقات تشغيل يومية لكل تدريب** … خارج الساعات
 * دي التدريب **مقفول** حتى لو فترة الإتاحة سارية».
 *
 * والقسم 3.2: «**Text Area واحد مشترك لكل دروس التدريب** — الملاحظات **موحّدة
 * على مستوى التدريب**» — فهي كتابةٌ **داخل** التدريب، وكانت تُقبَل والتدريب
 * مقفول: `POST /learning/courses/{slug}/notes` ⟵ 200 و«اتحفظ ✓».
 *
 * وهذا الملفّ يثبت **سقوط الحارس**: احذف `EnsureNotesWithinAvailability` من
 * `routes/parts/learning.php` وستسقط `…_is_refused_…` فورًا (200 بدل 403 وصفٌّ
 * في `course_notes`)، واحذف قراءة الإتاحة من `CredentialService::courseExam()`
 * وستسقط `…exam_block…`.
 */
class NotesAvailabilityGateTest extends LearningTestCase
{
    /** تدريب بنافذة يوميّة صباحيّة 5→7 ص — المثال الحرفيّ في الدستور 5 */
    private function dawnCourse(): Course
    {
        return $this->makeCourse(2, true, [
            'daily_open_at' => '05:00',
            'daily_close_at' => '07:00',
            'published_at' => Carbon::parse('2020-01-01'),
        ]);
    }

    private function egypt(): Country
    {
        return Country::updateOrCreate(['iso2' => 'EG'], [
            'name_ar' => 'مصر', 'name_en' => 'Egypt', 'timezone' => 'Africa/Cairo', 'is_active' => true,
        ]);
    }

    /** امتحان تدريبٍ نشط + إسقاط شرط التقدّم، فالمختبَر هنا الإتاحة لا النسبة */
    private function makeCourseExam(Course $course): Exam
    {
        Setting::updateOrCreate(['key' => 'learning.exam.unlock_percent'], ['type' => 'number', 'value' => '0']);
        Cache::forget('settings');

        return Exam::create([
            'examable_type' => $course->getMorphClass(),
            'examable_id' => $course->id,
            'title_ar' => 'امتحان التدريب',
            'duration_minutes' => 30,
            'attempts_allowed' => 3,
            'retry_cooldown_hours' => 0,
            'pass_score' => 70,
            'price_coins' => 0,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------- الحاجز على الكتابة

    public function test_writing_a_note_is_refused_while_the_course_is_outside_its_daily_window(): void
    {
        $user = $this->trainee();
        $user->forceFill(['country_id' => $this->egypt()->id])->save();

        $course = $this->dawnCourse();
        $this->enroll($user, $course);

        // 15:00 بالقاهرة — خارج نافذة 05:00→07:00
        Carbon::setTestNow(Carbon::parse('2026-07-15 13:00:00', 'UTC'));

        $this->actingAs($user)
            ->postJson(route('learning.course.notes.save', $course), ['body' => 'كتابة أثناء القفل'])
            ->assertStatus(403)
            ->assertJson(['saved' => false, 'locked' => true])
            ->assertJsonFragment(['message' => trim(
                setting('learning.notes.course_locked').' التدريب بيفتح يوميًّا من 05:00 إلى 07:00 بتوقيتك — يفتح الخميس 16 يوليو — 05:00'
            )]);

        $this->assertSame(
            0,
            CourseNote::where('user_id', $user->id)->where('course_id', $course->id)->count(),
            'ولا صفّ ملاحظة يُكتَب على تدريبٍ مقفول.',
        );
    }

    public function test_clearing_notes_is_refused_too_so_the_hole_is_not_left_open_on_the_other_verb(): void
    {
        $user = $this->trainee();
        $user->forceFill(['country_id' => $this->egypt()->id])->save();

        $course = $this->dawnCourse();
        $this->enroll($user, $course);

        Carbon::setTestNow(Carbon::parse('2026-07-15 03:00:00', 'UTC')); // 05:00 بالقاهرة — مفتوح
        $this->actingAs($user)->post(route('learning.course.notes.save', $course), ['body' => 'نصّ داخل النافذة']);

        Carbon::setTestNow(Carbon::parse('2026-07-15 13:00:00', 'UTC')); // 15:00 — مقفول
        $this->actingAs($user)
            ->deleteJson(route('learning.course.notes.clear', $course))
            ->assertStatus(403)
            ->assertJson(['locked' => true]);

        $this->assertDatabaseHas('course_notes', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'body' => 'نصّ داخل النافذة',
        ]);
    }

    /** الطبقة الثانية من القسم 5: خارج فترات الإتاحة ولو بلا نافذة يوميّة */
    public function test_writing_is_refused_outside_the_availability_periods_as_well(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(1);
        $this->enroll($user, $course);

        CourseAvailabilityPeriod::create([
            'course_id' => $course->id,
            'starts_on' => Carbon::now()->subMonths(6)->toDateString(),
            'ends_on' => Carbon::now()->subMonths(6)->addDays(7)->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('learning.course.notes.save', $course), ['body' => 'خارج كلّ الفترات'])
            ->assertStatus(403);

        $this->assertSame(0, CourseNote::where('course_id', $course->id)->count());
    }

    // ------------------------------------------- ولا يُقفَل الباب على مستحقّه

    public function test_inside_the_window_the_autosave_still_answers_with_the_saved_confirmation(): void
    {
        $user = $this->trainee();
        $user->forceFill(['country_id' => $this->egypt()->id])->save();

        $course = $this->dawnCourse();
        $this->enroll($user, $course);

        Carbon::setTestNow(Carbon::parse('2026-07-15 03:30:00', 'UTC')); // 05:30 بالقاهرة

        $this->actingAs($user)
            ->postJson(route('learning.course.notes.save', $course), ['body' => 'ملاحظة داخل النافذة'])
            ->assertOk()
            ->assertJson(['saved' => true, 'message' => setting('learning.notes.saved')]);
    }

    public function test_a_course_with_no_window_at_all_keeps_its_notes_open(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(1);
        $this->enroll($user, $course);

        $this->actingAs($user)
            ->postJson(route('learning.course.notes.save', $course), ['body' => 'بلا قيدٍ زمنيّ'])
            ->assertOk()
            ->assertJson(['saved' => true]);
    }

    /**
     * ⭐ والقراءة تمرّ ولو كان مقفولًا — قياسًا على استثناء «شاشة النتيجة» في
     * `EnsureExamWithinAvailability`: سجلٌّ لبيانات صاحبه لا يفتح بابًا ولا يكتب.
     */
    public function test_exporting_own_notes_stays_open_while_the_course_is_locked(): void
    {
        $user = $this->trainee();
        $user->forceFill(['country_id' => $this->egypt()->id])->save();

        $course = $this->dawnCourse();
        $this->enroll($user, $course);

        Carbon::setTestNow(Carbon::parse('2026-07-15 03:30:00', 'UTC'));
        $this->actingAs($user)->post(route('learning.course.notes.save', $course), ['body' => 'خلاصتي']);

        Carbon::setTestNow(Carbon::parse('2026-07-15 13:00:00', 'UTC'));
        $this->actingAs($user)
            ->get(route('learning.course.notes.export', $course))
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');
    }

    // ------------------------- بلوك الامتحان: ظاهرٌ بقفلٍ وسببٍ مكتوب (24.5)

    /**
     * 24.5 (صفحة التدريب): «**الدروس المقفولة تظهر بقفل وسببٍ مكتوب** … لا
     * مخفيّة. أسفلها **بلوك الامتحان النهائيّ بحالته وشرط فتحه**».
     *
     * فالبلوك يبقى ظاهرًا، ولا يحمل زرّ الدخول حين يردّه الخادم أصلًا.
     */
    public function test_the_exam_block_shows_a_lock_and_a_written_reason_instead_of_a_button_that_lies(): void
    {
        $user = $this->trainee();
        $user->forceFill(['country_id' => $this->egypt()->id])->save();

        $course = $this->dawnCourse();
        $this->enroll($user, $course);
        $this->makeCourseExam($course);

        Carbon::setTestNow(Carbon::parse('2026-07-15 13:00:00', 'UTC'));

        $response = $this->actingAs($user)->get(route('learning.course', $course));

        $response->assertOk()
            ->assertSee(setting('learning.exam.block_title'))          // ظاهر لا مخفيّ
            ->assertSee(setting('learning.exam.locked_badge'))          // بقفل
            ->assertSee(setting('exams.messages.course_locked'))        // وسببٍ مكتوب
            ->assertDontSee(setting('learning.exam.start_cta'));        // وبلا وعدٍ كاذب

        $exam = $response->viewData('exam');
        $this->assertTrue($exam['locked']);
        $this->assertNull($exam['url'], 'ولا رابط دخولٍ يُكتَب على بابٍ يردّه الخادم.');
    }

    /** وداخل النافذة يعود الزرّ — فالقفل حالةٌ لا حذف */
    public function test_inside_the_window_the_exam_button_comes_back(): void
    {
        $user = $this->trainee();
        $user->forceFill(['country_id' => $this->egypt()->id])->save();

        $course = $this->dawnCourse();
        $this->enroll($user, $course);
        $this->makeCourseExam($course);

        Carbon::setTestNow(Carbon::parse('2026-07-15 03:30:00', 'UTC'));

        $exam = $this->actingAs($user)->get(route('learning.course', $course))->assertOk()->viewData('exam');

        $this->assertFalse($exam['locked']);
        $this->assertNull($exam['lock_reason']);
    }
}
