<?php

namespace Tests\Feature\Growth;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Governorate;
use App\Models\Lesson;
use App\Models\Referral;
use App\Models\User;
use App\Services\Growth\InviteLeaderboard;
use App\Services\Growth\LessonPreview;
use App\Services\Growth\ProfileCompletion;
use App\Services\Referral\DeepLink;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\Auth;

/** حلقات النموّ (21.1): بار «أكمل ملفك» · المعاينة المجّانيّة · الروابط العميقة · لوحة الدعوات */
class GrowthLoopsTest extends GrowthTestCase
{
    // ------------------------------------------------ 21.1-ب — بار «أكمل ملفك»

    private function completeUser(): User
    {
        $country = Country::query()->firstOrCreate(
            ['iso2' => 'EG'],
            ['name_ar' => 'مصر', 'name_en' => 'Egypt'],
        );

        $governorate = Governorate::query()->firstOrCreate(
            ['country_id' => $country->id, 'name_ar' => 'القاهرة'],
            ['name_en' => 'Cairo'],
        );

        return $this->trainee([
            'avatar_path' => 'avatars/a.png',
            'phone' => '+2010'.random_int(10000000, 99999999),
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'birthdate' => '1999-01-01',
            'gender' => 'female',
        ]);
    }

    public function test_percent_reflects_missing_fields(): void
    {
        $service = app(ProfileCompletion::class);

        $this->assertLessThan(100, $service->percent($this->trainee()));
        $this->assertSame(100, $service->percent($this->completeUser()));
    }

    /** ⭐ المكافأة 3 تذاكر ومرّة واحدة مهما تكرّر النداء (idempotent) — `grant()` نفسها لا `sync()` */
    public function test_reward_is_granted_once_only(): void
    {
        Currency::query()->firstOrCreate(['code' => 'tickets'], ['name_ar' => 'تذاكر', 'symbol' => '🎟️']);

        $user = $this->completeUser();
        $service = app(ProfileCompletion::class);

        $this->assertTrue($service->grant($user));
        $this->assertFalse($service->grant($user->fresh()));
        $this->assertFalse($service->grant($user->fresh()));

        $this->assertSame(
            (float) setting('growth.profile_completion.reward_tickets', 3),
            $user->fresh()->balance('tickets'),
        );
    }

    /**
     * ⭐ [2026-09-10] `sync()` لا تمنح أبدًا — كانت تمنح داخل طلب GET نفسه
     * (الصفحة والبار)، وفعلٌ ماليٌّ (12.9) على GET يقدر يُطلَق بطلب قراءة
     * مموَّه بلا ضغطة مستخدم. المِنح صار محصورًا في `grant()` مباشرةً من
     * نقطتين غير-GET فقط: حدث الدخول والـPOST المخصَّص.
     */
    public function test_sync_never_grants_even_when_the_profile_is_already_complete(): void
    {
        Currency::query()->firstOrCreate(['code' => 'tickets'], ['name_ar' => 'تذاكر', 'symbol' => '🎟️']);

        $user = $this->completeUser();
        $service = app(ProfileCompletion::class);

        $state = $service->sync($user);

        $this->assertSame(100, $state['percent']);
        $this->assertTrue($state['eligible']);
        $this->assertFalse($state['granted']);
        $this->assertFalse($state['rewarded']);
        $this->assertSame(0.0, $user->fresh()->balance('tickets'));

        // وتكرار النداء (كما يحدث مع كلّ صفحةٍ فيها البار) لا يمنح أيضًا
        $service->sync($user->fresh());
        $this->assertSame(0.0, $user->fresh()->balance('tickets'));
    }

    /**
     * ⭐ زيارة صفحة الإكمال (GET) لا تمنح المكافأة — الـPOST المخصَّص
     * (`claim`) هو الوحيد الذي يمنح، وهو محميٌّ بـCSRF فلا يُزوَّر بطلب قراءة.
     */
    public function test_visiting_the_completion_page_does_not_grant_but_claiming_does(): void
    {
        Currency::query()->firstOrCreate(['code' => 'tickets'], ['name_ar' => 'تذاكر', 'symbol' => '🎟️']);

        $user = $this->completeUser();

        $this->actingAs($user)->get(route('growth.profile.completion'))
            ->assertOk()
            // الفورم المرسَلة تلقائيًّا موجودة — فالتجربة تبقى «بلا ضغطة زائدة»
            ->assertSee(route('growth.profile.completion.claim'), false);

        $this->assertSame(0.0, $user->fresh()->balance('tickets'), 'زيارة الصفحة وحدها منحت المكافأة — فعلٌ ماليٌّ على GET.');

        $this->actingAs($user)->post(route('growth.profile.completion.claim'))->assertRedirect(route('growth.profile.completion'));

        $this->assertSame(
            (float) setting('growth.profile_completion.reward_tickets', 3),
            $user->fresh()->balance('tickets'),
        );

        // وتكرار المطالبة لا يضاعف — نفس ضمان idempotent السابق
        $this->actingAs($user->fresh())->post(route('growth.profile.completion.claim'));
        $this->assertSame(
            (float) setting('growth.profile_completion.reward_tickets', 3),
            $user->fresh()->balance('tickets'),
        );
    }

