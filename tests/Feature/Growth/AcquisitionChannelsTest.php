<?php

namespace Tests\Feature\Growth;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\User;
use App\Services\Certificates\CertificateSignature;
use App\Services\Growth\ContentKit;
use App\Services\Growth\UtmBuilder;
use Illuminate\Support\Str;

/** قنوات الاكتساب (21.2): UTM موحّد · الكارت الأسبوعيّ · حزمة المتطوّعين · شاشة الإدارة */
class AcquisitionChannelsTest extends GrowthTestCase
{
    // ------------------------------------------------ 21.2-ح — UTM موحّد

    public function test_utm_is_added_to_every_generated_link(): void
    {
        $tagged = app(UtmBuilder::class)->tag('https://hc.test/articles/x', 'article', 'blog');

        $this->assertStringContainsString('utm_source=platform', $tagged);
        $this->assertStringContainsString('utm_medium=article', $tagged);
        $this->assertStringContainsString('utm_campaign=blog', $tagged);
    }

    /** رابطٌ جاء موسومًا من حملة حقيقيّة لا نعيد وسمه */
    public function test_existing_utm_parameters_win(): void
    {
        $tagged = app(UtmBuilder::class)->tag('https://hc.test/x?utm_source=fb', 'article');

        $this->assertStringContainsString('utm_source=fb', $tagged);
        $this->assertStringNotContainsString('utm_source=platform', $tagged);
    }

    public function test_utm_can_be_switched_off_entirely(): void
    {
        $this->setSetting('growth.utm.enabled', '0', 'bool');

        $this->assertSame('https://hc.test/x', app(UtmBuilder::class)->tag('https://hc.test/x', 'article'));
    }

    // ------------------------------------------------ 21.2-د/هـ — الكارت وحزمة المتطوّعين

    public function test_weekly_card_rotates_by_the_configured_period(): void
    {
        $kit = app(ContentKit::class);

        $today = $kit->currentCard(now());
        $sameBucket = $kit->currentCard($today['from']->copy()->addHours(1));
        $nextBucket = $kit->currentCard($today['to']->copy()->addDay());

        $this->assertSame($today['tip'], $sameBucket['tip']);
        $this->assertNotSame($today['index'], $nextBucket['index']);
    }

    public function test_weekly_card_image_is_public(): void
    {
        $this->get(route('growth.kit.weekly-card'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');
    }

    public function test_content_kit_gives_the_volunteer_a_tagged_link_and_ready_scripts(): void
    {
        $user = $this->trainee();

        $response = $this->actingAs($user)->get(route('growth.kit.index'))->assertOk();

        $this->assertStringContainsString('offer='.$user->code, $response->getContent());
        $this->assertStringContainsString('utm_medium=volunteer_kit', $response->getContent());

        $scripts = app(ContentKit::class)->scripts('https://hc.test/r');
        $this->assertStringContainsString('https://hc.test/r', $scripts[0]['body']);
    }

    // ------------------------------------------------ 21.1-أ — [احصل على شهادتك]

    public function test_verification_page_offers_to_get_your_own_certificate(): void
    {
        $holder = User::create([
            'name' => 'الحائز',
            'email' => Str::random(8).'@test.local',
            'password' => 'secret-password',
            'code' => 'U'.Str::upper(Str::random(7)),
            'status' => 'active',
        ]);

        $type = CertificateType::create([
            'key' => 'course-'.Str::lower(Str::random(4)),
            'name_ar' => 'شهادة إتمام',
            'name_en' => 'Completion',
        ]);

        $certificate = Certificate::create([
            'code' => 'HC-'.Str::upper(Str::random(8)),
            'hash' => '',
            'user_id' => $holder->id,
            'certificate_type_id' => $type->id,
            'status' => 'valid',
            'issued_at' => now(),
        ]);

        /*
         | ⭐ الصفّ المصنوع باليد يحتاج **توقيعه الحقيقيّ** (8.1 · 12.5-هـ): صفحة
         | التحقّق صارت تُعيد اشتقاق التوقيع وتقارنه، وما لا يطابق لا يُعرَض كوثيقة
         | ولا يحمل دعوة «احصل على شهادتك» — فقناة الاكتساب تُبنى على شهادةٍ مثبَتة
         | لا على صفٍّ عشوائيّ. والختم من **مصدر التوقيع الواحد** لا بمعادلةٍ منسوخة.
         */
        app(CertificateSignature::class)->seal($certificate);

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee('احصل على شهادتك')
            ->assertSee('utm_medium=certificate', false);
    }

    // ------------------------------------------------ 2.13 — شاشة إدارة لكلّ إعداد

    public function test_growth_admin_screen_renders_and_saves_a_setting(): void
    {
        $this->seedGrowth();

        $admin = $this->trainee();
        $admin->assignRole('super_admin');

        $this->actingAs($admin->fresh())->get(route('admin.growth.index'))
            ->assertOk()
            ->assertSee('حلقات النموّ');

        $this->actingAs($admin->fresh())
            ->post(route('admin.growth.setting.save'), [
                'key' => 'growth.invite_board.size',
                'value' => '5',
            ])
            ->assertOk();

        // ⛔ ولا يقبل مفتاحًا خارج مجال النموّ
        $this->actingAs($admin->fresh())
            ->post(route('admin.growth.setting.save'), ['key' => 'system.timezone', 'value' => 'UTC'])
            ->assertStatus(422);
    }
}
