<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\LearningPath;
use App\Models\VolunteerRecording;
use App\Services\Volunteer\People\AcademyService;
use App\Services\Volunteer\People\RecordingRewards;
use Illuminate\Support\Facades\DB;

/**
 * الأكاديمية (13.4-ل): OTP بتحقّق Server-side مرّةً واحدة،
 * و[احصل على الشهادة] بعد 100% فقط ولمسار مربوط.
 */
class AcademyRecordingsTest extends PeopleTestCase
{
    public function test_otp_is_verified_server_side_and_rewards_only_once(): void
    {
        $rewards = app(RecordingRewards::class);
        $user = $this->makeUser('متطوّع');

        $recording = VolunteerRecording::create([
            'title' => 'تسجيل بـرمز',
            'url' => 'https://example.test/video',
            'source' => 'drive',
            'otp' => '1234',
            'status' => 'published',
        ]);

        // رمز خطأ: لا كسب ولا سجلّ
        $wrong = $rewards->claim($recording, $user, '0000');
        $this->assertSame(RecordingRewards::RESULT_WRONG, $wrong['result']);
        $this->assertDatabaseCount('volunteer_recording_claims', 0);

        $ok = $rewards->claim($recording, $user, '1234');
        $this->assertSame(RecordingRewards::RESULT_OK, $ok['result']);
        $this->assertDatabaseCount('volunteer_recording_claims', 1);

        // المرّة الثانية: مستنفَد — والقيد الفريد هو الحارس
        $again = $rewards->claim($recording->fresh(), $user, '1234');
        $this->assertSame(RecordingRewards::RESULT_ALREADY, $again['result']);
        $this->assertDatabaseCount('volunteer_recording_claims', 1);
    }

    public function test_recording_without_otp_grants_nothing(): void
    {
        $rewards = app(RecordingRewards::class);

        $recording = VolunteerRecording::create([
            'title' => 'تسجيل بلا رمز',
            'url' => 'https://example.test/open',
            'source' => 'youtube',
            'status' => 'published',
        ]);

        $result = $rewards->claim($recording, $this->makeUser('متطوّع'), 'أيّ حاجة');

        $this->assertSame(RecordingRewards::RESULT_NO_OTP, $result['result']);
        $this->assertDatabaseCount('volunteer_recording_claims', 0);
    }

    public function test_reward_badge_reads_its_numbers_from_settings_and_rep_rules(): void
    {
        $rewards = app(RecordingRewards::class);

        $this->assertSame(10.0, $rewards->vxpValue());
        $this->assertSame(0.2, $rewards->repValue());
        $this->assertStringContainsString('مرّة واحدة', $rewards->rewardBadge());
    }

    public function test_certificate_cta_appears_only_at_100_percent_and_only_when_linked(): void
    {
        $academy = app(AcademyService::class);
        $user = $this->makeUser('متعلّم');

        $target = LearningPath::create([
            'slug' => 'target-path-'.str()->random(5),
            'name_ar' => 'مسار الشهادة',
            'status' => 'published',
        ]);

        $linked = LearningPath::create([
            'slug' => 'academy-linked-'.str()->random(5),
            'name_ar' => 'مسار أكاديميّ مربوط',
            'status' => 'published',
            'is_academy' => true,
            'target_path_id' => $target->id,
        ]);

        $pure = LearningPath::create([
            'slug' => 'academy-pure-'.str()->random(5),
            'name_ar' => 'مسار تعليميّ صِرف',
            'status' => 'published',
            'is_academy' => true,
        ]);

        $course = Course::create([
            'slug' => 'c-'.str()->random(6),
            'name_ar' => 'تدريب',
            'status' => 'published',
        ]);

        foreach ([$linked->id, $pure->id, $target->id] as $pathId) {
            DB::table('course_learning_path')->insert([
                'course_id' => $course->id, 'learning_path_id' => $pathId,
                'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // قبل الإكمال: بار صامت بلا CTA
        $before = $academy->progress($user, $linked);
        $this->assertSame(0, $before['percent']);
        $this->assertFalse($academy->showCertificateCta($before));

        // سجلّ إكمال واحد لكلّ (مستخدم، كورس) — يخدم المسارين معًا
        CourseCompletion::create([
            'user_id' => $user->id, 'course_id' => $course->id, 'completed_at' => now(),
        ]);

        $after = $academy->progress($user, $linked);
        $this->assertSame(100, $after['percent']);
        $this->assertTrue($academy->showCertificateCta($after));
        $this->assertSame((string) setting('academy.complete.linked_message'), $academy->completionMessage($after));

        // التعليميّ الصِرف: مكتمل لكن **بلا CTA وبلا أيّ إيحاء بشهادة ناقصة**
        $pureProgress = $academy->progress($user, $pure);
        $this->assertSame(100, $pureProgress['percent']);
        $this->assertFalse($academy->showCertificateCta($pureProgress));
        $this->assertSame((string) setting('academy.complete.pure_message'), $academy->completionMessage($pureProgress));
    }
}
