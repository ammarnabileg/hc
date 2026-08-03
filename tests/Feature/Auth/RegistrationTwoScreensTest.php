<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\AuthController;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Setting;
use App\Models\User;
use App\Services\Geo\FlagLibrary;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 🚀 **2.5-ب و2.5-ج شاشتان منفصلتان** — والـOTP في موضعه المنصوص.
 *
 * ما كان: فورمٌ واحد يحمل حقول البندين معًا، والـOTP **صفحةً تالية**، وقائمة
 * أكواد الدول بالأعلام **غير موجودة أصلًا**. وكلّ واحدةٍ من هذه مخالفةُ نصٍّ
 * صريح، لا تفصيلَ عرضٍ:
 *
 *  · «**ب) صفحة التسجيل الأساسية:** الحقول: **الإيميل** + **رقم الموبايل** مع
 *    **Select لأكواد الدول (بالأعلام، بشكل احترافي)** + **الباسوورد**.»
 *  · «عند التأكد: يظهر **تحته** **حقل كود التحقق** + زر **«إرسال»**.»
 *  · «**ج) صفحة المعلومات (بيانات الشهادات والإفادات)**: اللقب … المحافظة …»
 */
class RegistrationTwoScreensTest extends TestCase
{
    use RefreshDatabase;
    use RegistersThroughTwoScreens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seedCountries();

