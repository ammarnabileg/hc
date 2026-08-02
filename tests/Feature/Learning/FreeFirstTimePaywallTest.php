<?php

namespace Tests\Feature\Learning;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\LibraryEntitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * «مجّاني أوّل مرّة» (16): مشاهدةٌ حرّةٌ متكرّرة، وبمجرّد (**امتحان + شهادة**)
 * يُقفَل التدريب ويُطلَب الشراء — **والإغلاق في الخادم لا في الواجهة**.
 */
class FreeFirstTimePaywallTest extends LearningTestCase
{
    public function test_free_first_time_course_stays_open_before_exam_and_certificate(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false, ['free_first_time' => true, 'price_coins' => 300]);
        $this->enroll($user, $course);

        $lesson = $this->lessonsOf($course)->first();

        $this->actingAs($user)->get(route('learning.course', $course))->assertOk();
        $this->actingAs($user)->get(route('learning.lesson', [$course, $lesson]))->assertOk();
    }

    /** الامتحان وحده لا يقفل — القاعدة (امتحان **و** شهادة) معًا */
    public function test_passing_the_exam_alone_does_not_lock_the_course(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false, ['free_first_time' => true, 'price_coins' => 300]);
        $this->enroll($user, $course);
        $this->passExam($user, $course);

        $lesson = $this->lessonsOf($course)->first();

        $this->actingAs($user)->get(route('learning.lesson', [$course, $lesson]))->assertOk();
    }

    /** ⭐ الفجوة الحرجة: بعد (الامتحان + الشهادة) يُقفَل الدرس في الخادم */
    public function test_course_locks_in_the_server_after_exam_and_certificate(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false, ['free_first_time' => true, 'price_coins' => 300]);
        $this->enroll($user, $course);
        $this->passExam($user, $course);
        $this->issueCertificate($user, $course);

        $lesson = $this->lessonsOf($course)->first();

        // صفحة الدرس لا تُفتَح — تُردّ لصفحة التدريب حيث السبب مكتوب
        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lesson]))
            ->assertRedirect(route('learning.course', $course));

        // وتسجيل الإكمال مرفوض كذلك — فلا مسار خلفيّ يتخطّى القفل
        $this->actingAs($user)->post(route('learning.lesson.complete', [$course, $lesson]));

        $this->assertSame(0, DB::table('lesson_completions')->where('user_id', $user->id)->count());
    }

    public function test_locked_course_page_shows_the_loss_aversion_paywall_with_its_price(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false, [
            'free_first_time' => true,
            'price_coins' => 300,
            'offer_price_coins' => 210,
            'offer_ends_at' => now()->addDays(5),
        ]);
        $this->enroll($user, $course);
        $this->passExam($user, $course);
        $this->issueCertificate($user, $course);

        $this->actingAs($user)
            ->get(route('learning.course', $course))
            ->assertOk()
            ->assertSee(setting('learning.paywall.title'))
            // السعر النهائيّ = سعر العرض الساري (16)
            ->assertSee('210');
    }

    /** نصّ الأدمن للتدريب نفسه يسبق النصّ العامّ — والحقل ما عاد حبرًا على ورق (16) */
    public function test_the_admin_paywall_text_of_the_course_wins_over_the_global_one(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(1, false, [
            'free_first_time' => true,
            'price_coins' => 300,
            'paywall_text_ar' => 'خلصت «{course}» وشهادتك في إيدك — كمّل الرحلة كاملة.',
        ]);
        $this->enroll($user, $course);
        $this->passExam($user, $course);
        $this->issueCertificate($user, $course);

        $this->actingAs($user)
            ->get(route('learning.course', $course))
            ->assertOk()
            ->assertSee('خلصت «'.$course->name_ar.'» وشهادتك في إيدك');
    }

    /** التدريب المشترى لا يُقفَل أبدًا — الملكيّة دائمة (20) */
    public function test_a_purchased_course_never_locks(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false, ['free_first_time' => true, 'price_coins' => 300]);
        $this->enroll($user, $course);
        $this->passExam($user, $course);
        $this->issueCertificate($user, $course);

        LibraryEntitlement::create([
            'user_id' => $user->id,
            'itemable_type' => Course::class,
            'itemable_id' => $course->id,
            'source' => 'purchase',
        ]);

        $lesson = $this->lessonsOf($course)->first();

        $this->actingAs($user)->get(route('learning.lesson', [$course, $lesson]))->assertOk();
    }

    /** التدريب العاديّ لا يتأثّر بالقاعدة إطلاقًا */
    public function test_a_normal_course_is_untouched_by_the_paywall(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false, ['free_first_time' => false, 'price_coins' => 300]);
        $this->enroll($user, $course);
        $this->passExam($user, $course);
        $this->issueCertificate($user, $course);

        $lesson = $this->lessonsOf($course)->first();

        $this->actingAs($user)->get(route('learning.lesson', [$course, $lesson]))->assertOk();
    }

    // ------------------------------------------------------------ مساعدات

    private function passExam(User $user, Course $course): void
    {
        $examId = DB::table('exams')->insertGetId([
            'examable_type' => Course::class,
            'examable_id' => $course->id,
            'title_ar' => 'امتحان '.$course->name_ar,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('exam_attempts')->insert([
            'exam_id' => $examId,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'score' => 90,
            'passed' => true,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function issueCertificate(User $user, Course $course): void
    {
        $type = CertificateType::create([
            'key' => 'course-'.str()->random(6),
            'name_ar' => 'شهادة إتمام',
            'name_en' => 'Completion certificate',
        ]);

        Certificate::create([
            'code' => str()->upper(str()->random(10)),
            'hash' => hash('sha256', $course->id.'-'.$user->id),
            'user_id' => $user->id,
            'certificate_type_id' => $type->id,
            'subject_type' => Course::class,
            'subject_id' => $course->id,
            'issued_at' => now(),
            'status' => 'valid',
        ]);
    }
}
