<?php

namespace Tests\Feature\Growth;

use App\Models\Country;
use App\Models\Course;
use App\Models\Currency;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Referral;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Admin\AccountApproval;
use App\Services\Admin\System\StatsService;
use App\Services\Ads\Consent;
use App\Services\Growth\AcquisitionSource;
use App\Services\Security\OtpService;
use Carbon\CarbonImmutable;
use Database\Seeders\StoreDemoSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ⭐ **سلسلة الاكتساب متّصلة** (21.2-ح): «لوحة مصادر الاكتساب في الإحصائيّات:
 *    **المصدر ⟵ التسجيل ⟵ التفعيل ⟵ الشراء** — فلا يُصرَف على قناةٍ لا نعرف عائدها».
 *
 * ⛔ **وقيدها الحاكم** (21.3-د · 2.9): «الرفض يوقف البكسل وأحداث الخادم لهذا
 *    المستخدم **فعليًّا** — لا شكليًّا». فالالتقاط كلّه تحت غرض **«قياس داخليّ»**،
 *    ورحلةُ الرفض **نصف الدليل** لا زينة.
 */
class AcquisitionChainTest extends GrowthTestCase
{
    private const LANDING = '/?utm_source=facebook&utm_medium=cpc&utm_campaign=eid&utm_content=video1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedGrowth();
        (new StoreDemoSeeder)->settings();
        Cache::forget('settings');

