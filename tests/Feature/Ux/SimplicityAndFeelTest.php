<?php

namespace Tests\Feature\Ux;

use App\Models\PositiveMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Engagement\SocialProof;
use App\Services\Growth\ProfileCompletion;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * البساطة أوّلًا (2.15) وطبقة الإحساس (2.17) والمبادئ السيكولوجيّة (2.9).
 *
 * الاختبارات هنا تحرس **الأعلام الحيّة**: عَلَمٌ بلا قارئ يحوّل «الإخفاء
 * والتدرّج» إلى **حذف** — وهو انقلاب القاعدة على نفسها.
 */
class SimplicityAndFeelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    // ------------------------------------------------------------ 2.6-ب: التذكرة

    /** ⭐ زرّ التذكرة يخضع للنسبة فعلًا: نسبة صفر ⟵ لا يظهر أبدًا (2.6-ب) */
    public function test_ticket_button_actually_obeys_the_configured_chance(): void
    {
        $user = $this->trainee();

        // الأيقونة مضمونة الظهور (100%) حتى نعزل متغيّر **زرّ التذكرة** وحده
        $this->setSetting('engagement.positive.enabled', '1', 'bool');
        $this->setSetting('engagement.positive.icon_chance_percent', '100', 'number');
        $this->setSetting('engagement.positive.ticket_chance_percent', '0', 'number');

        PositiveMessage::create([
            'context' => 'any',
            'body_ar' => 'إنت أحسن من إمبارح.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // خمس محاولات: لو الزرّ لا يخضع للسحبة سيظهر في كلّها كما أثبت التدقيق
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->get(route('dashboard'))
                ->assertOk()
                ->assertSee('إنت أحسن من إمبارح.')
                ->assertDontSee(route('positive.ticket'), false);
        }

        // وبنسبة 100% يظهر الزرّ — فالنسبة قارئها حيّ في الاتّجاهين
        $this->setSetting('engagement.positive.ticket_chance_percent', '100', 'number');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('positive.ticket'), false);
    }

    /** العنصران العائمان على **يمين** الشاشة نصًّا (2.6-أ و2.6-ب) */
    public function test_floating_widgets_sit_on_the_physical_right_edge(): void
    {
        $this->actingAs($this->trainee())->get(route('dashboard'))
            ->assertOk()
            // `inset-inline-end` في صفحة RTL يقع على اليسار — فلا يُستعمَل هنا
            ->assertSee('right: 1rem', false);
    }

    // -------------------------------------------------- 2.15: وضعا مبسّط ومتقدّم

    /** ⭐ الوضع المتقدّم **يغيّر الناتج** فعلًا — لا عَلَم ميّت (2.15-أ-9) */
    public function test_advanced_mode_changes_the_rendered_output(): void
    {
        $user = $this->trainee();

        $simple = $this->actingAs($user)->get(route('complaints.index'))->assertOk()->getContent();

        $user->forceFill(['simple_mode' => false, 'advanced_mode' => true])->save();

        $advanced = $this->actingAs($user->fresh())->get(route('complaints.index'))->assertOk()->getContent();

        $this->assertNotSame($simple, $advanced, 'التبديل بين الوضعين لا يغيّر الناتج — العَلَم بلا قارئ.');

        // حدّ الفلاتر الظاهرة يُفرَض في المبسّط ويُرفَع في المتقدّم
        $this->assertStringContainsString('data-filters-cap="', $simple);
        $this->assertStringNotContainsString('data-filters-cap="', $advanced);
        $this->assertStringContainsString('data-filters-mode="advanced"', $advanced);
    }

    /** السويتش حاضر في كلّ صفحة، ويُحفَظ لكلّ مستخدم بعد الضغط */
    public function test_advanced_switch_is_on_every_page_and_is_persisted(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-advanced-toggle="0"', false);

        $this->actingAs($user)->post(route('ui.mode.toggle'))->assertRedirect();

        $this->assertTrue((bool) $user->fresh()->advanced_mode);
        $this->assertFalse((bool) $user->fresh()->simple_mode);

        $this->actingAs($user->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-advanced-toggle="1"', false);
    }

    /** حدّ الفلاتر الظاهرة يقرأ الإعداد نفسه لا رقمًا محروقًا (2.13 · 2.15-أ-4) */
    public function test_visible_filters_limit_reads_the_setting(): void
    {
        $this->setSetting('ux.filters.max_visible', '2', 'number');

        $this->actingAs($this->trainee())->get(route('complaints.index'))
            ->assertOk()
            ->assertSee('data-filters-cap="2"', false);
    }

    /** ⭐ الحالة الفارغة = سطر واحد + **زرّ واحد** داخلها (2.15-د) */
    public function test_empty_state_carries_its_own_single_button(): void
    {
        $response = $this->actingAs($this->trainee())->get(route('complaints.index'))->assertOk();

        $html = $response->getContent();
        $start = strpos($html, 'مفيش تذاكر لسّه');
        $this->assertNotFalse($start, 'الحالة الفارغة مش ظاهرة.');

        // الزرّ داخل كارت الحالة الفارغة نفسه لا سطرًا منفصلًا تحته
        $card = substr($html, $start, 600);
        $this->assertStringContainsString('data-modal-open="new-ticket"', $card);
    }

    // ------------------------------------------------------------ 2.9: المبادئ

    /** ⭐ تقدّم مُهدى: المؤشّر يبدأ من نقطة منجَزة لا من صفر (2.9-2) */
    public function test_profile_completion_starts_from_an_endowed_point(): void
    {
        $this->setSetting('growth.profile_completion.endowed_percent', '20', 'number');

        $service = app(ProfileCompletion::class);
        $empty = $this->trainee();

        $this->assertGreaterThanOrEqual(20, $service->percent($empty));
        // ومع ذلك 100% لا تُبلَغ إلّا بامتلاء الحقول فعلًا — فلا مكافأة بلا عمل
        $this->assertLessThan(100, $service->percent($empty));
    }

    /** ⭐ الدليل الاجتماعيّ يظهر بحدوده: فوق الحدّ رقم، وتحته تأطير ريادة (2.9-7) */
    public function test_social_proof_respects_its_approved_floors(): void
    {
        $proof = app(SocialProof::class);

        // الحدود المعتمَدة نصًّا: 20 · 10 · 3 · 20
        $this->assertSame(20, $proof->floor('lesson'));
        $this->assertSame(10, $proof->floor('club_5am'));
        $this->assertSame(3, $proof->floor('war'));
        $this->assertSame(20, $proof->floor('leaderboard'));

        $under = $proof->frame('lesson', 19);
        $this->assertTrue($under['lead']);
        $this->assertStringContainsString('كن أوّل', $under['text']);
        $this->assertStringNotContainsString('19', $under['text']);

        $over = $proof->frame('lesson', 20);
        $this->assertFalse($over['lead']);
        $this->assertStringContainsString('20', $over['text']);

        $club = $proof->frame('club_5am', 9);
        $this->assertTrue($club['lead']);
        $this->assertFalse($proof->frame('club_5am', 10)['lead']);

        $war = $proof->frame('war', 2);
        $this->assertTrue($war['lead']);
        $this->assertStringContainsString('كن أوّل محارب', $war['text']);
    }

    /** المقارنة القريبة: النسبة تُكتَم تحت حدّ 20، والفارق حقيقيّ (2.9-5) */
    public function test_near_comparison_hides_a_misleading_percentage(): void
    {
        $proof = app(SocialProof::class);

        $this->assertFalse($proof->betterThanPercent(2, 3)['show']);
        $this->assertTrue($proof->betterThanPercent(2, 40)['show']);

        $gap = $proof->gapToNext(500, 900, 'سلمى');
        $this->assertTrue($gap['show']);
        $this->assertSame(401, $gap['gap']);
        $this->assertStringContainsString('سلمى', $gap['text']);

        // بلا منافس أمامي لا نخترع رقمًا (بلا Dark Patterns)
        $this->assertFalse($proof->gapToNext(500, null, null)['show']);
    }

    /** الحدود كلّها إعدادات لا أرقام محروقة (2.13) */
    public function test_social_proof_floors_come_from_settings(): void
    {
        $this->setSetting('engagement.social_proof.lesson.min', '2', 'number');

        $this->assertSame(2, app(SocialProof::class)->floor('lesson'));
        $this->assertFalse(app(SocialProof::class)->frame('lesson', 2)['lead']);
    }

    // ------------------------------------------------------------- أدوات

    private function trainee(array $attributes = []): User
    {
        $user = User::create([
            'name' => $attributes['name'] ?? 'متدرّب تجريبيّ',
            'email' => $attributes['email'] ?? Str::lower(Str::random(8)).'@test.local',
            'phone' => $attributes['phone'] ?? '+2010'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'code' => $attributes['code'] ?? 'U'.Str::upper(Str::random(7)),
            'status' => $attributes['status'] ?? 'active',
        ] + array_diff_key($attributes, array_flip(['name', 'email', 'phone', 'code', 'status'])));

        $user->assignRole('trainee');

        return $user->fresh();
    }

    private function setSetting(string $key, string $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => 'ux',
            'label_ar' => $key,
            'type' => $type,
            'default_value' => $value,
            'value' => $value,
        ]);

        Cache::forget('settings');
    }
}