        // الزائر يبدأ من شاشة الدعوة (2.5-أ)، وهي ليست موضوع هذا الاختبار
        $this->post(route('onboarding.referral.skip'));
    }

    // =================================================== الشاشتان منفصلتان

    /** ⭐ الشاشة الأولى تحمل حقول 2.5-ب **وحدها** — بلا حقلٍ من 2.5-ج */
    public function test_first_screen_carries_only_the_account_fields(): void
    {
        $page = $this->get(route('register'))->assertOk();

        foreach (['email', 'phone_iso2', 'phone_national', 'password', 'password_confirmation'] as $field) {
            $page->assertSee('name="'.$field.'"', false);
        }

        // ⛔ ولا حقلَ من صفحة المعلومات — الدمج هو ما نحرسه من العودة
        foreach (['title', 'name_ar', 'name_en', 'gender', 'country_id', 'governorate_id', 'address_line'] as $field) {
            $page->assertDontSee('name="'.$field.'"', false);
        }
    }

    /** ⭐ والشاشة الثانية تحمل حقول 2.5-ج **وحدها** بترتيبها المنصوص */
    public function test_second_screen_carries_only_the_certificate_fields_in_order(): void
    {
        $this->passFirstScreen('two.screens@test.local');

        $html = $this->get(route('register'))->assertOk()->getContent();

        foreach (['email', 'password', 'phone_national'] as $field) {
            $this->assertStringNotContainsString('name="'.$field.'"', $html);
        }

        // الترتيب حرفيًّا: اللقب ⟵ عربيّ ⟵ إنجليزيّ ⟵ النوع ⟵ الدولة ⟵ المحافظة ⟵ العنوان
        $order = ['title', 'name_ar', 'name_en', 'gender', 'country_id', 'governorate_id', 'address_line'];
        $positions = array_map(fn (string $f) => mb_strpos($html, 'name="'.$f.'"'), $order);

        foreach ($positions as $at) {
            $this->assertNotFalse($at);
        }

        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'ترتيب حقول 2.5-ج تغيّر عن ترتيب النصّ.');
    }

    /** ⛔ الشاشة الثانية لا تُفتَح بالرابط قبل اجتياز الأولى */
    public function test_second_screen_is_not_reachable_before_the_first(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('name="email"', false);
    }

    // ============================================ الـOTP تحت الإيميل لا صفحةً

    /** ⭐ حقل الرمز **في نفس الصفحة وتحت الإيميل** — لا صفحةٍ تالية (2.5-ب) */
    public function test_the_otp_field_sits_under_the_email_on_the_same_page(): void
    {
        $this->post(route('register.verify.send'), ['email' => 'inline@test.local']);

        $html = $this->get(route('register'))->assertOk()->getContent();

        $email = mb_strpos($html, 'name="email"');
        $code = mb_strpos($html, 'name="code"');
        $phone = mb_strpos($html, 'name="phone_national"');

        $this->assertNotFalse($code, 'حقل الرمز غائبٌ عن الشاشة الأولى — رجع صفحةً تالية.');
        $this->assertTrue($email < $code, 'حقل الرمز ليس **تحت** الإيميل.');
        $this->assertTrue($code < $phone, 'حقل الرمز ليس ملتصقًا بالإيميل — نزل تحت باقي الحقول.');
    }

    /** «بعد الضغط على إرسال يتحوّل لزرّ **تأكيد**» + «إعادة الإرسال بعد دقيقة بعدّاد» */
    public function test_send_turns_into_confirm_and_resend_waits_a_minute(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('data-sent="0"', false);

        $this->post(route('register.verify.send'), ['email' => 'inline@test.local']);

        $this->get(route('register'))->assertOk()
            ->assertSee('data-sent="1"', false)
            ->assertSee('data-wait="'.(int) setting('auth.otp.resend_seconds', 60).'"', false);
    }

    /** 🧬 طفرة: تخطّي الـOTP ⟵ لا حساب ولا انتقال للشاشة الثانية */
    public function test_mutation_skipping_the_otp_creates_nothing(): void
    {
        $this->post('/register', [
            'step' => AuthController::STEP_ACCOUNT,
            'email' => 'skipper@test.local',
            'phone_iso2' => $this->anyIso2(),
            'phone_national' => '1099887766',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('code');

        // ولا يُفتَح الباب الثاني: الشاشة المعروضة ما زالت الأولى
        $this->get(route('register'))->assertOk()->assertSee('name="email"', false);

        $this->passSecondScreen()->assertRedirect(route('register'));
        $this->assertDatabaseMissing('users', ['email' => 'skipper@test.local']);
    }

    /** 🧬 طفرة: بريدٌ مؤكَّد ثمّ **يُغيَّر** ⟵ التأكيد لا ينسحب على البريد الجديد */
    public function test_mutation_changing_the_email_invalidates_the_verification(): void
    {
        $this->passFirstScreen('first@test.local');

        // البريد الثاني لم يُؤكَّد قطّ — فلا يُنشِئ حسابًا بشهادة الأوّل
        $this->post('/register', [
            'step' => AuthController::STEP_ACCOUNT,
            'email' => 'second@test.local',
            'phone_iso2' => $this->anyIso2(),
            'phone_national' => '1055443322',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('users', ['email' => 'second@test.local']);
    }

    /** «تحقق الإيميل لحظيًا: صيغة صحيحة + **غير مستخدم من قبل**» (2.5-ب) */
    public function test_email_is_checked_live_for_shape_and_for_being_taken(): void
    {
        User::create([
            'name' => 'مستخدم', 'email' => 'taken@test.local', 'password' => 'secret-password',
            'code' => 'UTAKEN01', 'status' => 'active',
        ]);

        $this->getJson(route('register').'?probe=email&email=fresh@test.local')
            ->assertOk()->assertJson(['valid' => true, 'taken' => false, 'ok' => true]);

        $this->getJson(route('register').'?probe=email&email=taken@test.local')
            ->assertOk()->assertJson(['valid' => true, 'taken' => true, 'ok' => false]);

        $this->getJson(route('register').'?probe=email&email=not-an-email')
            ->assertOk()->assertJson(['valid' => false, 'ok' => false]);
    }

    /** ⛔ ولا رمزَ يُبعَث لبريدٍ مستعمَل — «لا نرسل رمزًا لبريد مرفوض أصلًا» */
    public function test_no_code_is_sent_to_an_already_used_email(): void
    {
        User::create([
            'name' => 'مستخدم', 'email' => 'used@test.local', 'password' => 'secret-password',
            'code' => 'UUSED001', 'status' => 'active',
        ]);

        $this->post(route('register.verify.send'), ['email' => 'used@test.local'])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('security_otp_codes', ['email' => 'used@test.local']);
    }

    // ================================================ أكواد الدول بالأعلام

    /** ⭐ «Select لأكواد الدول (**بالأعلام**)» — علمٌ لكلّ دولة، ومرسومٌ SVG */
    public function test_the_dial_code_select_draws_a_flag_for_every_country(): void
    {
        $html = $this->get(route('register'))->assertOk()->getContent();

        $expected = Country::query()->where('is_active', true)
            ->whereNotNull('phone_code')->where('phone_code', '!=', '')->count();

        $this->assertSame($expected, substr_count($html, '<svg class="flag"'));
        $this->assertGreaterThan(0, $expected);

        // وعرض الحقل إعدادٌ منصوص في 12.7-د (110px) لا رقمٌ محروق
        $this->assertStringContainsString(
            'width: '.(int) setting('countries.registration.phone_code_width', 110).'px',
            $html,
        );
    }

    /**
     * 🔒 **الأعلام من داخل الحزمة لا من شبكة.**
     *
     * قاعدةٌ كُسِرت قبلًا في هذا المشروع (خطّ ورقة السيرة الذاتيّة) — فتُحرَس
     * هنا بثلاثة أوجه: لا `<img>` · لا `url(http…)` · ولا **إيموجي علم** يرسمه
     * نظام التشغيل (ويندوز لا يرسمه أصلًا).
     */
    public function test_flags_never_touch_the_network(): void
    {
        $html = $this->get(route('register'))->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, '<img'));
        $this->assertSame(0, preg_match_all('/url\(\s*[\'"]?https?:/i', $html));
        $this->assertSame(0, preg_match_all('/[\x{1F1E6}-\x{1F1FF}]{2}/u', $html));

        // وكلّ `url(` في الصفحة إشارةٌ داخليّة (`#…`) لا عنوانٌ خارجيّ
        preg_match_all('/url\(([^)]*)\)/', $html, $matches);

        foreach ($matches[1] as $reference) {
            $this->assertStringStartsWith('#', trim($reference, '\'" '));
        }
    }

    /** ولا دولةَ بلا علم: الجدول يغطّي كلّ ما في القاعدة (وإلّا خرجت لوحة حروف) */
    public function test_every_country_in_the_database_has_a_drawn_flag(): void
    {
        $flags = app(FlagLibrary::class);
        $missing = Country::query()->pluck('iso2')->reject(fn ($iso2) => $flags->has((string) $iso2));

        $this->assertSame([], $missing->values()->all());
    }

    // ================================================ المحافظة مبنيّة على الدولة

    /** «المحافظة Select **مبني على الدولة**، يُملأ **تلقائيًّا**» (2.5-ج) */
    public function test_governorates_are_fetched_for_the_chosen_country_only(): void
    {
        $egypt = Country::where('iso2', 'EG')->firstOrFail();
        $saudi = Country::where('iso2', 'SA')->firstOrFail();

        $this->passFirstScreen('geo@test.local');

        $mine = $this->getJson(route('register').'?governorates='.$egypt->id)->assertOk()->json('governorates');
        $names = array_column($mine, 'name');

        $this->assertContains('القاهرة', $names);
        $this->assertNotContains('الرياض', $names);

        $theirs = $this->getJson(route('register').'?governorates='.$saudi->id)->assertOk()->json('governorates');
        $this->assertContains('الرياض', array_column($theirs, 'name'));
    }

    /**
     * ⛔ **المحافظة لا تُخفى أبدًا** (قاعدة مالك صريحة) — فقائمة الاختيار
     * تعرضها ولو أُطفِئ `is_active`، وإلّا صار ارتباط المستخدم بها ارتباطًا بشبح.
     */
    public function test_a_governorate_is_never_dropped_from_the_list(): void
    {
        $egypt = Country::where('iso2', 'EG')->firstOrFail();
        $cairo = Governorate::where('country_id', $egypt->id)->firstOrFail();
        $cairo->forceFill(['is_active' => false])->save();

        $this->passFirstScreen('never.hidden@test.local');

        $names = array_column(
            $this->getJson(route('register').'?governorates='.$egypt->id)->assertOk()->json('governorates'),
            'name',
        );

        $this->assertContains($cairo->name_ar, $names);
    }

    // ================================================ إسناد ODbL عند الاستهلاك

    /** ⚖️ «الرخصة ODbL v1.0 ⇒ نضيف **إسنادًا**» — حيث تُستهلَك البيانات (2.5-ج) */
    public function test_the_odbl_attribution_shows_on_both_public_screens(): void
    {
        $attribution = $this->attributionText();

        $this->get(route('register'))->assertOk()
            ->assertSee('data-odbl-attribution', false)
            ->assertSee($attribution, false);

        $this->passFirstScreen('odbl@test.local');

        $this->get(route('register'))->assertOk()
            ->assertSee('data-odbl-attribution', false)
            ->assertSee($attribution, false);
    }

    /** 🧬 طفرة: إفراغ نصّ الإسناد ⟵ يختفي الوسم (فالاختبار يقيس المعروض لا وجود ملفّ) */
    public function test_mutation_emptying_the_attribution_removes_it_from_the_page(): void
    {
        $this->attributionText();

        $this->get(route('register'))->assertOk()->assertSee('data-odbl-attribution', false);

        $this->writeSetting('countries.attribution', '');

        $this->get(route('register'))->assertOk()->assertDontSee('data-odbl-attribution', false);
    }

    // ================================================ الرقم الكامل بكود دولته

    /** الرقم يُخزَّن بكود دولته: «+20» + المحلّيّ بلا صفرٍ ولا مسافات */
    public function test_the_phone_is_stored_with_its_dial_code(): void
    {
        $this->passFirstScreen('dial@test.local', ['phone_iso2' => 'EG', 'phone_national' => '010 1234 5678']);
        $this->passSecondScreen();

        $this->assertSame('+201012345678', User::where('email', 'dial@test.local')->value('phone'));
    }

    // ------------------------------------------------------------------ أدوات

    /** نصّ الإسناد كما يقرؤه القالب — ويُثبَّت صفًّا فيقيس الاختبار المعروض لا الافتراضيّ */
    private function attributionText(): string
    {
        $text = 'بيانات الدول والمحافظات من dr5hn/countries-states-cities-database — برخصة ODbL v1.0.';
        $this->writeSetting('countries.attribution', $text);

        return $text;
    }

    private function writeSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => 'countries', 'label_ar' => $key, 'type' => 'text',
            'default_value' => $value, 'value' => $value,
        ]);

        Cache::forget('settings');
    }

    private function seedCountries(): void
    {
        $rows = [
            ['EG', 'مصر', 'Egypt', '20', ['القاهرة' => 'Cairo', 'الجيزة' => 'Giza']],
            ['SA', 'السعوديّة', 'Saudi Arabia', '966', ['الرياض' => 'Riyadh']],
        ];

        foreach ($rows as $index => [$iso2, $ar, $en, $dial, $governorates]) {
            $country = Country::updateOrCreate(['iso2' => $iso2], [
                'name_ar' => $ar, 'name_en' => $en, 'phone_code' => $dial,
                'timezone' => 'Africa/Cairo', 'sort_order' => $index + 1, 'is_active' => true,
            ]);

            foreach ($governorates as $nameAr => $nameEn) {
                Governorate::updateOrCreate(
                    ['country_id' => $country->id, 'name_en' => $nameEn],
                    ['name_ar' => $nameAr, 'is_active' => true],
                );
            }
        }
    }
}