        // البانر لا يظهر والتتبّع مطفأ كلّيًّا (21.3-و) — فالمفتاح الأعلى يُفتَح أوّلًا
        $this->setSetting('ads.tracking.enabled', '1', 'bool');
    }

    // ================================================ الرحلة الكاملة (بموافقة)

    /**
     * ⭐ الرحلة الأربع وصلات بطلبات HTTP حقيقيّة:
     * زيارة موسومة ⟵ موافقة ⟵ تسجيل ⟵ تفعيل ⟵ شراء — والمصدر باقٍ ومنسوب عند كلّ خطوة.
     */
    public function test_the_whole_chain_survives_from_the_first_visit_to_the_purchase(): void
    {
        // (0) زيارةٌ برابطٍ يحمل مصدرًا — **قبل** الموافقة لا يُلتقَط شيء
        $this->get(self::LANDING)->assertOk();
        $this->assertNull(session(AcquisitionSource::SESSION_KEY));

        // (1) الموافقة على **القياس الداخليّ وحده** — والكوكي تُحمَل كما يحملها المتصفّح
        $this->consentTo(['analytics']);

        // (2) الزيارة الموسومة نفسها بعد الموافقة ⟵ المصدر يُلتقَط الآن
        $this->get(self::LANDING)->assertOk();

        $this->assertSame([
            'utm_source' => 'facebook',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'eid',
            'utm_content' => 'video1',
        ], session(AcquisitionSource::SESSION_KEY));

        // (3) التسجيل — الفورم بلا query وبلا حقول utm، والمصدر يعبر رغم ذلك
        $referrer = $this->trainee(['code' => 'REFCODE9']);
        $this->get(route('referral.join', ['ref' => $referrer->code]))->assertRedirect(route('register'));
        $this->get(route('register'))->assertOk();

        $this->register();

        $user = User::query()->where('email', 'mostafa.acq@test.local')->firstOrFail();

        // ⭐ قراءةٌ من القاعدة: المصدر مثبَّت على الحساب نفسه
        $this->assertSame('facebook', $user->acquisition_utm_source);
        $this->assertSame('cpc', $user->acquisition_utm_medium);
        $this->assertSame('eid', $user->acquisition_utm_campaign);
        $this->assertSame('video1', $user->acquisition_utm_content);
        $this->assertSame('pending', $user->status);

        // وسطر الإحالة يحمل المصدر — لا `NULL` كما كان (0 من 10)
        $referral = Referral::query()->where('referred_id', $user->id)->firstOrFail();
        $this->assertSame('facebook', $referral->utm_source);
        $this->assertSame('cpc', $referral->utm_medium);
        $this->assertSame('eid', $referral->utm_campaign);

        // (4) التفعيل باعتماد إداريّ (2.5-د) — والمصدر يبقى بعده
        app(AccountApproval::class)->approve($this->admin(), $user);
        $user->refresh();

        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->activated_at);
        $this->assertSame('facebook', $user->acquisition_utm_source);

        // (5) الشراء بطلب HTTP حقيقيّ من المتجر — والطلب يُنسَب لصاحب المصدر
        $course = $this->purchasableCourse();
        $this->credit($user, 1000);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'course',
            'slug' => $course->slug,
            'refund_ack' => 1,
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('paid', $order->status);

        // (6) ⭐ اللوحة تقرأ السلسلة كاملةً — لا `registered=0` بعد اليوم
        $row = $this->acquisitionRowFor('facebook');

        $this->assertSame(1, $row['registered']);
        $this->assertSame(1, $row['activated']);
        $this->assertSame(1, $row['purchased']);
    }

    // ================================================ الرحلة المقابلة (بلا موافقة)

    /** ⛔ الرفض يوقف الالتقاط **فعليًّا**: لا جلسة ولا عمود ولا سطر إحالة موسوم */
    public function test_the_same_journey_captures_nothing_when_tracking_is_refused(): void
    {
        $this->consentTo([], Consent::REJECTED);

        $this->get(self::LANDING)->assertOk();
        $this->assertNull(session(AcquisitionSource::SESSION_KEY));

        $referrer = $this->trainee(['code' => 'REFCODE8']);
        $this->get(route('referral.join', ['ref' => $referrer->code]))->assertRedirect(route('register'));

        // ⭐ حتّى لو حمل الفورم نفسه الوسم في الـquery — الرفض أعلى من الرابط
        $this->register('?utm_source=facebook&utm_medium=cpc');

        $user = User::query()->where('email', 'mostafa.acq@test.local')->firstOrFail();

        $this->assertNull($user->acquisition_utm_source);
        $this->assertNull($user->acquisition_utm_medium);
        $this->assertNull(Referral::query()->where('referred_id', $user->id)->firstOrFail()->utm_source);

        // ولا صفَّ في اللوحة أصلًا — فلا مصدر ولا زيارة موسومة
        $this->assertSame([], $this->acquisitionRows());
    }

    /** والصمت ليس موافقة: من لم يختر شيئًا لا يُقاس له شيء (21.3-د) */
    public function test_silence_is_not_consent(): void
    {
        $this->get(self::LANDING)->assertOk();

        $this->register();

        $this->assertNull(User::query()->where('email', 'mostafa.acq@test.local')->firstOrFail()->acquisition_utm_source);
    }

    // ================================================ 21.3-د — «قياس داخليّ» غرضٌ قائم بذاته

    /** ⭐ من وافق على **القياس وحده** يُقاس له — ولا يُشغَّل له بكسل إعلانيّ */
    public function test_analytics_only_consent_measures_acquisition_without_any_ad_event(): void
    {
        $this->setSetting('ads.pixel.meta_id', '123456789');
        $this->consentTo(['analytics']);

        $this->get(self::LANDING)->assertOk()->assertDontSee('fbevents.js', false);

        $this->register();

        $user = User::query()->where('email', 'mostafa.acq@test.local')->firstOrFail();

        $this->assertSame('facebook', $user->acquisition_utm_source);   // ✓ قِيس له
        $this->assertSame(0, TrackingEvent::query()->count());          // ⛔ ولا حدث إعلانيّ واحد
    }

    /** ⛔ ومن رفض القياس **لا يُقاس ولو وافق على الإعلان** — لا رهينةَ بعد اليوم */
    public function test_ads_only_consent_does_not_measure_acquisition(): void
    {
        $this->setSetting('ads.pixel.meta_id', '123456789');
        $this->consentTo(['ads']);

        $this->get(self::LANDING)->assertOk()->assertSee('fbevents.js', false);
        $this->assertNull(session(AcquisitionSource::SESSION_KEY));

        $this->register();

        $user = User::query()->where('email', 'mostafa.acq@test.local')->firstOrFail();

        $this->assertNull($user->acquisition_utm_source);

        // والسلسلة في اللوحة لا تنسب له شيئًا — ولو ظهرت زياراته الإعلانيّة
        foreach ($this->acquisitionRows() as $row) {
            $this->assertSame(0, $row['registered']);
        }
    }

    /** ⭐ ونداء `allows('analytics')` صار له قارئ — والمفتاح الأعلى يوقفه كغيره (21.3-و) */
    public function test_the_master_switch_stops_acquisition_capture_too(): void
    {
        $this->setSetting('ads.tracking.enabled', '0', 'bool');
        $this->consentTo(['analytics']);

        $this->get(self::LANDING)->assertOk();

        $this->assertNull(session(AcquisitionSource::SESSION_KEY));
        $this->assertFalse(app(Consent::class)->allowsAnalytics());
    }

    /** وتفعيل/إيقاف الحلقة على حدة (21.1-هـ · 2.13) */
    public function test_the_loop_can_be_switched_off_from_the_panel(): void
    {
        $this->setSetting('growth.acquisition.enabled', '0', 'bool');
        $this->consentTo(['analytics']);

        $this->get(self::LANDING)->assertOk();

        $this->assertNull(session(AcquisitionSource::SESSION_KEY));
    }

    /** «أوّل مصدرٍ يفوز» افتراضًا — ولا يسرقه رابطٌ لاحق (وقابلٌ للقلب من اللوحة) */
    public function test_first_touch_wins_by_default_and_the_owner_can_flip_it(): void
    {
        $this->consentTo(['analytics']);

        $this->get(self::LANDING)->assertOk();
        $this->get('/?utm_source=twitter&utm_medium=post')->assertOk();

        $this->assertSame('facebook', session(AcquisitionSource::SESSION_KEY)['utm_source']);

        $this->setSetting('growth.acquisition.attribution', 'last');
        $this->get('/?utm_source=twitter&utm_medium=post')->assertOk();

        $this->assertSame('twitter', session(AcquisitionSource::SESSION_KEY)['utm_source']);
    }

    // ------------------------------------------------------------------ أدوات

    /**
     * موافقة عبر **المسار الحقيقيّ** ثمّ حمل الكوكي كما يحملها المتصفّح —
     * فالزائر ليس له حساب، وحالته لا تعيش إلّا في الكوكي (21.3-د).
     */
    private function consentTo(array $scopes, string $choice = Consent::CUSTOM): void
    {
        $response = $this->from(self::LANDING)->post(route('consent.tracking'), [
            'choice' => $scopes === [] ? $choice : Consent::CUSTOM,
            'scopes' => $scopes,
        ]);

        $jar = [];

        foreach ($response->headers->getCookies() as $cookie) {
            $jar[$cookie->getName()] = $cookie->getValue();
        }

        $this->withUnencryptedCookies($jar);
    }

    /**
     * ⭐ التسجيل بمساره الحقيقيّ كاملًا (2.5-ب): فورم ⟵ إرسال الرمز ⟵ تأكيده ⟵
     * إعادة تشغيل التسجيل. ولا نقفز فوق خطوةٍ منه، فالخطوة المقفوزة تخفي عيبًا.
     */
    private function register(string $query = ''): void
    {
        $payload = $this->registrationPayload();

        $this->post(route('register').$query, $payload)
            ->assertRedirect(route('register.verify'));

        $this->post(route('register.verify.send'))->assertRedirect();

        $code = Crypt::decryptString((string) DB::table('security_otp_codes')
            ->where('email', $payload['email'])
            ->where('purpose', OtpService::PURPOSE_REGISTER)
            ->value('code'));

        $this->post(route('register.verify.confirm'), ['code' => $code])->assertRedirect();
    }

    private function registrationPayload(): array
    {
        $country = Country::query()->where('is_active', true)->first();
        $governorate = Governorate::query()->where('is_active', true)->first();

        return [
            'title' => 'المهندس',
            'name_ar' => 'مصطفى سيّد كامل',
            'name_en' => 'Mostafa Sayed Kamel',
            'gender' => 'male',
            'country_id' => $country?->id,
            'governorate_id' => $governorate?->id,
            'address_line' => 'شارع الجمهوريّة',
            'email' => 'mostafa.acq@test.local',
            'phone' => '01000000123',
            'password' => 'secret-password-9',
            'password_confirmation' => 'secret-password-9',
        ];
    }

    private function admin(): User
    {
        $admin = $this->trainee(['code' => 'ADMIN'.Str::upper(Str::random(3))]);
        $admin->assignRole('super_admin');

        return $admin->fresh();
    }

    private function purchasableCourse(): Course
    {
        $course = $this->makeCourse();
        $course->forceFill(['price_coins' => 200])->save();

        return $course->fresh();
    }

    private function credit(User $user, float $coins): void
    {
        WalletBalance::updateOrCreate(
            ['user_id' => $user->id, 'currency_id' => Currency::query()->where('code', 'coins')->firstOrFail()->id],
            ['balance' => $coins],
        );
    }

    /** @return array<int,array<string,mixed>> */
    private function acquisitionRows(): array
    {
        $now = CarbonImmutable::now();

        return app(StatsService::class)->data('acquisition', [
            'from' => $now->subDays(30)->startOfDay(),
            'to' => $now->endOfDay(),
            'prev_from' => $now->subDays(60)->startOfDay(),
            'prev_to' => $now->subDays(30)->startOfDay(),
            'days' => 30,
            'compare' => false,
        ])['rows'];
    }

    /** @return array<string,mixed> */
    private function acquisitionRowFor(string $source): array
    {
        foreach ($this->acquisitionRows() as $row) {
            if ($row['source'] === $source) {
                return $row;
            }
        }

        $this->fail("لا صفَّ للمصدر «{$source}» في لوحة مصادر الاكتساب.");
    }
}
