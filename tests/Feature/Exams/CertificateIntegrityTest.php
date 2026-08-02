<?php

namespace Tests\Feature\Exams;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Country;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\Section;
use App\Services\AdminScreens\QuestionBank;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * حجّية الوثيقة (8 · 8.1 · 12.5) وحارس الامتحان (4 · 4.2).
 *
 * كلّ اختبار هنا يقفل بابًا كان **مسار تزوير**: صورةٌ باسم شخصٍ مكان آخر تحت
 * نفس الكود، أو شهادةٌ تُنال بلا تسجيلٍ ولا تعلّم، أو سؤالُ درسٍ غير عامٍّ
 * يدخل الامتحان الذي تُصدَر بنجاحه الشهادة.
 */
class CertificateIntegrityTest extends ExamTestCase
{
    use RefreshDatabase;

    /**
     * ⭐ صورة الشهادة تطابق سجلّها بعد إعادة الإصدار.
     *
     * كان مفتاح الكاش هو **الكود وحده**، والكود يُعاد إنتاجه بعد أيّ حذف —
     * فيجيب الرابطُ الحيُّ بصورةٍ باسم صاحبٍ سابق بينما صفحة التحقّق تقول
     * غيرها. البصمة تُشتقّ من اللقطة المجمَّدة، فلا تصادم ممكن.
     */
    public function test_certificate_image_matches_its_record_after_reissue(): void
    {
        Storage::fake('local');

        $issuer = app(CertificateIssuer::class);
        $renderer = app(CertificateRenderer::class);

        $first = $issuer->issue($this->trainee('سلمى عبد الرحمن'), 'course');
        $firstPath = $this->cachePathOf($first);
        $renderer->png($first);

        $this->assertTrue(Storage::disk('local')->exists($firstPath), 'الصورة الأولى لم تُخزَّن.');

        // حذفٌ كامل للصفّ — وهو ما كان يعيد إنتاج نفس الكود لشخصٍ آخر
        $code = $first->code;
        $first->delete();

        $second = $issuer->issue($this->trainee('هالة منير'), 'course');

        // 12.5-ب: الكود الذي صدر مرّةً لا يصدر ثانيةً ولو مُحِي صفّه
        $this->assertNotSame($code, $second->code, 'أُعيد استعمال كودٍ صادر — بابُ تزوير.');

        // ولا ملفّ كاشٍ قديم يجيب عن الشهادة الجديدة
        $renderer->png($second);
        $this->assertNotSame($firstPath, $this->cachePathOf($second));

        $this->assertSame(
            'هالة منير',
            (string) ($second->fresh()->data_snapshot['holder_name'] ?? ''),
        );
    }

    /** تغيّر اللقطة أو الحالة ⟵ بصمةٌ أخرى ⟵ صورةٌ تُرسَم من جديد (8.1) */
    public function test_cache_key_follows_the_frozen_snapshot_not_the_code(): void
    {
        $renderer = app(CertificateRenderer::class);
        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course');

        $before = $renderer->fingerprint($certificate);

        $certificate->update(['status' => 'revoked', 'revoked_at' => now()]);

        $this->assertNotSame($before, $renderer->fingerprint($certificate->fresh()));
    }

    /**
     * ⭐ اسم الشهادة هو **اسم الشهادة** لا اسم العرض (8 · 3).
     */
    public function test_certificate_name_is_the_certificate_name_not_the_display_name(): void
    {
        $exam = $this->courseExam();
        $course = Course::find($exam->examable_id);
        $course->update([
            'name_ar' => 'إدارة المشروعات الصغيرة',
            'cert_name_ar' => 'شهادة معتمدة في إدارة المشروعات الصغيرة',
        ]);

        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course', $course->fresh());

        $this->assertSame(
            'شهادة معتمدة في إدارة المشروعات الصغيرة',
            (string) ($certificate->data_snapshot['certificate_name'] ?? ''),
        );
    }

