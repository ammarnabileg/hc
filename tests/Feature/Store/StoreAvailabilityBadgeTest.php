<?php

namespace Tests\Feature\Store;

use App\Models\Course;
use App\Models\CourseAvailabilityPeriod;
use Illuminate\Support\Carbon;

/**
 * شارة الإتاحة الزمنيّة في شبكة المتجر (16 ⟵ 5).
 *
 * الدستور 16 يعطي التدريب «فترات تشغيل (بداية/نهاية، متعدّدة) + ساعة مشاهدة
 * يوميّة»، والقسم 5 يقول إنّ التدريب خارج ساعاته **مقفول ولو كانت الفترة سارية**
 * وإنّ الفتح/الغلق **بتوقيت المستخدم المحلّيّ لا الخادم**. وكانت الشبكة تعرض
 * «نادي الفجر» (5→7 ص) الساعة التاسعة صباحًا كأنّه مفتوح بلا أيّ إشارة.
 *
 * ولأنّ الشارة قرارٌ في الخادم لا في المتصفّح، تُثبَّت الساعة هنا
 * (`Carbon::setTestNow`) ويُقرأ الناتج من HTML الشبكة نفسها.
 */
class StoreAvailabilityBadgeTest extends StoreTestCase
{
    /** اللحظتان نفسهما المستعملتان في `CartTest`/`AvailabilityTest`: 02:00 UTC داخل 5→7 ص بتوقيت مصر، و18:00 UTC خارجها. */
    private const INSIDE = '2026-07-15 02:00:00';

    private const OUTSIDE = '2026-07-15 18:00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** داخل نافذته: «متاح الآن» ومعها ساعة الإغلاق — الشارة تقول متى ينتهي لا «مفتوح» وكفى (2.17) */
    public function test_a_course_inside_its_daily_window_is_badged_open_until_its_closing_time(): void
    {
        $this->dawnClub();
        $user = $this->trainee(0);

        Carbon::setTestNow(Carbon::parse(self::INSIDE, 'UTC'));

        $this->actingAs($user)->get(route('store.index'))
            ->assertOk()
            ->assertSee('نادي الفجر')
            ->assertSee('متاح الآن حتى 07:00')
            ->assertDontSee('مغلق الآن');
    }

    /** وخارجها: «مغلق الآن — يفتح 05:00» — فالمقفول يقول متى يفتح (2.17) */
    public function test_a_course_outside_its_daily_window_is_badged_closed_with_its_next_opening(): void
    {
        // صيغةُ يومٍ آخر تُضبَط على الساعة وحدها كي يكون النصّ المتوقَّع قاطعًا
        $this->setting('store.availability.day_time_format', 'H:i');
        $this->dawnClub();
        $user = $this->trainee(0);

        Carbon::setTestNow(Carbon::parse(self::OUTSIDE, 'UTC'));

        $this->actingAs($user)->get(route('store.index'))
            ->assertOk()
            ->assertSee('نادي الفجر')
            ->assertSee('مغلق الآن — يفتح 05:00')
            ->assertDontSee('متاح الآن');
    }

    /** ونصّ الشارة إعداد لا نصّ محروق (2.13): يتغيّر بتغيير المفتاح وحده */
    public function test_the_badge_wording_comes_from_settings(): void
    {
        $this->setting('store.availability.open_until_text', 'شغّال دلوقتي لحدّ {time}');
        $this->dawnClub();
        $user = $this->trainee(0);

        Carbon::setTestNow(Carbon::parse(self::INSIDE, 'UTC'));

        $this->actingAs($user)->get(route('store.index'))
            ->assertOk()
            ->assertSee('شغّال دلوقتي لحدّ 07:00');
    }

    /**
     * فترات الإتاحة المتعدّدة جزءٌ من نفس البند لا آليّة أخرى: تدريبٌ **داخل**
     * ساعته اليوميّة لكنّه **خارج كلّ فتراته** مقفولٌ كذلك — ولو قرأنا النافذة
     * وحدها لأعلنّاه «متاحًا الآن» وهو غير قابل للوصول أصلًا (5).
     */
    public function test_a_course_outside_all_its_active_periods_is_badged_closed_even_inside_its_daily_hours(): void
    {
        $course = $this->dawnClub();

        CourseAvailabilityPeriod::create([
            'course_id' => $course->id,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-07',
            'is_active' => true,
        ]);

        $user = $this->trainee(0);

        Carbon::setTestNow(Carbon::parse(self::INSIDE, 'UTC'));

        $this->actingAs($user)->get(route('store.index'))
            ->assertOk()
            ->assertSee('نادي الفجر')
            ->assertDontSee('متاح الآن');
    }

    /** ⚠️ الانحدار: تدريبٌ بلا نافذة ولا فترات يبقى كارتًا كما كان — بلا شارة إطلاقًا */
    public function test_a_course_without_any_time_restriction_keeps_its_card_untouched(): void
    {
        $this->course(['published_at' => Carbon::parse('2020-01-01')]);
        $user = $this->trainee(0);

        Carbon::setTestNow(Carbon::parse(self::OUTSIDE, 'UTC'));

        $this->actingAs($user)->get(route('store.index'))
            ->assertOk()
            ->assertSee('إكسل للشغل')
            ->assertSee('400 كوين')
            ->assertDontSee('متاح الآن')
            ->assertDontSee('مغلق الآن')
            ->assertDontSee('مغلق حاليًّا');
    }

    /** والمنتج الرقميّ لا نافذة له أصلًا — فلا شارة إتاحةٍ زمنيّة عليه */
    public function test_a_digital_product_never_gets_an_availability_badge(): void
    {
        $this->product();
        $user = $this->trainee(0);

        Carbon::setTestNow(Carbon::parse(self::OUTSIDE, 'UTC'));

        $this->actingAs($user)->get(route('store.index'))
            ->assertOk()
            ->assertSee('دليل أسئلة المقابلات')
            ->assertDontSee('متاح الآن')
            ->assertDontSee('مغلق الآن');
    }

    /** «نادي الفجر» بنافذته الدستوريّة 5→7 ص، بنشرٍ قديم كي لا تتداخل الجدولة مع تثبيت الساعة */
    private function dawnClub(): Course
    {
        return $this->course([
            'slug' => 'nadi-al-fajr',
            'name_ar' => 'نادي الفجر',
            'price_coins' => 400,
            'daily_open_at' => '05:00',
            'daily_close_at' => '07:00',
            'published_at' => Carbon::parse('2020-01-01'),
        ]);
    }
}