    /**
     * ⭐ الدخول (حدثٌ حقيقيّ لا GET) يمنح المكافأة لو كان الملفّ مكتملًا
     * بالفعل قبل الدخول — `SettleGrowthOnLogin` هو الموضع الآمن للمِنح
     * التلقائيّ خارج الشاشة المخصَّصة (انظر `test_sync_never_grants_…`).
     */
    public function test_logging_in_grants_the_reward_when_the_profile_is_already_complete(): void
    {
        Currency::query()->firstOrCreate(['code' => 'tickets'], ['name_ar' => 'تذاكر', 'symbol' => '🎟️']);

        $user = $this->completeUser();

        Auth::login($user);

        $this->assertSame(
            (float) setting('growth.profile_completion.reward_tickets', 3),
            $user->fresh()->balance('tickets'),
        );

        // وتسجيلا دخولٍ لاحقان لا يضاعفان — idempotent كعادة grant()
        Auth::logout();
        Auth::login($user->fresh());
        $this->assertSame(
            (float) setting('growth.profile_completion.reward_tickets', 3),
            $user->fresh()->balance('tickets'),
        );
    }

    /** البار يظهر على شاشاته المعتمَدة فقط، ويختفي عند 100% */
    public function test_bar_shows_for_incomplete_profile_and_hides_when_complete(): void
    {
        $this->setSetting('growth.profile_completion.bar_routes', '["growth.articles.index"]', 'json');

        $this->actingAs($this->trainee())->get(route('growth.articles.index'))
            ->assertOk()
            ->assertSee('كمّل ملفّك');

        $this->actingAs($this->completeUser())->get(route('growth.articles.index'))
            ->assertOk()
            ->assertDontSee('كمّل ملفّك');
    }

    /** ولا يظهر في شاشةٍ خارج قائمته — «سؤال واحد لكلّ شاشة» (2.15-أ-1) */
    public function test_bar_is_absent_outside_its_configured_screens(): void
    {
        $this->setSetting('growth.profile_completion.bar_routes', '["dashboard"]', 'json');

        $this->actingAs($this->trainee())->get(route('growth.articles.index'))
            ->assertOk()
            ->assertDontSee('كمّل ملفّك');
    }

    public function test_completion_screen_lists_missing_fields(): void
    {
        $this->actingAs($this->trainee())->get(route('growth.profile.completion'))
            ->assertOk()
            ->assertSee('صورة الملفّ')
            ->assertSee('الدولة');
    }

    // ------------------------------------------------ 21.1-أ — المعاينة المجّانيّة

    public function test_preview_opens_allowed_lesson_publicly_and_blocks_the_rest(): void
    {
        $course = $this->makeCourse(previewLessons: 1);
        $lessons = Lesson::query()->orderBy('sort_order')->get();

        $this->get(route('growth.preview.course', $course->slug))
            ->assertOk()
            ->assertSee('الدرس 1')
            ->assertSee('بعد التسجيل');

        // الدرس الأوّل يُفتَح للزائر بلا تسجيل
        $this->get(route('growth.preview.lesson', ['slug' => $course->slug, 'lesson' => $lessons[0]->id]))
            ->assertOk()
            ->assertSee('محتوى الدرس 1', false);

        // ⛔ والحاجز في الخادم: معرفة الرابط لا تفتح درسًا خارج المعاينة
        $this->get(route('growth.preview.lesson', ['slug' => $course->slug, 'lesson' => $lessons[2]->id]))
            ->assertForbidden();
    }

    public function test_preview_quota_is_capped_by_the_setting(): void
    {
        $course = $this->makeCourse(previewLessons: 99);
        $this->setSetting('growth.preview.max_lessons', '2', 'number');

        $this->assertSame(2, app(LessonPreview::class)->lessons($course)->count());
    }

