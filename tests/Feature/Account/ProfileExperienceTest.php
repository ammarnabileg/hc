<?php

namespace Tests\Feature\Account;

use App\Models\Cv;
use App\Services\Account\AchievementTracks;
use App\Services\Dashboard\DashboardStatsService;

/**
 * تاب «خبراتي» ووحدة المستوى بين البروفايل والرادار (الدستور 9 · 10 · 10.1).
 */
class ProfileExperienceTest extends AccountTestCase
{
    /**
     * ⚠️ العطل المُثبَت: الواجهة كانت تقرأ `summary` و`experiences` و`skills`
     * كمصفوفة، والمخزن يكتب `profile.summary` و`experience` و`skills` **نصًّا**
     * — فسيرةٌ كاملة كانت تظهر بالتعليم واللغات فقط، بلا خطأ ولا أثر.
     */
    public function test_experience_tab_shows_summary_and_experience_and_skills(): void
    {
        $owner = $this->trainee(['name' => 'ياسمين فؤاد', 'code' => 'UEXPTAB1']);

        Cv::create([
            'user_id' => $owner->id,
            'completion_percent' => 80,
            'data' => [
                'profile' => ['job_title' => 'محلّلة بيانات', 'company' => 'نماء', 'summary' => 'خمس سنوات في تحليل بيانات التعليم.'],
                'experience' => [['title' => 'محلّلة بيانات', 'company' => 'نماء', 'from' => '2021', 'current' => true]],
                'volunteering' => [['role' => 'منسّقة مبادرة', 'organization' => 'رسالة', 'from' => '2020']],
                'education' => [['degree' => 'بكالوريوس إحصاء', 'institution' => 'جامعة القاهرة', 'major' => 'إحصاء تطبيقيّ']],
                'courses' => [['name' => 'Power BI', 'provider' => 'Microsoft', 'date' => '2023']],
                'skills' => 'Excel, SQL, تحليل بيانات',
                'languages' => [['language' => 'العربيّة', 'level' => 'الأمّ']],
            ],
        ]);

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'experience']))
            ->assertOk()
            // النبذة والمسمّى الوظيفيّ — كانا يسقطان بصمت
            ->assertSee('خمس سنوات في تحليل بيانات التعليم.', false)
            ->assertSee('محلّلة بيانات', false)
            // الخبرة — المفتاح `experience` لا `experiences`
            ->assertSee('نماء', false)
            // المهارات — نصٌّ مفصول بفواصل لا مصفوفة
            ->assertSee('SQL', false)
            ->assertSee('تحليل بيانات', false)
            // بندا القسم 9 اللذان لم يكونا موجودَين أصلًا
            ->assertSee('منسّقة مبادرة', false)
            ->assertSee('Power BI', false)
            // وما كان يظهر وحده يبقى ظاهرًا
            ->assertSee('جامعة القاهرة', false)
            ->assertSee('العربيّة', false);
    }

    /**
     * ⚠️ العطل المُثبَت: البروفايل كان يحسب التذاكر من **الرصيد الحاليّ**
     * والدعوات من **كلّ الصفوف**، فإنفاق تذكرة يُنزِل مستواك في بروفايلك
     * ولا يُنزِله في لوحتك. الدستور 10 يقول «إجماليّ التذاكر المكتسبة»
     * و«الدعوات **الناجحة**».
     */
    public function test_profile_and_radar_agree_on_the_same_level(): void
    {
        $owner = $this->trainee(['code' => 'ULEVEL01']);

        $currency = \App\Models\Currency::firstOrCreate(
            ['code' => 'tickets'],
            ['name_ar' => 'تذاكر', 'kind' => 'reward', 'is_active' => true],
        );

        // كسب 40 تذكرة ثمّ إنفاق 30 — المستوى محسوبٌ على المكتسب لا على الباقي
        \App\Models\WalletBalance::updateOrCreate(
            ['user_id' => $owner->id, 'currency_id' => $currency->id],
            ['balance' => 10, 'lifetime_earned' => 40, 'lifetime_spent' => 30],
        );

        $tracks = app(AchievementTracks::class)->forUser($owner->fresh());
        $tickets = collect($tracks)->firstWhere('key', 'tickets');

        // 40 تذكرة مكتسبة ⟵ المستوى 3 (العتبة 15 ثمّ 40) — لا المستوى 1 بـ10 متبقّية
        $this->assertSame(40, $tickets['value']);
        $this->assertSame(3, $tickets['level']);

        $radar = app(DashboardStatsService::class)->achievementsRadar($owner->fresh());
        $axis = collect($radar['axes'])->firstWhere('label', $tickets['label']);

        $this->assertSame($tickets['level'], $axis['level']);
        $this->assertSame($tickets['value'], $axis['value']);
    }

    /** «مستويات مفتوحة بلا سقف» (10.1) — فلا حارسَ يوقف العدّ عند رقمٍ ما */
    public function test_levels_are_open_ended_with_no_ceiling(): void
    {
        $tracks = app(AchievementTracks::class);

        // العتبات المعتمَدة في 10.1 — مثبَتةٌ ولا تُمَسّ
        $this->assertSame(1, $tracks->progress(499, 500, 250)['level']);
        $this->assertSame(2, $tracks->progress(500, 500, 250)['level']);
        $this->assertSame(10, $tracks->progress(13500, 500, 250)['level']);

        // وما فوق المئتين لا يتوقّف — الحارس القديم كان يقف عند 200
        $this->assertGreaterThan(300, $tracks->progress(50_000_000, 500, 250)['level']);
    }
}
