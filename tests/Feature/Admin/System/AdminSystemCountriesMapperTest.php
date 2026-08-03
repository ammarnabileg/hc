<?php

namespace Tests\Feature\Admin\System;

use App\Models\Country;
use App\Models\Governorate;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\System\CountryDataSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

/**
 * 12.7-د — **تحويل شكل مصدر `dr5hn` إلى شكل لقطتنا**.
 *
 * المصدر منصوصٌ عليه بالاسم في الدستور، وشكله ليس شكلنا: `name` لا `name_en` ·
 * `phonecode` لا `phone_code` · `timezones[].zoneName` لا `timezone` · والاسم
 * العربيّ داخل `translations.ar` · والمحافظات في **ملفٍّ ثانٍ** مربوطةٍ بـ
 * `country_code`. فبلا محوِّل يكون «الجلب عبر الشبكة» مبنيًّا وعاطلًا: أوّل فحص
 * يفشل بـ`shape` ويقف البند حيث كان.
 *
 * والعيّنات هنا **منسوخة من بنية المصدر الحقيقيّة** لا مخترَعة.
 *
 * ⭐ أخطر ما يحرسه هذا الصفّ: **الاسم العربيّ لا يُستبدَل بغير عربيّ**. حقل
 * `native` عند dr5hn هو اسم البلد بلغته هو (فارسيّ لأفغانستان)، فلو حُشِر في
 * `name_ar` لصار الدمج يكتب فوق «مصر» ما ليس عربيًّا — وهو فقدُ بياناتٍ يمرّ
 * من تحت قاعدة «لا حذف» لأنّه تعديلٌ لا حذف.
 */