    public function test_unpublished_course_preview_is_404(): void
    {
        $course = $this->makeCourse();
        $course->update(['status' => 'draft']);

        $this->get(route('growth.preview.course', $course->slug))->assertNotFound();
    }

    // ------------------------------------------------ 21.1-ج — الروابط العميقة

    public function test_deep_link_supports_courses_and_paths_not_events_only(): void
    {
        $deepLink = app(DeepLink::class);

        $this->assertTrue($deepLink->isSupported('course'));
        $this->assertTrue($deepLink->isSupported('path'));
        $this->assertFalse($deepLink->isSupported('product'));

        $course = $this->makeCourse();
        $url = $deepLink->url('course', $course->id);

        $this->assertStringContainsString($course->slug, (string) $url);
        // ⭐ UTM موحّد على كلّ رابط تولّده المنصّة (21.2-ح)
        $this->assertStringContainsString('utm_medium=invite', (string) $url);
    }

    /** «يُفتَح على نفس الصفحة بعد التسجيل» — وكان يضيع لأنّ الاعتماد يبدأ جلسة جديدة */
    public function test_invite_landing_survives_activation_and_opens_once(): void
    {
        $course = $this->makeCourse();
        $referrer = $this->trainee();
        $invited = $this->trainee(['status' => 'pending', 'activated_at' => null]);

        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $invited->id,
            'code' => $referrer->code,
            'landing_type' => 'course',
            'landing_id' => $course->id,
            'commission_percent' => 7,
        ]);

        // دخولٌ وهو «تحت المراجعة»: الوجهة تُثبَّت على المستخدم ولا تُستهلك
        Auth::login($invited);
        $invited->refresh();

        $this->assertStringContainsString($course->slug, (string) $invited->invite_landing_url);
        $this->assertNull($invited->invite_landing_seen_at);

        // بعد الاعتماد: أوّل دخول يستهلكها مرّةً واحدة
        Auth::logout();
        $invited->forceFill(['status' => 'active', 'activated_at' => now()])->saveQuietly();

        Auth::login($invited->fresh());
        $this->assertNotNull($invited->fresh()->invite_landing_seen_at);
    }

    public function test_landing_url_for_uses_the_referral_row(): void
    {
        $course = $this->makeCourse();
        $referrer = $this->trainee();
        $invited = $this->trainee();

        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $invited->id,
            'code' => $referrer->code,
            'landing_type' => 'course',
            'landing_id' => $course->id,
            'commission_percent' => 7,
        ]);

        $this->assertStringContainsString(
            $course->slug,
            (string) app(ReferralService::class)->landingUrlFor($invited),
        );
    }

    // ------------------------------------------------ 21.1-ج — لوحة متصدّري الدعوات

    public function test_monthly_invite_board_ranks_completed_invites(): void
    {
        $top = $this->trainee(['name' => 'الداعي الأوّل']);
        $second = $this->trainee(['name' => 'الداعي التاني']);

        foreach (range(1, 3) as $i) {
            Referral::create([
                'referrer_id' => $top->id,
                'referred_id' => $this->trainee()->id,
                'code' => $top->code,
                'commission_percent' => 7,
            ]);
        }

        Referral::create([
            'referrer_id' => $second->id,
            'referred_id' => $this->trainee()->id,
            'code' => $second->code,
            'commission_percent' => 7,
        ]);

        $rows = app(InviteLeaderboard::class)->rows();

        $this->assertSame($top->id, $rows->first()['user']->id);
        $this->assertSame(3, $rows->first()['completed']);

        $this->actingAs($top)->get(route('growth.invite.board'))
            ->assertOk()
            ->assertSee('متصدّرو الدعوات');
    }

    public function test_invite_board_card_renders_as_an_image(): void
    {
        $this->get(route('growth.og.leaderboard', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');
    }

    // ------------------------------------------------ 21.1-أ — صور OG لكلّ نوع

    public function test_og_cards_exist_for_every_link_type(): void
    {
        $course = $this->makeCourse();
        $user = $this->trainee();

        $this->get(route('growth.og.course', $course->slug))->assertOk();
        $this->get(route('growth.og.profile', $user->code))->assertOk();
        $this->get(route('growth.og.articles'))->assertOk();
        $this->get(route('growth.og.leaderboard'))->assertOk();
    }
}
