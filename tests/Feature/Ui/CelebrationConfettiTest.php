<?php

namespace Tests\Feature\Ui;

use App\Models\CelebrationEvent;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Setting;
use App\Models\User;
use App\Services\Learning\VideoWatchService;
use Database\Seeders\SettingSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Learning\LearningTestCase;

/**
 * 🎉 كونفيتي لحظة الإكمال — الحارس على نصّ 4.1 حرفيًّا:
 *
 *   «**احتفال إنهاء الفيديو/الدرس: كونفيتي بينزل من فوق لتحت (Confetti Rain)
 *   لحظة الإكمال** — لحظة ذروة (Peak-End، راجع 2.9-#6).»
 *
 * وكان المبنيّ يعطي **صفر قطعة**: الحدث بمستوى 1 والقالب يحسب
 * `$pieces = $tier === 3 ? 36 : ($tier === 2 ? 16 : 0)` — فشريطٌ علويّ بلا
 * كونفيتي أصلًا، أي لحظة الذروة تمرّ بلا ذروة.
 *
 * والمستوى يبقى **1** كما في جدول 2.14-أ («1 — خفيف … إكمال درس» · «بلا صوت»)،
 * فالمستوى من 2.14 والشكل من 4.1 — ولا يُرفَع الحدث للذروة وإلّا خضع الكونفيتي
 * لـ«الحدّ اليوميّ لمستوى الذروة» فانطفأ بعد ثلاثة دروس.
 */
