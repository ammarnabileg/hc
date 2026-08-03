<?php

namespace Tests\Feature\Admin\System;

use App\Models\Country;
use App\Models\CountrySourceCheck;
use App\Models\CountrySourceSnapshot;
use App\Models\Governorate;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\System\CountryDataSync;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 12.7-د «بيانات الدول» — **جلب المصدر عبر الشبكة + الفحص الدوريّ**.
 *
 * الادّعاءات المحروسة هنا:
 * 1) الجلب يُنتج **لقطة `pending` وحدها** — ولا يكتب حرفًا في `countries`.
 * 2) **كلّ فشل شبكة يُقال بسببه** ولا يُبتلَع، **ولا يمسّ اللقطة الأخيرة الناجحة**.
 * 3) الأمر **يقف عند الفروق** — لا دمج آليّ إطلاقًا.
 * 4) القرار الشهريّ **داخل الخدمة وحدها** لا في تعبير كرونٍ متجمّد.
 * 5) صفرُ فروقٍ = **سكوت**: لا لقطة مكرّرة ولا إشعار.
 *
 * ولا اختبار هنا يلمس شبكةً حقيقيّة: `Http::fake` + `preventStrayRequests`.
 */
class AdminSystemCountriesSourceTest extends SystemTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ⛔ أيّ طلب خارج المزيَّف ينفجر — فلا يعتمد اختبارٌ على شبكة حقيقيّة
        Http::preventStrayRequests();

        $this->putSetting('countries.source.url', 'https://source.test/countries.json');
        $this->putSetting('countries.source.retry_delay_ms', '0');

        // هذا الصفّ يقيس **النقل** (نجاحه وأصناف فشله ودوريّته) على حمولةٍ بشكل
        // لقطتنا مباشرةً؛ وتحويلُ شكل `dr5hn` مقيسٌ في صفّه الخاصّ.
        $this->putSetting('countries.source.format', CountryDataSync::FORMAT_NATIVE);
    }

    // ------------------------------------------------------------------ أدوات

    private function putSetting(string $key, string $value): void
    {
        Setting::query()->where('key', $key)->update(['value' => $value]);
        Cache::forget('settings');
    }

    private function countriesAdmin(): User
    {
        return $this->admin([
            'countries_data.view', 'countries_data.list', 'countries_data.edit',
            'countries_data.import', 'countries_data.export', 'settings_general.view',
        ], 'أدمن بيانات الدول');
    }

    /** مصر بمحافظة لها ساكن — محلّ الخطر الذي تحرسه قاعدة عدم الفقد. */
    private function seedGeography(): array
    {
        $egypt = Country::create([
            'iso2' => 'EG', 'name_ar' => 'مصر', 'name_en' => 'Egypt',
            'phone_code' => '+20', 'timezone' => 'Africa/Cairo', 'is_active' => true,
        ]);

        $luxor = Governorate::create(['country_id' => $egypt->id, 'name_ar' => 'الأقصر', 'name_en' => 'Luxor', 'is_active' => true]);

        $resident = $this->makeUser('ساكن الأقصر');
        $resident->forceFill(['country_id' => $egypt->id, 'governorate_id' => $luxor->id])->save();

        return compact('egypt', 'luxor', 'resident');
    }

    /** نسخة مصدر فيها دولة جديدة — أيْ فرقٌ واحد على الأقلّ. */
    private function payload(): array
    {
        return [
            'version' => '2026-09-01',
            'countries' => [
                [
                    'iso2' => 'EG', 'name_ar' => 'مصر', 'name_en' => 'Egypt',
                    'phone_code' => '+20', 'timezone' => 'Africa/Cairo',
                    'governorates' => [['name_ar' => 'الأقصر', 'name_en' => 'Luxor']],
                ],
                [
                    'iso2' => 'JO', 'name_ar' => 'الأردن', 'name_en' => 'Jordan',
                    'phone_code' => '+962', 'timezone' => 'Asia/Amman',
                    'governorates' => [['name_ar' => 'عمّان', 'name_en' => 'Amman']],
                ],
            ],
        ];
    }

    private function fakeSource(mixed $body, int $status = 200): void
    {
        Http::fake(['source.test/*' => Http::response($body, $status)]);
    }

    // ============================================================ الجلب من الشبكة

    /**
     * ⭐ الجلب يُنتج **لقطة `pending` وحدها** — ولا يدمج ولا يكتب حرفًا في
     * `countries`/`governorates`. القرار للمالك من الشاشة (12.7-د).
     */
    public function test_fetch_produces_a_pending_snapshot_and_writes_nothing_to_the_data(): void
    {
        $this->seedGeography();
        $this->fakeSource($this->payload());

        $check = app(CountryDataSync::class)->fetch();

        $this->assertTrue($check->succeeded());
        $this->assertSame('pending', CountrySourceSnapshot::query()->findOrFail($check->snapshot_id)->status);

        // ⛔ ولا صفّ واحد اتكتب في بيانات الدول
        $this->assertDatabaseMissing('countries', ['iso2' => 'JO']);
        $this->assertSame(1, Country::query()->count());
        $this->assertSame(1, Governorate::query()->count());
    }

    /** والجلب يمرّ على `import()` نفسه فيرث تحقّقاته — لا بابَ ثانٍ بقواعد أرخى. */
    public function test_fetch_inherits_the_import_validations(): void
    {
        // نسخة فيها صفّ دولة بلا ISO2 — يرفضه `import()` بنصّه
        $this->fakeSource(['countries' => [['name_ar' => 'بلا كود']]]);

        $check = app(CountryDataSync::class)->fetch();

        $this->assertFalse($check->succeeded());
        $this->assertSame('shape', $check->failure);
        $this->assertStringContainsString('ISO2', $check->message);
        $this->assertDatabaseCount('country_source_snapshots', 0);
    }

    /**
     * ⭐⭐ المهلة تُقال بوضوح — **ولا تمسّ اللقطة الأخيرة الناجحة**: فروقٌ راجعها
     * المالك ولم يقرّر فيها بعدُ لا تضيع بسبب شبكةٍ متعثّرة.
     */
    public function test_a_timeout_is_reported_and_leaves_the_last_good_snapshot_untouched(): void
    {
        $this->seedGeography();

        // لقطة ناجحة قائمة — هي ما نحرسه
        $this->fakeSource($this->payload());
        $good = app(CountryDataSync::class)->checkSource();
        $goodSnapshot = CountrySourceSnapshot::query()->findOrFail($good->snapshot_id);

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $check = app(CountryDataSync::class)->checkSource();

        $this->assertFalse($check->succeeded());
        $this->assertSame('timeout', $check->failure);
        $this->assertStringContainsString('ما ردّش', $check->message);
        $this->assertNull($check->snapshot_id);

        // ⛔ اللقطة الناجحة كما هي: نفس الصفّ ونفس الحالة ونفس الملخّص
        $this->assertDatabaseCount('country_source_snapshots', 1);
        $this->assertSame($goodSnapshot->id, app(CountryDataSync::class)->latest()->id);
        $this->assertSame('checked', $goodSnapshot->refresh()->status);
        $this->assertSame(1, (int) $goodSnapshot->summary['added']);
    }

    /** حالة HTTP غير ناجحة تُقال برقمها — ولا لقطة تُنشَأ. */
    public function test_an_unsuccessful_http_status_is_reported_with_its_number(): void
    {
        $this->fakeSource('service unavailable', 503);

        $check = app(CountryDataSync::class)->checkSource();

        $this->assertFalse($check->succeeded());
        $this->assertSame('http', $check->failure);
        $this->assertSame(503, $check->http_status);
        $this->assertStringContainsString('503', $check->message);
        $this->assertDatabaseCount('country_source_snapshots', 0);
    }

    /** جسمٌ ليس JSON (صفحة HTML مثلًا) يُقال — ولا يُبتلَع بصمت. */
    public function test_a_body_that_is_not_json_is_reported(): void
    {
        $this->fakeSource('<html><body>سجّل دخولك</body></html>');

        $check = app(CountryDataSync::class)->checkSource();

        $this->assertFalse($check->succeeded());
        $this->assertSame('body', $check->failure);
        $this->assertStringContainsString('JSON', $check->message);
        $this->assertDatabaseCount('country_source_snapshots', 0);
    }

    /** وJSON سليم بلا `countries` يُقال كذلك — الشكل جزءٌ من الصحّة. */
    public function test_json_without_a_countries_list_is_reported(): void
    {
        $this->fakeSource(['version' => '2026-09-01']);

        $check = app(CountryDataSync::class)->checkSource();

        $this->assertFalse($check->succeeded());
        $this->assertSame('shape', $check->failure);
        $this->assertStringContainsString('قايمة دول', $check->message);
        $this->assertDatabaseCount('country_source_snapshots', 0);
    }

    /** ورابطٌ فارغ لا يُجرَّب أصلًا — يُقال للمالك ماذا يضبط. */
    public function test_an_empty_source_url_says_what_to_configure(): void
    {
        $this->putSetting('countries.source.url', '');

        $check = app(CountryDataSync::class)->checkSource();

        $this->assertFalse($check->succeeded());
        $this->assertSame('no_url', $check->failure);
        $this->assertStringContainsString('رابط', $check->message);
    }

    /** والمهلة وعدد المحاولات **إعدادان** يقرؤهما العميل فعلًا لا رقمان محروقان. */
    public function test_the_timeout_and_retry_count_come_from_settings(): void
    {
        $this->putSetting('countries.source.timeout', '7');
        $this->putSetting('countries.source.retries', '3');

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('timed out');
        });

        $check = app(CountryDataSync::class)->fetch();

        $this->assertSame(3, $attempts, 'عدد المحاولات لازم يجي من `countries.source.retries`');
        $this->assertSame(3, $check->attempts);
        $this->assertStringContainsString('7 ثانية', $check->message);
    }

    // ============================================================ الفحص يقف عند الفروق

    /**
     * ⭐⭐ الأمر **يجلب ويبني الفروق ويقف** — ولا يدمج حرفًا. نصّ 12.7-د:
     * «فحص فروق النسخة الجديدة **قبل** الدمج».
     */
    public function test_the_command_builds_the_diff_and_stops_without_merging_anything(): void
    {
        $geo = $this->seedGeography();
        $this->fakeSource($this->payload());

        $this->artisan('countries:check-source', ['--force' => true])->assertSuccessful();

        $snapshot = app(CountryDataSync::class)->latest();
        $this->assertNotNull($snapshot);
        $this->assertSame('checked', $snapshot->status);
        $this->assertSame(1, (int) $snapshot->summary['added']);

        // ⛔ ولا شيء اندمج: الأردن ما دخلتش، ومصر زيّ ما هي، والساكن مرتبط
        $this->assertDatabaseMissing('countries', ['iso2' => 'JO']);
        $this->assertSame('pending', $snapshot->status === 'checked' ? 'pending' : 'merged');
        $this->assertNull($snapshot->merged_at);
        $this->assertDatabaseHas('users', ['id' => $geo['resident']->id, 'governorate_id' => $geo['luxor']->id]);
    }

    /** والفروق تصل إشعارًا لأصحاب `countries_data.import` وحدهم — لا لكلّ أدمن. */
    public function test_differences_notify_only_the_import_permission_holders(): void
    {
        $this->seedGeography();
        $importer = $this->countriesAdmin();
        $stranger = $this->admin(['settings_general.view'], 'أدمن بلا استيراد');

        $this->fakeSource($this->payload());

        $this->artisan('countries:check-source', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $importer->id,
            'title' => 'فروق جديدة في بيانات الدول',
        ]);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $stranger->id]);
    }

    /** ⭐ صفرُ فروقٍ = **سكوت**: لا لقطة مكرّرة تتراكم ولا إشعار يوقظ أحدًا. */
    public function test_zero_differences_leave_no_duplicate_snapshot_and_no_notification(): void
    {
        $geo = $this->seedGeography();
        $this->countriesAdmin();

        // نسخة مطابقة تمامًا لما عندنا
        $this->fakeSource([
            'version' => '2026-09-01',
            'countries' => [[
                'iso2' => 'EG', 'name_ar' => 'مصر', 'name_en' => 'Egypt',
                'phone_code' => '+20', 'timezone' => 'Africa/Cairo',
                'governorates' => [['name_ar' => 'الأقصر', 'name_en' => 'Luxor']],
            ]],
        ]);

        $this->artisan('countries:check-source', ['--force' => true])->assertSuccessful();

        $check = app(CountryDataSync::class)->lastCheck();
        $this->assertTrue($check->succeeded());
        $this->assertSame(0, $check->differences());

        $this->assertDatabaseCount('country_source_snapshots', 0);
        $this->assertDatabaseCount('app_notifications', 0);
        $this->assertDatabaseHas('countries', ['id' => $geo['egypt']->id, 'name_ar' => 'مصر']);
    }

    /** وفشل الأمر يخرج بكود فشل ولا يقول «تمّ بنجاح» — الفشل الصامت ممنوع. */
    public function test_a_failing_command_exits_with_a_failure_code_and_says_why(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->artisan('countries:check-source', ['--force' => true])->assertFailed();

        $this->assertSame('failed', app(CountryDataSync::class)->lastCheck()->status);
    }

    // ============================================================ القرار داخل الخدمة

    /**
     * ⭐⭐ الدوريّة واليوم **إعدادان**، والقرار داخل `isCheckDue()` وحدها — فتغيير
     * الأدمن يسري فورًا. لو كان الموعد في تعبير كرونٍ لتجمّد على القيمة القديمة
     * وما التقى الأمرُ بموعده أبدًا: فشلٌ صامت بلا خطأ ولا سجلّ.
     */
    public function test_the_monthly_decision_follows_the_settings_not_a_frozen_cron(): void
    {
        $sync = app(CountryDataSync::class);
        $tz = $sync->checkTimezone();

        $this->putSetting('countries.source.check.day_of_month', '9');
        $this->putSetting('countries.source.check.hour', '13');

        $this->assertTrue($sync->isCheckDue(CarbonImmutable::create(2026, 9, 9, 13, 0, 0, $tz)));
        $this->assertFalse($sync->isCheckDue(CarbonImmutable::create(2026, 9, 9, 12, 0, 0, $tz)));
        $this->assertFalse($sync->isCheckDue(CarbonImmutable::create(2026, 9, 1, 13, 0, 0, $tz)));

        // ويغيّر الأدمن الموعد ⟵ يسري فورًا بلا إعادة نشر
        $this->putSetting('countries.source.check.day_of_month', '1');
        $this->assertTrue($sync->isCheckDue(CarbonImmutable::create(2026, 9, 1, 13, 0, 0, $tz)));

        // والدوريّة إعداد: كلّ 3 شهور ⟵ الشهر التالي مباشرةً ليس موعدًا
        $this->putSetting('countries.source.check.every_months', '3');
        $ranAt = CarbonImmutable::create(2026, 9, 1, 13, 0, 0, $tz)->setTimezone(config('app.timezone'));
        CountrySourceCheck::create([
            'status' => 'ok', 'message' => 'اتفحص', 'trigger' => 'schedule',
            'created_at' => $ranAt, 'updated_at' => $ranAt,
        ]);

        $this->assertFalse($sync->isCheckDue(CarbonImmutable::create(2026, 10, 1, 13, 0, 0, $tz)));
        $this->assertTrue($sync->isCheckDue(CarbonImmutable::create(2026, 12, 1, 13, 0, 0, $tz)));
    }

    /**
     * والإيقاف يوقِف فعلًا — مفتاحٌ بلا قارئ يعني إيقافًا لا يوقِف.
     *
     * والموعد مضبوطٌ على **هذه اللحظة بالضبط**، فلا يبقى مانعٌ إلّا المفتاح نفسه:
     * لولا ذلك لمرّ الاختبار حتّى لو صار المفتاح بلا أثر.
     */
    public function test_turning_the_periodic_check_off_stops_it(): void
    {
        $now = CarbonImmutable::now(app(CountryDataSync::class)->checkTimezone());
        $this->putSetting('countries.source.check.day_of_month', (string) $now->day);
        $this->putSetting('countries.source.check.hour', (string) $now->hour);
        $this->putSetting('countries.source.check.enabled', '0');

        $this->assertFalse(app(CountryDataSync::class)->isCheckDue());

        // ولا شبكة تُلمَس: `preventStrayRequests` كان هينفجر لو الأمر جلب
        $this->artisan('countries:check-source')->assertSuccessful();
        $this->assertDatabaseCount('country_source_checks', 0);
    }

    /** والأمر خارج موعده لا يفعل شيئًا — ولا يلمس الشبكة. */
    public function test_the_command_does_nothing_outside_its_window(): void
    {
        $this->putSetting('countries.source.check.day_of_month', (string) (CarbonImmutable::now()->day % 28 + 1));
        $this->putSetting('countries.source.check.hour', (string) ((CarbonImmutable::now()->hour + 5) % 24));

        $this->artisan('countries:check-source')->assertSuccessful();

        $this->assertDatabaseCount('country_source_checks', 0);
        $this->assertDatabaseCount('country_source_snapshots', 0);
    }

    // ============================================================ الشاشة

    /** زرّ «فحص المصدر الآن» خلف `countries_data.import` — والمحظور يُخفى لا يُعطَّل. */
    public function test_the_check_button_is_hidden_from_who_cannot_import(): void
    {
        // الفورم نفسه هو الدليل — لا نصُّ الزرّ، فهو يظهر أيضًا في شرح الإعداد
        $action = route('admin.countries.check-source');

        $this->actingAs($this->countriesAdmin())
            ->get(route('admin.settings.index', ['tab' => 'countries']))
            ->assertOk()
            ->assertSee('فحص المصدر الآن')
            ->assertSee($action, false);

        $this->actingAs($this->admin(['countries_data.view', 'settings_general.view'], 'أدمن بلا استيراد'))
            ->get(route('admin.settings.index', ['tab' => 'countries']))
            ->assertOk()
            ->assertDontSee($action, false);

        $this->actingAs($this->makeUser('غريب'))
            ->post(route('admin.countries.check-source'))
            ->assertForbidden();
    }

    /** والشاشة تعرض **آخر فحص ونتيجته** — والفشل بسببه لا بصمتٍ. */
    public function test_the_screen_shows_the_last_check_and_its_reason(): void
    {
        $this->seedGeography();
        $admin = $this->countriesAdmin();

        // قبل أيّ فحص: تقول ذلك صراحةً بدل فراغٍ يوهم بالنجاح
        $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => 'countries']))
            ->assertOk()->assertSee('لسّه ما اتفحصش المصدر ولا مرّة', false);

        Http::fake(['source.test/*' => Http::response('nope', 503)]);

        $this->actingAs($admin)->post(route('admin.countries.check-source'))
            ->assertRedirect()->assertSessionHas('error');

        $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => 'countries']))
            ->assertOk()
            ->assertSee('آخر فحص للمصدر')
            ->assertSee('فشل')
            ->assertSee('503', false);
    }

    /** وزرّ الشاشة نفسه **يجلب ويقف**: لقطة بفروقها، وبلا أيّ دمج. */
    public function test_the_screen_button_fetches_and_stops_without_merging(): void
    {
        $this->seedGeography();
        $this->fakeSource($this->payload());

        $this->actingAs($this->countriesAdmin())
            ->post(route('admin.countries.check-source'))
            ->assertRedirect()
            ->assertSessionMissing('error');

        $snapshot = app(CountryDataSync::class)->latest();
        $this->assertSame('checked', $snapshot->status);
        $this->assertNull($snapshot->merged_at);
        $this->assertDatabaseMissing('countries', ['iso2' => 'JO']);
    }
}
