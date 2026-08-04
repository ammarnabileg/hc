<?php

namespace Tests\Feature\Learning;

use App\Models\Badge;
use App\Models\BadgeUser;
use App\Models\Currency;
use App\Models\LessonQuestion;
use App\Models\Level;
use App\Models\WalletBalance;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\LevelResolver;
use App\Services\Learning\LessonQuestionService;
use App\Services\Learning\ProgressService;
use App\Services\Learning\VideoWatchService;

/**
 * سلامة مكافآت الدرس (4.1 · 7 · 7.4).
 *
 * ثلاثة أبواب كانت مفتوحة: اختبار الدرس يمنح XP رغم نصّ «لا يؤثّر في الحساب»،
 * والشارات لا تُقيَّم إلّا حين يفتح المتدرّب صفحتها بنفسه، وشرط «مشاهدة
 * الفيديو» في تعريف إنهاء الدرس بلا تنفيذ أصلًا.
 */
class LessonRewardIntegrityTest extends LearningTestCase
{
    /**
     * ⭐ اختبار الدرس **لا يمنح XP** (4.1): «لا يكلّف تذاكر ولا يؤثّر في الحساب».
     *
     * ولماذا يهمّ؟ لأنّ نقاط السؤال ثابتة لا تخضع للتناقص الخطّيّ (7)، فمنحُها
     * كان مسارًا ثانيًا يلتفّ على قاعدة الإنجاز المبكر كلّها.
     */
    public function test_a_lesson_quiz_grants_no_xp(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(1, false, ['xp_max' => 100]);
        $lesson = $this->lessonsOf($course)->first();

        $question = LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'prompt' => 'اكتب «صحّ»',
            'correct_answer' => 'صحّ',
            'xp_reward' => 10,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $before = (int) $user->fresh()->xp;

        $result = app(LessonQuestionService::class)->answer($user, $question, 'صحّ');

        $this->assertTrue($result['correct']);
        $this->assertSame(0, $result['xp']);
        $this->assertSame($before, (int) $user->fresh()->xp, 'اختبار الدرس منح XP — خرقٌ لـ4.1.');
    }

    /**
     * ⭐ إكمال درسٍ **يمنح الشارة فورًا** (7.4): لا تنتظر أن يفتح المتدرّب
     * صفحة الشارات بنفسه — والشارة المتأخّرة عن لحظتها ليست شارة.
     */
    public function test_completing_a_lesson_awards_its_badge_immediately(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false, ['xp_max' => 60]);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $badge = Badge::create([
            'key' => 'first_lesson_'.str()->random(4),
            'name_ar' => 'أوّل خطوة',
            'name_en' => 'First Step',
            'condition_text_ar' => 'أكمل أوّل درس في أيّ تدريب.',
            'condition_key' => 'lesson.completed',
            'condition_value' => 1,
            'is_active' => true,
        ]);

        $this->assertSame(0, BadgeUser::query()->where('user_id', $user->id)->count());

        $result = app(ProgressService::class)->completeLesson($user, $course, $lesson, $enrollment);