class CelebrationConfettiTest extends LearningTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // مفاتيح الاحتفالات تُزرَع في **مسار الإنتاج** (2.13) — وهذا هو سيدرها
        $this->seed(SettingSeeder::class);
        cache()->forget('settings');
    }

    #[Test]
    public function finishing_a_lesson_rains_confetti_from_top_to_bottom(): void
    {
        [$user, $course, $lesson] = $this->readyToComplete();

        $response = $this->actingAs($user)
            ->post(route('learning.lesson.complete', [$course, $lesson]))
            ->assertRedirect();

        $html = $this->actingAs($user)->get($response->headers->get('Location'))->assertOk()->getContent();

        // 1) الكونفيتي موجود فعلًا — ونوعه «مطر» لا شريطٌ فاضٍ
        $this->assertStringContainsString('data-confetti="rain"', $html,
            'لحظة إنهاء الدرس بلا كونفيتي — و4.1 تنصّ على «كونفيتي بينزل من فوق لتحت».');

        // 2) وبعددٍ حقيقيّ: القطع مرسومة في الـDOM لا وعدٌ في تعليق
        $declared = (int) setting('celebrations.confetti.rain_pieces');
        $this->assertGreaterThan(0, $declared);
        $this->assertSame($declared, $this->attribute($html, 'data-confetti-pieces'));
        $this->assertSame($declared, substr_count($html, 'class="confetti-piece"'),
            'عدد قطع الكونفيتي في الصفحة لا يساوي العدد المعلَن.');

        // 3) والنزول **من فوق لتحت**: يبدأ فوق الشاشة وينتهي تحتها
        $this->assertMatchesRegularExpression(
            '#@keyframes confetti-fall\s*\{\s*from\s*\{[^}]*translate3d\(0,\s*-\d+vh#',
            $html,
            'الكونفيتي لا يبدأ من فوق الشاشة.',
        );
        $this->assertMatchesRegularExpression(
            '#to\s*\{[^}]*translate3d\(0,\s*1\d\dvh#',
            $html,
            'الكونفيتي لا ينتهي تحت الشاشة — فهو لا «ينزل من فوق لتحت».',
        );
    }

    /** المستوى من 2.14-أ: «1 — خفيف (Micro) … **بلا صوت** … إكمال درس» */
    #[Test]
    public function the_lesson_event_keeps_its_tier_one_and_stays_silent(): void
    {
        $this->assertSame(1, (int) CelebrationEvent::query()->where('key', 'lesson.completed')->value('tier'));

        [$user, $course, $lesson] = $this->readyToComplete();

        $this->actingAs($user)->post(route('learning.lesson.complete', [$course, $lesson]));

        $this->assertFalse((bool) (session('celebration')['sound'] ?? false),
            'إكمال الدرس مستواه 1 و«بلا صوت» نصًّا (2.14-أ).');
    }

    /** لا رقم محروق (2.13): العدد والمدّة والشدّة يملكها المالك من `setting()` */
    #[Test]
    public function the_piece_count_and_timing_come_from_settings(): void
    {
        Setting::query()->where('key', 'celebrations.confetti.rain_pieces')->update(['value' => '7']);
        Setting::query()->where('key', 'celebrations.confetti.fall_ms')->update(['value' => '4321']);
        cache()->forget('settings');

        [$user, $course, $lesson] = $this->readyToComplete();

        $response = $this->actingAs($user)->post(route('learning.lesson.complete', [$course, $lesson]));
        $html = $this->actingAs($user)->get($response->headers->get('Location'))->getContent();

        $this->assertSame(7, $this->attribute($html, 'data-confetti-pieces'));
        $this->assertSame(7, substr_count($html, 'class="confetti-piece"'));
        $this->assertStringContainsString('animation-duration: 4321ms', $html);

        // ولا عددَ محروقٍ في القوالب: مصدرٌ واحد `<x-confetti>` يقرأ `setting()`
        $component = (string) file_get_contents(resource_path('views/components/confetti.blade.php'));

        foreach (['rain_pieces', 'light_pieces', 'confetti.pieces', 'fall_ms', 'stagger_ms'] as $key) {
            $this->assertStringContainsString($key, $component);
        }
    }

    /** 2.15-ج: القطع داخل حاويةٍ بمقاس الشاشة تقصّ الفائض — فلا تمرير أفقيّ على 375px */
    #[Test]
    public function the_confetti_stage_cannot_widen_the_page(): void
    {
        $component = (string) file_get_contents(resource_path('views/components/confetti.blade.php'));

        $this->assertStringContainsString('fixed inset-0 overflow-hidden', $component,
            'حاوية الكونفيتي بلا قصٍّ للفائض — القطع هتمدّ الصفحة أفقيًّا على الموبايل.');
        $this->assertStringNotContainsString('overflow-x-auto', $component);
    }

    /** مصدر واحد للاحتفالات (2.14-ب) — لا نسخة كونفيتي في كلّ قالب */
    #[Test]
    public function every_celebration_partial_uses_the_single_confetti_source(): void
    {
        $partials = [
            'views/learning/partials/celebration.blade.php',
            'views/home/partials/celebration.blade.php',
            'views/onboarding/partials/celebration.blade.php',
            'views/challenges/components/celebration.blade.php',
        ];

        foreach ($partials as $partial) {
            $body = (string) file_get_contents(resource_path($partial));

            $this->assertStringContainsString('<x-confetti', $body, $partial);
            $this->assertDoesNotMatchRegularExpression('#\$pieces\s*=\s*\$tier#', $body, $partial);
        }
    }

    // ------------------------------------------------------------------ أدوات

    /**
     * @return array{0:User,1:Course,2:Lesson} متدرّب شاهد الفيديو وما بقي إلّا زرّ الإكمال
     *
     * ⚠️ ويُكمَل الدرس الأوّل أوّلًا عن قصد: XP أوّل درسٍ يرفع المستوى، و«لا
     * تتراكم: يُعرَض الأعلى مستوى فقط» (2.14-ب) — فيبتلع `level.up` احتفالَ
     * الدرس. فالمقيس هنا هو **لحظة إكمال درسٍ خالصة**.
     */
    private function readyToComplete(): array
    {
        $course = $this->makeCourse(4);
        $user = $this->trainee();
        $this->enroll($user, $course);

        $lessons = $this->lessonsOf($course)->values();

        // «إنهاء الدرس = مشاهدة الفيديو + اجتياز اختبار الدرس» (4.1)
        app(VideoWatchService::class)->track($user, $lessons[0], 600, 600);
        $this->actingAs($user)->post(route('learning.lesson.complete', [$course, $lessons[0]]));

        app(VideoWatchService::class)->track($user, $lessons[1], 600, 600);

        return [$user, $course, $lessons[1]];
    }

    private function attribute(string $html, string $name): int
    {
        preg_match('#'.preg_quote($name, '#').'="(\d+)"#', $html, $m);

        return (int) ($m[1] ?? 0);
    }
}
