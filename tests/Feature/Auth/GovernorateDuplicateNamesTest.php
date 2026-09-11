<?php

namespace Tests\Feature\Auth;

use App\Models\Country;
use App\Models\Governorate;
use App\Services\Geo\GovernorateLabels;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 🏷️ **سطران متطابقان في قائمة المحافظة** — والمُسجِّل لا يعرف أيّهما محافظته.
 *
 * المصدر المعتمَد (`dr5hn`) يعطي لبعض المحافظات المختلفة **ترجمةً عربيّةً
 * واحدة** داخل الدولة نفسها. وهويّة المحافظة عندنا اسمُها الإنجليزيّ (هجرة
 * 2026-08-28)، فالصفّان يدخلان القاعدة متمايزَين — لكنّ `<select>` يطبع
 * `name_ar` وحده فيخرج **خياران لا يفرّق بينهما شيء**. وهذا ليس تجميلًا:
 * المحافظة تُطبَع على **الشهادة والإفادة والسيرة** (2.5-ج)، فاختيارُ الخطأ
 * يخرج على ورقةٍ مجمَّدة.
 *
 * ⛔ ولا يُصلَح في البيانات: `database/data/countries.json` **مولَّد** من المصدر
 *    (`php artisan countries:check-source --force --dump=…`)، فتعديله بيدٍ يضيع
 *    مع أوّل توليد. فالعلاج **طبقةُ عرض**، وهذا ما تحرسه الاختبارات هنا.
 */
class GovernorateDuplicateNamesTest extends TestCase
{
    use RefreshDatabase;
    use RegistersThroughTwoScreens;

