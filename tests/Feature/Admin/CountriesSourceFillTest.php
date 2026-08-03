<?php

namespace Tests\Feature\Admin;

use App\Models\Country;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\System\CountryDataSync;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CountriesSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 🌍 **كلّ دول العالم ومحافظاتها كاملة** (2.5-ج) — من المصدر بالآليّة، بلا فقد.
 *
 * النصّ: «**الدولة:** Select … **المحافظة:** Select مبني على الدولة، يُملأ
 * تلقائيًّا … مطلوب **كل دول العالم ومحافظاتها كاملة**. **مصدر الداتا
 * (مُعتمَد):** ريبو GitHub `dr5hn/countries-states-cities-database` (250 دولة +
 * 5,299 محافظة/ولاية …)».
 *
 * وما كان: **4 دول ومحافظة واحدة** — وهي بذرة **عرض** لا بذرة إنتاج.
 *
 * وثلاث قواعد لا تُخترَق ويحرسها هذا الملفّ صراحةً:
 *  1) **لا حذف أبدًا** · 2) **المحافظة لا تُخفى أبدًا** · 3) **ما له مستخدمٌ
 *  مرتبط لا يُمَسّ** — والتحقّق يقارن أعداد المستخدمين قبل/بعد ويُفجِّر المعاملة.
 */
class CountriesSourceFillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seedLegacyFour();
    }

    // ================================================================ الملء

    /** ⭐ 4 دول ومحافظة ⟵ كلّ دول المصدر ومحافظاتها، والصفوف القديمة كما هي */
    public function test_the_seeder_fills_the_world_from_the_packaged_source(): void
    {
        $this->assertSame(4, Country::count());
        $this->assertSame(1, Governorate::count());

        $egyptId = Country::where('iso2', 'EG')->value('id');
        $cairoId = Governorate::where('name_en', 'Cairo')->value('id');

        $this->seed(CountriesSeeder::class);

        // العدد يُقرأ من **النسخة المثبَّتة نفسها** لا من رقمٍ محروق يتقادم
        $payload = $this->packagedPayload();
        $this->assertSame(count($payload['countries']), Country::count());
        $this->assertGreaterThan(5000, Governorate::count());

        // ⛔ ولا صفَّ قديم تغيّر رقمه — الارتباطات كلّها معلّقة على هذه الأرقام
        $this->assertSame($egyptId, Country::where('iso2', 'EG')->value('id'));
        $this->assertSame($cairoId, Governorate::where('name_en', 'Cairo')->value('id'));
    }

    /** 🔁 تشغيلٌ ثانٍ = **صفر أثر**: لا صفّ يُضاف ولا يُعدَّل ولا يُحذَف */
    public function test_running_the_seeder_twice_changes_nothing(): void
    {
        $this->seed(CountriesSeeder::class);

        $countries = Country::count();
        $governorates = Governorate::count();
        $fingerprint = $this->fingerprint();

        $this->seed(CountriesSeeder::class);

        $this->assertSame($countries, Country::count());
        $this->assertSame($governorates, Governorate::count());
        $this->assertSame($fingerprint, $this->fingerprint());
    }

    // ============================================== القواعد الثلاث لا تُخترَق

    /** ⛔ **لا حذف أبدًا** — ولا صفَّ يختفي من الجدولين مهما اختلف المصدر */
    public function test_nothing_is_ever_deleted(): void
    {
        // دولةٌ ومحافظةٌ لا وجود لهما في المصدر إطلاقًا
        $ghost = Country::create([
            'iso2' => 'ZZ', 'name_ar' => 'دولة الاختبار', 'name_en' => 'Testland',
            'phone_code' => '999', 'timezone' => 'UTC', 'is_active' => true,
        ]);
        $ghostGovernorate = Governorate::create([
            'country_id' => $ghost->id, 'name_ar' => 'محافظة الاختبار',
            'name_en' => 'Testville', 'is_active' => true,
        ]);

        $this->seed(CountriesSeeder::class);

        $this->assertDatabaseHas('countries', ['id' => $ghost->id, 'iso2' => 'ZZ']);
        $this->assertDatabaseHas('governorates', ['id' => $ghostGovernorate->id]);
    }

    /** ⛔ **المحافظة لا تُخفى أبدًا** (قاعدة مالك) ولو غابت عن المصدر تمامًا */
    public function test_a_governorate_is_never_hidden(): void
    {
        $egypt = Country::where('iso2', 'EG')->firstOrFail();
        $orphan = Governorate::create([
            'country_id' => $egypt->id, 'name_ar' => 'محافظة قديمة',
            'name_en' => 'Old Governorate', 'is_active' => true,
        ]);

        $sync = app(CountryDataSync::class);
        $snapshot = $sync->check($sync->import($this->packagedPayload()));
        $diff = $sync->diff($snapshot);

        $row = collect($diff['removed'])->firstWhere('key', 'gov:EG:old-governorate');

        $this->assertNotNull($row, 'المحافظة الغائبة عن المصدر لم تظهر في عمود «محذوف».');
        $this->assertTrue($row['protected'], 'المحافظة ظهرت غير محميّة — والقاعدة أنّها لا تُخفى أبدًا.');

        // وحتى لو اختارها المالك صراحةً في الدمج — تبقى كما هي
        $report = $sync->merge($snapshot, [$row['key']]);

        $this->assertContains($row['label'], $report['protected']);
        $this->assertTrue(Governorate::find($orphan->id)->is_active);
        $this->assertSame(0, $report['hidden']);
    }

    /** ⛔ **ما له مستخدمٌ مرتبط لا يُمَسّ** — ولو غاب عن المصدر */
    public function test_a_country_with_users_is_protected(): void
    {
        $ghost = Country::create([
            'iso2' => 'ZZ', 'name_ar' => 'دولة الاختبار', 'name_en' => 'Testland',
            'phone_code' => '999', 'timezone' => 'UTC', 'is_active' => true,
        ]);

        $this->makeUser('resident@test.local', $ghost->id, null);

        $sync = app(CountryDataSync::class);
        $snapshot = $sync->check($sync->import($this->packagedPayload()));
        $row = collect($sync->diff($snapshot)['removed'])->firstWhere('key', 'country:ZZ');

        $this->assertNotNull($row);
        $this->assertTrue($row['protected'], 'دولةٌ لها مستخدم ظهرت غير محميّة.');
        $this->assertSame(1, $row['users']);

        $sync->merge($snapshot, [$row['key']]);

        $this->assertTrue(Country::find($ghost->id)->is_active);
        $this->assertNull(Country::find($ghost->id)->sync_hidden_at);
    }

    /** ⭐ ومصر والقاهرة — المرتبطتان بمستخدمين — تخرجان من الملء بلا خدش */
    public function test_egypt_and_cairo_survive_the_fill_untouched(): void
    {
        $egypt = Country::where('iso2', 'EG')->firstOrFail();
        $cairo = Governorate::where('name_en', 'Cairo')->firstOrFail();

        foreach (range(1, 3) as $i) {
            $this->makeUser("resident{$i}@test.local", $egypt->id, $cairo->id);
        }

        $before = [
            'country' => $egypt->only(['id', 'iso2', 'name_ar', 'phone_code', 'timezone', 'is_active']),
            'governorate' => $cairo->only(['id', 'country_id', 'name_ar', 'name_en', 'is_active']),
        ];

        $this->seed(CountriesSeeder::class);

        $this->assertSame($before['country'], Country::find($egypt->id)->only(array_keys($before['country'])));
        $this->assertSame($before['governorate'], Governorate::find($cairo->id)->only(array_keys($before['governorate'])));

        // ولا واحدٌ من الثلاثة فقد ارتباطه
        $this->assertSame(3, User::where('country_id', $egypt->id)->count());
        $this->assertSame(3, User::where('governorate_id', $cairo->id)->count());
    }

    /**
     * 🧬 طفرة: **يد تقطع ارتباط مستخدمٍ أثناء الدمج** ⟵ المعاملة تنفجر وترتدّ.
     *
     * هذا هو الحارس الذي يجعل «بلا فقد» قياسًا لا شعارًا — فيُثبَت سقوطه.
     */
    public function test_mutation_losing_a_user_link_mid_merge_rolls_everything_back(): void
    {
        $egypt = Country::where('iso2', 'EG')->firstOrFail();
        $cairo = Governorate::where('name_en', 'Cairo')->firstOrFail();
        $this->makeUser('victim@test.local', $egypt->id, $cairo->id);

        $sync = app(CountryDataSync::class);
        $snapshot = $sync->check($sync->import($this->packagedPayload()));
        $keys = array_map(fn (array $row) => (string) $row['key'], $sync->diff($snapshot)['added']);

        $countriesBefore = Country::count();
        $governoratesBefore = Governorate::count();

        // يدٌ تقطع الارتباط في منتصف المعاملة — كأنّ الدمج أضاعه
        Country::saved(function () {
            User::where('email', 'victim@test.local')->update(['country_id' => null]);
        });

        try {
            $sync->merge($snapshot, $keys);
            $this->fail('الدمج نجح رغم فقد ارتباط مستخدم — الحارس ساقط.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('التحقّق', $exception->getMessage());
        }

        // ⟵ لا صفَّ كُتِب: المعاملة ارتدّت كاملةً
        $this->assertSame($countriesBefore, Country::count());
        $this->assertSame($governoratesBefore, Governorate::count());
    }

    /**
     * 🧬 طفرة: **حقلٌ غائب عن المصدر لا يُصطنَع** — العطب الذي أُصلِح ولا يعود.
     *
     * `indexSource()` كانت تملأ الغائب بافتراضيّ (`name_ar` ⟵ كود الدولة ·
     * `timezone` ⟵ توقيت المنصّة)، فيتحوّل **غياب الحقل** إلى **تعديلٍ يمسح ما
     * عندنا** — فقدُ بياناتٍ يمرّ من تحت قاعدة «لا حذف» لأنّه لا يبدو حذفًا.
     */
    public function test_mutation_a_field_absent_from_the_source_never_overwrites_ours(): void
    {
        $egypt = Country::where('iso2', 'EG')->firstOrFail();
        $egypt->forceFill(['name_ar' => 'جمهوريّة مصر', 'timezone' => 'Africa/Cairo'])->save();

        $sync = app(CountryDataSync::class);

        // نسخةٌ فيها `iso2` وحده: لا اسم عربيّ ولا توقيت ولا مفتاح هاتف
        $snapshot = $sync->check($sync->import(['countries' => [['iso2' => 'EG', 'governorates' => []]]]));
        $diff = $sync->diff($snapshot);

        $this->assertSame([], $diff['changed'], 'الحقل الغائب عن المصدر اقتُرِح تعديلًا — العطب عاد.');

        $sync->merge($snapshot, array_map(fn (array $r) => (string) $r['key'], $diff['added']));

        $egypt->refresh();
        $this->assertSame('جمهوريّة مصر', $egypt->name_ar);
        $this->assertSame('Africa/Cairo', $egypt->timezone);
    }

    /**
     * 🧬 طفرة: **المحافظة بلا ترجمة عربيّة في المصدر تدخل** ولا تُسقَط بصمت.
     *
     * وكانت تُسقَط: `indexSource()` تشترط `name_ar` لتُدرِج المحافظة، فـ12 محافظة
     * في المصدر بلا `translations.ar` كانت تختفي من النسخة كأنّها غير موجودة.
     */
    public function test_mutation_a_governorate_without_an_arabic_name_still_enters(): void
    {
        $sync = app(CountryDataSync::class);

        $snapshot = $sync->check($sync->import(['countries' => [[
            'iso2' => 'EG', 'governorates' => [['name_en' => 'Nameless Province']],
        ]]]));

        $diff = $sync->diff($snapshot);
        $row = collect($diff['added'])->firstWhere('key', 'gov:EG:nameless-province');

        $this->assertNotNull($row, 'محافظة بلا اسمٍ عربيّ سقطت من النسخة بصمت.');

        $sync->merge($snapshot, [$row['key']]);

        $this->assertDatabaseHas('governorates', ['name_en' => 'Nameless Province', 'is_active' => true]);
    }

    /** ⛔ ولا يعود اسمها العربيّ إنجليزيًّا لمجرّد غيابه عن المصدر */
    public function test_mutation_a_missing_arabic_name_never_replaces_the_one_we_have(): void
    {
        $egypt = Country::where('iso2', 'EG')->firstOrFail();
        Governorate::where('name_en', 'Cairo')->update(['name_ar' => 'القاهرة الكبرى']);

        $sync = app(CountryDataSync::class);
        $snapshot = $sync->check($sync->import(['countries' => [[
            'iso2' => 'EG', 'governorates' => [['name_en' => 'Cairo']],
        ]]]));

        $this->assertSame([], $sync->diff($snapshot)['changed']);
        $this->assertSame('القاهرة الكبرى', Governorate::where('country_id', $egypt->id)->value('name_ar'));
    }

    // ------------------------------------------------------------------ أدوات

    /** نفس الأربع التي كانت في القاعدة قبل الملء (بذرة عرضٍ لا إنتاج) */
    private function seedLegacyFour(): void
    {
        $rows = [
            ['EG', 'مصر', 'Egypt', '+20', 'Africa/Cairo'],
            ['SA', 'السعوديّة', 'Saudi Arabia', '+966', 'Asia/Riyadh'],
            ['AE', 'الإمارات', 'United Arab Emirates', '+971', 'Asia/Dubai'],
            ['MA', 'المغرب', 'Morocco', '+212', 'Africa/Casablanca'],
        ];

        foreach ($rows as $index => [$iso2, $ar, $en, $dial, $tz]) {
            $country = Country::create([
                'iso2' => $iso2, 'name_ar' => $ar, 'name_en' => $en,
                'phone_code' => $dial, 'timezone' => $tz,
                'sort_order' => $index + 1, 'is_active' => true,
            ]);

            if ($iso2 === 'EG') {
                Governorate::create([
                    'country_id' => $country->id, 'name_ar' => 'القاهرة',
                    'name_en' => 'Cairo', 'is_active' => true,
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function packagedPayload(): array
    {
        $path = database_path('data/countries.json');
        $this->assertFileExists($path, 'نسخة الدول المثبَّتة مفقودة — ولّدها بـcountries:check-source --dump=…');

        return (array) json_decode((string) file_get_contents($path), true);
    }

    private function makeUser(string $email, ?int $countryId, ?int $governorateId): User
    {
        $user = User::create([
            'name' => 'مقيم', 'email' => $email, 'password' => 'secret-password',
            'code' => 'U'.mb_strtoupper(substr(md5($email), 0, 7)), 'status' => 'active',
            'country_id' => $countryId, 'governorate_id' => $governorateId,
        ]);

        if ($role = Role::where('key', 'trainee')->first()) {
            $user->assignRole($role);
        }

        return $user;
    }

    /** بصمة الجدولين — أيّ إضافةٍ أو تعديلٍ أو حذفٍ تغيّرها */
    private function fingerprint(): string
    {
        return md5(
            Country::orderBy('id')->get(['id', 'iso2', 'name_ar', 'name_en', 'phone_code', 'timezone', 'is_active'])->toJson()
            .Governorate::orderBy('id')->get(['id', 'country_id', 'name_ar', 'name_en', 'is_active'])->toJson(),
        );
    }
}