        $this->assertTrue($result['ok']);
        $this->assertTrue(
            BadgeUser::query()->where('user_id', $user->id)->where('badge_id', $badge->id)->exists(),
            'الشارة لم تُمنَح لحظة الإنجاز.',
        );
    }

    /** ⭐ كلّ مفتاح في القائمة المقفولة له مقياسٌ فعليّ — وإلّا فهي شارة ميتة (7.4) */
    public function test_every_offered_condition_key_has_a_live_metric(): void
    {
        $metrics = app(BadgeService::class)->metrics($this->trainee());

        foreach (array_keys(BadgeService::CONDITIONS) as $key) {
            $this->assertArrayHasKey($key, $metrics, 'المفتاح «'.$key.'» معروضٌ في الفورم بلا مقياس.');
        }
    }

    /**
     * ⭐ **المستوى مصدرٌ واحد** (7 · 10 · 10.1) — لا عمودٌ متأخّر ولا جدولُ عتبات.
     *
     * كان هذا الحارس يقارن مقياس الشارة بـ**أقصى صفٍّ في جدول `levels`**، وهو
     * مصدرٌ ثانٍ سقط بنصّ 10.1: «الزيادة للوصول للمستوى N = `base + (N − 2) ×
     * step` … **والمستويات مفتوحة بلا سقف بنفس المعادلة**». فجدولٌ بثمانية صفوف
     * يقصّ العدّ عند «أسطورة»، والصيغة تمضي — وهذا **عين ن-2**: رقمان لمعنًى
     * واحد. فأُعيد بناء الحارس على **المصدر الحاكم** لا على المصدر الساقط.
     *
     * ويحرس ثلاثًا معًا: أنّ المقياس يتبع XP لا العمود المخبَّأ، وأنّه هو نفسه
     * الذي تعرضه اللوحة والبروفايل، وأنّه **لا يتوقّف عند سقف الجدول**.
     */
    public function test_level_metric_follows_xp_not_a_stale_column(): void
    {
        $user = $this->trainee();
        $user->forceFill(['xp' => 999999, 'level' => 1])->save();

        $levels = app(LevelResolver::class);
        $metrics = app(BadgeService::class)->metrics($user->fresh());

        // (أ) المصدر الواحد: صيغة 10.1 على XP — وهو ما يقرؤه كارت الـKPI والرادار
        $this->assertSame((float) $levels->levelFor(999999), $metrics['level.reached']);

        // (ب) لا العمود المخبَّأ المتأخّر (1)
        $this->assertNotSame(1.0, $metrics['level.reached']);

        // (ج) ولا سقف جدول الأسماء — «المستويات مفتوحة بلا سقف» (10.1)
        $tableCeiling = (int) Level::query()->orderByDesc('level')->value('level');
        $this->assertGreaterThan((float) $tableCeiling, $metrics['level.reached']);

        // (د) وXP نفسه من المصدر الواحد لا من العمود وحده
        $this->assertSame((float) $levels->xpFor($user->fresh()), $metrics['xp.total']);
    }

    /** ⭐ ومقياس XP يتبع دفتر المحفظة لا العمود — فالشارة تُقيَّم بما يراه صاحبها */
    public function test_the_xp_metric_reads_the_wallet_ledger_not_the_column(): void
    {
        $user = $this->trainee();
        $user->forceFill(['xp' => 100])->save();

        // الدفتر يقول 4,000 والعمود يقول 100 — والحاكم هو الدفتر (19.2 · 7.3)
        WalletBalance::updateOrCreate(
            ['user_id' => $user->id, 'currency_id' => Currency::where('code', 'xp')->value('id')],
            ['balance' => 4000, 'lifetime_earned' => 4000, 'lifetime_spent' => 0],
        );

        $metrics = app(BadgeService::class)->metrics($user->fresh());

        $this->assertSame(4000.0, $metrics['xp.total']);
        $this->assertSame((float) app(LevelResolver::class)->levelFor(4000), $metrics['level.reached']);
    }

    /**
     * ⭐ «إنهاء الدرس» = مشاهدة الفيديو **+** اجتياز اختباره (4.1).
     * كان `POST /complete` ينجح بلا أيّ تتبّع مشاهدة.
     */
    public function test_a_video_lesson_cannot_be_completed_before_it_is_watched(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(1, false, ['xp_max' => 50]);
        $enrollment = $this->enroll($user, $course);

        $lesson = $this->lessonsOf($course)->first();
        $lesson->update(['type' => 'video', 'video_provider' => 'youtube', 'video_id' => 'abc123', 'duration_minutes' => 1]);

        $progress = app(ProgressService::class);

        $refused = $progress->completeLesson($user, $course, $lesson->fresh(), $enrollment);
        $this->assertFalse($refused['ok'], 'اكتمل درس فيديو بلا مشاهدة — خرقٌ لتعريف 4.1.');

        // المشاهدة تُسجَّل تدريجيًّا: القفزة الواحدة محدودة فلا يكفي نداءٌ ملفَّق
        $watch = app(VideoWatchService::class);

        foreach (range(1, 5) as $ignored) {
            $watch->track($user, $lesson->fresh(), 60, 60);
        }

        $this->assertTrue($watch->hasWatched($user, $lesson->fresh()));
        $this->assertTrue($progress->completeLesson($user, $course, $lesson->fresh(), $enrollment)['ok']);
    }

    /** التقرير الواحد الملفَّق لا يُنهي فيديوًا طويلًا — القرار في الخادم (4.1) */
    public function test_a_single_forged_report_does_not_finish_a_long_video(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(1, false);
        $lesson = $this->lessonsOf($course)->first();
        $lesson->update(['type' => 'video', 'video_provider' => 'youtube', 'video_id' => 'x', 'duration_minutes' => 30]);

        app(VideoWatchService::class)->track($user, $lesson->fresh(), 999999, 1800);

        $this->assertFalse(app(VideoWatchService::class)->hasWatched($user, $lesson->fresh()));
    }
}