class AdminSystemCountriesMapperTest extends SystemTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->putSetting('countries.source.url', 'https://source.test/countries.json');
        $this->putSetting('countries.source.states_url', 'https://source.test/states.json');
        $this->putSetting('countries.source.format', CountryDataSync::FORMAT_DR5HN);
        $this->putSetting('countries.source.retry_delay_ms', '0');
    }

    private function putSetting(string $key, string $value): void
    {
        Setting::query()->where('key', $key)->update(['value' => $value]);
        Cache::forget('settings');
    }

    private function actor(): User
    {
        return $this->admin(['countries_data.view', 'countries_data.import'], 'أدمن الدول');
    }

    /** بنية `countries.json` كما هي عند المصدر — الحقول المعنيّة منها. */
    private function dr5hnCountries(): array
    {
        return [
            [
                'id' => 65, 'name' => 'Egypt', 'iso3' => 'EGY', 'iso2' => 'EG',
                'phonecode' => '20', 'capital' => 'Cairo', 'native' => 'مصر',
                'timezones' => [['zoneName' => 'Africa/Cairo', 'gmtOffset' => 7200]],
                'translations' => ['ar' => 'مصر', 'fr' => 'Égypte'],
            ],
            [
                'id' => 1, 'name' => 'Afghanistan', 'iso3' => 'AFG', 'iso2' => 'AF',
                'phonecode' => '93', 'native' => 'افغانستان',
                'timezones' => [['zoneName' => 'Asia/Kabul', 'gmtOffset' => 16200]],
                // ⚠️ بلا `translations.ar` — و`native` هنا **فارسيّ** لا عربيّ
                'translations' => ['fa' => 'افغانستان', 'fr' => 'Afghanistan'],
            ],
        ];
    }

    /** بنية `states.json` كما هي عند المصدر. */
    private function dr5hnStates(): array
    {
        return [
            [
                'id' => 3236, 'name' => 'Luxor', 'country_id' => 65, 'country_code' => 'EG',
                'iso2' => 'LX', 'type' => 'governorate', 'native' => 'الأقصر',
                'translations' => ['ar' => 'الأقصر'],
            ],
            [
                'id' => 3901, 'name' => 'Badakhshan', 'country_id' => 1, 'country_code' => 'AF',
                'iso2' => 'BDS', 'type' => 'province', 'native' => 'بدخشان',
                'translations' => ['fa' => 'بدخشان'],
            ],
        ];
    }

    /** دمج **كلّ** ما في الفروق — فالاختبار يقيس ما يقع بعد أوسع قرارٍ ممكن. */
    private function mergeEverything(CountryDataSync $sync, $snapshot): array
    {
        $diff = $sync->diff($snapshot);

        $keys = collect($diff['added'])->merge($diff['changed'])->merge($diff['removed'])
            ->pluck('key')->all();

        return $sync->merge($snapshot, $keys, dryRun: false);
    }

    private function fakeSource(?array $countries = null, ?array $states = null): void
    {
        Http::fake([
            'source.test/countries.json' => Http::response($countries ?? $this->dr5hnCountries()),
            'source.test/states.json' => Http::response($states ?? $this->dr5hnStates()),
        ]);
    }

    // ================================================================ التحويل

    #[Test]
    public function the_dr5hn_shape_becomes_a_readable_snapshot(): void
    {
        $this->fakeSource();

        $check = app(CountryDataSync::class)->fetch($this->actor());

        $this->assertTrue($check->succeeded(), 'الجلب فشل: '.$check->message);

        $countries = collect($check->snapshot->payload['countries'])->keyBy('iso2');

        $this->assertSame('Egypt', $countries['EG']['name_en']);
        $this->assertSame('مصر', $countries['EG']['name_ar']);
        $this->assertSame('20', $countries['EG']['phone_code']);
        $this->assertSame('Africa/Cairo', $countries['EG']['timezone']);
    }

    #[Test]
    public function governorates_come_from_the_second_file_matched_by_country_code(): void
    {
        $this->fakeSource();

        $check = app(CountryDataSync::class)->fetch($this->actor());
        $countries = collect($check->snapshot->payload['countries'])->keyBy('iso2');

        $this->assertSame(['الأقصر'], array_column($countries['EG']['governorates'], 'name_ar'));
        $this->assertSame(['Luxor'], array_column($countries['EG']['governorates'], 'name_en'));

        // ولا تتسرّب محافظة دولةٍ إلى أخرى
        $this->assertSame(['Badakhshan'], array_column($countries['AF']['governorates'], 'name_en'));
    }

    #[Test]
    public function a_country_without_an_arabic_translation_keeps_the_arabic_name_we_already_have(): void
    {
        // أفغانستان عندنا باسمٍ عربيّ، والمصدر بلا `translations.ar`
        $afghanistan = Country::create([
            'iso2' => 'AF', 'name_ar' => 'أفغانستان', 'name_en' => 'Afghanistan',
            'phone_code' => '93', 'timezone' => 'Asia/Kabul', 'is_active' => true,
        ]);

        $this->fakeSource();

        $sync = app(CountryDataSync::class);
        $check = $sync->fetch($this->actor());
        $snapshot = $sync->check($check->snapshot);

        // الحقل الغائب لا يُقارَن ⟵ لا يظهر «تعديل» على الاسم العربيّ أصلًا
        $changed = collect($sync->diff($snapshot)['changed'])->firstWhere('key', 'country:AF');

        $this->assertArrayNotHasKey('name_ar', (array) ($changed['after'] ?? []),
            'المصدر بلا ترجمة عربيّة اقترح تغيير الاسم العربيّ — و`native` عنده فارسيّ.');

        $this->mergeEverything($sync, $snapshot);

        $this->assertSame('أفغانستان', $afghanistan->refresh()->name_ar,
            'الاسم العربيّ اتكتب فوقه من مصدر بلا ترجمة عربيّة.');
    }

    #[Test]
    public function a_governorate_without_an_arabic_translation_keeps_its_arabic_name(): void
    {
        $afghanistan = Country::create([
            'iso2' => 'AF', 'name_ar' => 'أفغانستان', 'name_en' => 'Afghanistan',
            'phone_code' => '93', 'timezone' => 'Asia/Kabul', 'is_active' => true,
        ]);

        $badakhshan = Governorate::create([
            'country_id' => $afghanistan->id, 'name_ar' => 'بدخشان', 'name_en' => 'Badakhshan', 'is_active' => true,
        ]);

        $this->fakeSource();

        $sync = app(CountryDataSync::class);
        $snapshot = $sync->check($sync->fetch($this->actor())->snapshot);
        $this->mergeEverything($sync, $snapshot);

        $this->assertSame('بدخشان', $badakhshan->refresh()->name_ar);
    }

    #[Test]
    public function a_source_row_without_iso2_is_dropped_and_does_not_kill_the_whole_snapshot(): void
    {
        $rows = $this->dr5hnCountries();
        $rows[] = ['id' => 999, 'name' => 'Nowhere', 'phonecode' => '000'];   // بلا iso2

        $this->fakeSource($rows);

        $check = app(CountryDataSync::class)->fetch($this->actor());

        $this->assertTrue($check->succeeded(), 'صفٌّ أعور أسقط النسخة كلّها: '.$check->message);
        $this->assertSame(['EG', 'AF'], array_column($check->snapshot->payload['countries'], 'iso2'));
    }

    #[Test]
    public function an_empty_states_url_yields_countries_without_touching_any_governorate(): void
    {
        $egypt = Country::create([
            'iso2' => 'EG', 'name_ar' => 'مصر', 'name_en' => 'Egypt',
            'phone_code' => '20', 'timezone' => 'Africa/Cairo', 'is_active' => true,
        ]);

        $luxor = Governorate::create([
            'country_id' => $egypt->id, 'name_ar' => 'الأقصر', 'name_en' => 'Luxor', 'is_active' => true,
        ]);

        $this->putSetting('countries.source.states_url', '');
        Http::fake(['source.test/countries.json' => Http::response($this->dr5hnCountries())]);

        $sync = app(CountryDataSync::class);
        $snapshot = $sync->check($sync->fetch($this->actor())->snapshot);
        $this->mergeEverything($sync, $snapshot);

        // القاعدة التي أقرّها المالك: المحافظة لا تُخفى أبدًا — ولا حتّى حين
        // تغيب عن المصدر كلّه لأنّ ملفّها لم يُطلَب أصلًا.
        $luxor->refresh();
        $this->assertTrue((bool) $luxor->is_active);
        $this->assertSame('الأقصر', $luxor->name_ar);
    }

    #[Test]
    public function a_failure_on_the_states_file_is_reported_and_writes_nothing(): void
    {
        Http::fake([
            'source.test/countries.json' => Http::response($this->dr5hnCountries()),
            'source.test/states.json' => Http::response('<html>404</html>', 404),
        ]);

        $check = app(CountryDataSync::class)->fetch($this->actor());

        $this->assertFalse($check->succeeded());
        $this->assertSame('http', $check->failure);
        $this->assertStringContainsString('404', $check->message);
        $this->assertNull($check->snapshot_id, 'فشل ملفّ المحافظات خلّف لقطةً نصفَ مبنيّة.');
    }

    #[Test]
    public function the_format_setting_is_what_picks_the_mapper(): void
    {
        // حمولة بشكلنا نحن تمرّ كما هي حين يقول الإعداد `native`
        $this->putSetting('countries.source.format', CountryDataSync::FORMAT_NATIVE);

        Http::fake(['source.test/countries.json' => Http::response([
            'version' => 'x', 'countries' => [['iso2' => 'JO', 'name_ar' => 'الأردن', 'name_en' => 'Jordan']],
        ])]);

        $check = app(CountryDataSync::class)->fetch($this->actor());

        $this->assertTrue($check->succeeded(), $check->message);
        $this->assertSame('الأردن', $check->snapshot->payload['countries'][0]['name_ar']);
    }
}