    /**
     * الحالات **السبع** كما قِيسَت على النسخة المثبَّتة: الدولة ⟵ الاسم العربيّ
     * المتصادم ⟵ الأسماء الإنجليزيّة المتمايزة تحته.
     *
     * @var array<string, array<int, array{0: string, 1: array<int, string>}>>
     */
    private const COLLISIONS = [
        'EE' => [['توري', ['Tori', 'Türi']]],
        'FR' => [['لوار', ['Loire', 'Loiret']]],
        'LT' => [
            ['كلايبيدا', ['Klaipėda', 'Klaipėdos miestas']],
            ['بانيفيزيس', ['Panevėžio miestas', 'Panevėžys']],
        ],
        'MV' => [['فافو', ['Faafu', 'Vaavu']]],
        'ES' => [
            ['جزر البليار', ['Balearic Islands', 'Islas Baleares']],
            ['نافارا', ['Navarra', 'Navarre']],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->post(route('onboarding.referral.skip'));
    }

    // ============================================== المصدر نفسه: ما زالت سبعًا

    /**
     * ⭐ **القياس على المصدر لا على ما زرعه الاختبار بيده.**
     *
     * الملفّ مولَّد، فقد تُغيّر نسخةٌ جديدة عدد التصادمات. واختبارٌ يزرع حالتين
     * بيده لا يرى ذلك أبدًا — فيُقاس هنا **الملفّ المثبَّت** بنفس فهرسة
     * `CountryDataSync::indexSource()` (المحافظة تُعرَّف بـ`slug(name_en)`)،
     * وتُطبَع كلّ حالةٍ باسمها لو تغيّر العدد.
     */
    public function test_the_shipped_source_still_carries_exactly_seven_arabic_collisions(): void
    {
        $found = $this->collisionsInSource();
        $expected = [];

        foreach (self::COLLISIONS as $iso2 => $cases) {
            foreach ($cases as [$arabic, $english]) {
                sort($english);
                $expected[] = $iso2.' · '.$arabic.' ⟵ '.implode(' / ', $english);
            }
        }

        sort($expected);
        sort($found);

        $this->assertCount(7, $found, 'تغيّر عدد المحافظات المتطابقة الاسم في المصدر: '.implode(' | ', $found));
        $this->assertSame($expected, $found, 'تغيّرت حالات التصادم في المصدر — راجع `GovernorateLabels` وهذا الاختبار معًا.');
    }

    // ====================================== قائمة التسجيل: ولا سطران متطابقان

    /** ⭐ كلّ دولةٍ متصادمة تخرج قائمتها بأسماءٍ **متمايزة بصريًّا** بلا استثناء */
    public function test_every_affected_country_renders_visually_distinct_options(): void
    {
        $this->passFirstScreen('collisions@test.local');

        foreach (self::COLLISIONS as $iso2 => $cases) {
            $country = $this->seedCountry($iso2, $cases);

            $names = array_column(
                $this->getJson(route('register').'?governorates='.$country->id)->assertOk()->json('governorates'),
                'name',
            );

            $expected = 1;

            foreach ($cases as [, $english]) {
                $expected += count($english);
            }

            $this->assertCount($expected, $names, "قائمة {$iso2} ناقصة صفًّا.");
            $this->assertSame(
                $names,
                array_values(array_unique($names)),
                "قائمة {$iso2} فيها سطران متطابقان: ".json_encode($names, JSON_UNESCAPED_UNICODE),
            );

            // والمميِّز هو الاسم الإنجليزيّ نفسه — لا رقمٌ مصطنَع
            foreach ($cases as [$arabic, $english]) {
                foreach ($english as $name) {
                    $this->assertContains($arabic.' ('.$name.')', $names);
                }
            }

            // ⛔ ولا يُلمَس الاسم الفريد في نفس القائمة
            $this->assertContains('اسم فريد', $names);
        }
    }

    /**
     * ⛔ **ودولةٌ بلا تصادم لا يتغيّر فيها حرف.** لولا هذا لصار العلاج أسوأ من
     * العلّة: قائمة مصر تمتلئ أقواسًا إنجليزيّة لأنّ إسبانيا عندها تصادم.
     */
    public function test_an_untouched_country_renders_exactly_as_before(): void
    {
        $egypt = Country::create([
            'iso2' => 'EG', 'name_ar' => 'مصر', 'name_en' => 'Egypt',
            'phone_code' => '20', 'timezone' => 'Africa/Cairo', 'is_active' => true,
        ]);

        $expected = ['القاهرة', 'الجيزة', 'الإسكندريّة'];

        foreach (['Cairo' => 'القاهرة', 'Giza' => 'الجيزة', 'Alexandria' => 'الإسكندريّة'] as $en => $ar) {
            Governorate::create(['country_id' => $egypt->id, 'name_ar' => $ar, 'name_en' => $en, 'is_active' => true]);
        }

        $this->passFirstScreen('untouched@test.local');

        $names = array_column(
            $this->getJson(route('register').'?governorates='.$egypt->id)->assertOk()->json('governorates'),
            'name',
        );

        sort($expected);
        sort($names);

        $this->assertSame($expected, $names, 'قائمةٌ بلا تصادمٍ تغيّرت — الفكّ تعدّى المتصادمين.');
    }

    // ================================================= الخدمة نفسها: الحدّ الأدنى

    /** المحافظة الفريدة كما هي، والمتصادمة وحدها تحمل اللاحقة */
    public function test_the_helper_only_touches_the_colliding_rows(): void
    {
        $labels = app(GovernorateLabels::class)->labels([
            new Governorate(['id' => 1, 'name_ar' => 'لوار', 'name_en' => 'Loire']),
            new Governorate(['id' => 2, 'name_ar' => 'لوار', 'name_en' => 'Loiret']),
            new Governorate(['id' => 3, 'name_ar' => 'باريس', 'name_en' => 'Paris']),
        ]);

        $this->assertSame([1 => 'لوار (Loire)', 2 => 'لوار (Loiret)', 3 => 'باريس'], $labels);
    }

    /**
     * وآخرُ الحلول: صفٌّ بلا اسمٍ إنجليزيّ يميّزه يأخذ **ترتيبه** — فلا سطران
     * متطابقان يخرجان من هنا أبدًا مهما كانت البيانات.
     */
    public function test_a_row_without_a_distinguishing_english_name_falls_back_to_its_position(): void
    {
        $labels = app(GovernorateLabels::class)->labels([
            new Governorate(['id' => 1, 'name_ar' => 'توري', 'name_en' => '']),
            new Governorate(['id' => 2, 'name_ar' => 'توري', 'name_en' => 'توري']),
            new Governorate(['id' => 3, 'name_ar' => 'توري', 'name_en' => 'Türi']),
        ]);

        $this->assertSame([1 => 'توري (1)', 2 => 'توري (2)', 3 => 'توري (Türi)'], $labels);
        $this->assertSame(array_values($labels), array_values(array_unique($labels)));
    }

    /** والاسم العربيّ الغائب لا يترك خيارًا فارغًا — يخرج الصفّ باسمه الإنجليزيّ */
    public function test_a_row_without_an_arabic_name_falls_back_to_its_english_one(): void
    {
        $labels = app(GovernorateLabels::class)->labels([
            new Governorate(['id' => 1, 'name_ar' => '', 'name_en' => 'Adélie Land']),
        ]);

        $this->assertSame([1 => 'Adélie Land'], $labels);
    }

    // ==================================================================== أدوات

    /**
     * دولةٌ بحالات تصادمها **كما في المصدر** + محافظةٍ فريدة تحرس «لا يُلمَس الفريد».
     *
     * @param  array<int, array{0: string, 1: array<int, string>}>  $cases
     */
    private function seedCountry(string $iso2, array $cases): Country
    {
        $country = Country::create([
            'iso2' => $iso2, 'name_ar' => $iso2, 'name_en' => $iso2,
            'phone_code' => '1', 'timezone' => 'UTC', 'is_active' => true,
        ]);

        foreach ($cases as [$arabic, $english]) {
            foreach ($english as $name) {
                Governorate::create([
                    'country_id' => $country->id, 'name_ar' => $arabic, 'name_en' => $name, 'is_active' => true,
                ]);
            }
        }

        Governorate::create([
            'country_id' => $country->id, 'name_ar' => 'اسم فريد', 'name_en' => 'Unique '.$iso2, 'is_active' => true,
        ]);

        return $country;
    }

    /**
     * تصادمات الملفّ المثبَّت بنفس فهرسة `CountryDataSync` — المحافظة تُعرَّف
     * بـ`slug(name_en)`، فما يتكرّر إنجليزيًّا ينطوي في صفٍّ واحد أصلًا.
     *
     * @return array<int, string> سطرٌ لكلّ حالة: `ISO2 · الاسم ⟵ en / en`
     */
    private function collisionsInSource(): array
    {
        $payload = (array) json_decode((string) file_get_contents(database_path('data/countries.json')), true);
        $out = [];

        foreach ((array) ($payload['countries'] ?? []) as $country) {
            $indexed = [];

            foreach ((array) ($country['governorates'] ?? []) as $governorate) {
                $nameAr = trim((string) ($governorate['name_ar'] ?? ''));
                $nameEn = trim((string) ($governorate['name_en'] ?? $nameAr));

                if ($nameEn === '') {
                    continue;
                }

                $indexed[$this->slug($nameEn)] = ['name_ar' => $nameAr !== '' ? $nameAr : $nameEn, 'name_en' => $nameEn];
            }

            $byArabic = [];

            foreach ($indexed as $row) {
                $byArabic[$row['name_ar']][] = $row['name_en'];
            }

            foreach ($byArabic as $nameAr => $names) {
                if (count($names) > 1) {
                    sort($names);
                    $out[] = mb_strtoupper((string) $country['iso2']).' · '.$nameAr.' ⟵ '.implode(' / ', $names);
                }
            }
        }

        return $out;
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? $value;

        return trim((string) $value, '-') ?: 'x';
    }
}