    /** الدولة تُؤخَذ من ملفّ المستخدم لحظة الإصدار ولا تخرج فارغةً (8) */
    public function test_country_comes_from_the_holder_profile(): void
    {
        $country = Country::create([
            'name_ar' => 'مصر',
            'name_en' => 'Egypt',
            'iso2' => 'EG',
            'timezone' => 'Africa/Cairo',
            'is_active' => true,
        ]);

        $user = $this->trainee();
        $user->forceFill(['country_id' => $country->id])->save();

        $certificate = app(CertificateIssuer::class)->issue($user->fresh(), 'course');

        $this->assertSame('مصر', (string) ($certificate->data_snapshot['country'] ?? ''));
    }

    /**
     * ⭐ غير المسجَّل **لا يدخل الامتحان** (4.2 · 8): الصلاحيّة وحدها كانت تكفي،
     * فيدفع تذكرةً ويأخذ شهادةً بلا أيّ تعلّم.
     */
    public function test_a_user_who_is_not_enrolled_cannot_enter_the_course_exam(): void
    {
        $exam = $this->courseExam();
        $user = $this->trainee();

        $this->assertFalse(Enrollment::query()->where('user_id', $user->id)->exists());

        $this->actingAs($user)->get(route('exams.start', $exam))
            ->assertRedirect(route('learning.course', Course::find($exam->examable_id)));

        $this->actingAs($user)->post(route('exams.begin', $exam))->assertRedirect();

        $this->assertSame(0, ExamAttempt::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, Certificate::query()->where('user_id', $user->id)->count());
    }

    /** والمسجَّل يدخل عاديًّا — الحارس يمنع غير المسجَّل وحده */
    public function test_an_enrolled_user_still_reaches_the_exam(): void
    {
        $exam = $this->courseExam();
        $user = $this->trainee();

        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => (int) $exam->examable_id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($user)->get(route('exams.start', $exam))->assertOk();
    }

    /**
     * ⭐ السؤال غير العامّ **يُرفَض** (4): «الأسئلة العامّة فقط هي المؤهّلة
     * للدخول في الامتحان النهائيّ».
     */
    public function test_a_non_general_question_is_refused_by_the_bank(): void
    {
        $exam = $this->courseExam();

        $section = Section::create([
            'course_id' => (int) $exam->examable_id,
            'title_ar' => 'القسم الأوّل',
            'sort_order' => 1,
        ]);

        $lesson = Lesson::create([
            'section_id' => $section->id,
            'title_ar' => 'الدرس الأوّل',
            'type' => 'text',
            'content' => 'محتوى',
            'sort_order' => 1,
        ]);

        $question = LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'prompt' => 'سؤال درسٍ لا يصلح للامتحان النهائيّ',
            'correct_answer' => 'إجابة',
            'is_general' => false,
            'is_active' => true,
        ]);

        $result = app(QuestionBank::class)->reuse($question, [$exam->id]);

        $this->assertSame(0, $result['attached']);
        $this->assertSame(1, $result['skipped']);

        // وحين يُعلَّم «عام» يمرّ — الخاصّيّة تعمل في الاتّجاهين
        $question->update(['is_general' => true]);

        $this->assertSame(1, app(QuestionBank::class)->reuse($question->fresh(), [$exam->id])['attached']);
    }

    /** مسار الكاش الحاليّ لهذه الشهادة — يعكس البصمة لا الكود وحده */
    private function cachePathOf(Certificate $certificate): string
    {
        return 'certificates/'.$certificate->code.'/'.$certificate->language
            .'-'.app(CertificateRenderer::class)->fingerprint($certificate).'.png';
    }

    /** نوع شهادة التدريب موجودٌ في CoreSeeder — نتأكّد فقط أنّه فعّال */
    protected function setUp(): void
    {
        parent::setUp();

        CertificateType::query()->where('key', 'course')->update(['is_active' => true]);
    }
}
