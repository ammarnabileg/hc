<?php

namespace Tests\Feature\Store;

use App\Models\LibraryEntitlement;
use App\Services\Store\BundleLanding;

/**
 * ⭐ **الندرة الحقيقيّة وحدها، وصفر Dark Pattern.**
 *
 * النصّ الحاكم — ثلاثة مواضع لا موضع:
 *  > «🛡️ الحارس الأخلاقي (قاعدة عليا): ممنوع **Dark Patterns** — لا ندرة كاذبة،
 *  >  لا تأنيب/إشعارات مُذنِبة، لا استغلال إدمان، **لا أرقام وهمية**.» (2.9)
 *  > «**ندرة حقيقية:** إبراز نوافذ الإتاحة/الوقت اليومي كعدّادات **صادقة**.» (2.9-10)
 *  > «**بلا Dark Patterns:** لا عدّادات وهميّة ولا ندرة مزيّفة ولا إزعاج … **وكلّ
 *  >  عرضٍ بقيمته الحقيقيّة مكتوبة**.» (21.1-د)
 *  > «⭐ القاعدة النهائيّة: **لا استرجاع نقديّ لأيّ مدفوعات**.» (19.4)
 *
 * والطفرات التي يمسكها هذا الملفّ:
 *  - إظهار عدّادٍ بلا تاريخ ⟵ يسقط `no_countdown_exists_without_a_real_end_date`.
 *  - إظهار «باقي N» بلا حدّ ⟵ يسقط `no_seat_counter_without_a_real_purchase_limit`.
 *  - كتابةُ عدد المقاعد بيدٍ ⟵ يسقط `the_seat_number_drops_after_a_real_purchase`.
 *  - عرضُ «وفّرت» حين القيمة ≤ السعر ⟵ يسقط `no_savings_when_the_value_is_not_above_the_price`.
 *  - كتابةُ «ضمان استرجاع» ⟵ يسقط `the_page_never_promises_a_refund`.
 */
class BundleScarcityAndHonestyTest extends StoreTestCase
{
    // ============================================================ العدّاد

    /** ⭐ بلا `available_until` **لا يوجد عدّادٌ في الـHTML إطلاقًا** — لا مخفيًّا ولا مصفَّرًا. */
    public function test_no_countdown_exists_without_a_real_end_date(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()]);

        $html = $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-bundle-countdown', $html,
            'عدّادٌ في الصفحة بلا تاريخ نهاية حقيقيّ — ندرةٌ مصطنعة (2.9 · 21.1-د).');
        $this->assertStringNotContainsString((string) setting('store.bundle.countdown_title'), $html);
        $this->assertNull(app(BundleLanding::class)->countdownEndsAt($bundle));
    }

    /** وبتاريخٍ حقيقيّ يظهر العدّاد بموعده — والموعد **من الخادم** لا من المتصفّح. */
    public function test_a_real_end_date_renders_a_countdown_bound_to_the_server_time(): void
    {
        $endsAt = now()->addDays(3)->startOfMinute();
        $bundle = $this->bundle([$this->course()], ['available_until' => $endsAt]);

        $html = $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(setting('store.bundle.countdown_title'))
            ->getContent();

        $this->assertStringContainsString('data-ends-at="'.$endsAt->toIso8601String().'"', $html,
            'العدّاد لا يحمل موعد الخادم — يبقى مدّةً تبدأ من جديد لكلّ زائر، وهو العدّاد الوهميّ بعينه.');
    }

    /** والتاريخ الماضي **يُنهي العدّاد ويُقفل الشراء** — لا يعاد تشغيله ولا يُمدَّد صامتًا. */
    public function test_a_past_end_date_closes_the_window_instead_of_restarting(): void
    {
        $bundle = $this->bundle([$this->course()], ['available_until' => now()->subHour()]);
        $landing = app(BundleLanding::class);

        $this->assertNull($landing->countdownEndsAt($bundle));
        $this->assertFalse($landing->purchasable($bundle));

        $this->actingAs($this->trainee(1000))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(setting('store.bundle.window_closed_text'))
            // 2.15-أ-7: المحظور **يُخفى لا يُعطَّل** — فلا زرّ شراءٍ ميّت
            ->assertDontSee(setting('store.bundle.cta_label'));
    }

    // ============================================================ المقاعد

    /** ⭐ بلا `purchase_limit` لا يوجد سطرُ مقاعد أصلًا. */
    public function test_no_seat_counter_without_a_real_purchase_limit(): void
    {
        $bundle = $this->bundle([$this->course()]);

        $this->assertNull(app(BundleLanding::class)->seatsLeft($bundle));

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertDontSee('مقعدًا');
    }

    /** ⭐ والرقم **ينقص بعد شراءٍ فعليّ** — معدودٌ من الطلبات المدفوعة لا مكتوب. */
    public function test_the_seat_number_drops_after_a_real_purchase(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()], [
            'price_coins' => 420,
            'purchase_limit' => 3,
        ]);

        $landing = app(BundleLanding::class);
        $this->assertSame(3, $landing->seatsLeft($bundle));

        $buyer = $this->trainee(1000);
        $this->actingAs($buyer)->post(route('store.checkout'), [
            'type' => 'bundle', 'slug' => $bundle->slug, 'refund_ack' => 1,
        ])->assertRedirect();

        $this->assertSame(2, $landing->seatsLeft($bundle->fresh()), 'الرقم مانقصش بعد شراءٍ فعليّ — يبقى مكتوبًا لا معدودًا.');

        $this->actingAs($this->trainee(1000))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee('باقي 2 مقعدًا من 3');
    }

    /** ونفادُ المقاعد **يقفل الشراء فعلًا** — فالحدّ حقيقيّ لا لافتةٌ تخوّف. */
    public function test_a_sold_out_bundle_actually_closes(): void
    {
        $bundle = $this->bundle([$this->course()], ['price_coins' => 100, 'purchase_limit' => 1]);

        $this->actingAs($this->trainee(1000))->post(route('store.checkout'), [
            'type' => 'bundle', 'slug' => $bundle->slug, 'refund_ack' => 1,
        ])->assertRedirect();

        $this->assertSame(0, app(BundleLanding::class)->seatsLeft($bundle->fresh()));

        $this->actingAs($this->trainee(1000))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(setting('store.bundle.sold_out_text'))
            ->assertDontSee(setting('store.bundle.cta_label'));
    }

    // ============================================================ «وفّرت» المحسوبة

    /**
     * ⭐ **القيمة الإجماليّة ≤ سعر الباقة ⟵ لا «وفّرت» إطلاقًا**: لا صفرًا ولا
     * رقمًا سالبًا ولا شطبًا ولا نسبة. «كلّ عرضٍ بقيمته الحقيقيّة» (21.1-د).
     */
    public function test_no_savings_when_the_value_is_not_above_the_price(): void
    {
        // مجموع العناصر 500 وسعر الباقة 600 — أيْ أغلى، فلا مرساة أصلًا
        $bundle = $this->bundle([$this->course(['price_coins' => 400]), $this->product(['price_coins' => 100])], [
            'price_coins' => 600,
        ]);

        $html = $this->actingAs($this->trainee(1000))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertDontSee('وفّرت')
            ->assertDontSee(setting('store.bundle.ledger_title'))
            ->getContent();

        $this->assertStringNotContainsString('line-through', $html,
            'شطبٌ بلا توفيرٍ حقيقيّ — مرساةٌ كاذبة (18 · 2.9).');
        $this->assertDoesNotMatchRegularExpression('/أقلّ بـ-?0?%/u', $html);
    }

    /** وحالة التساوي تمامًا — الحدّ الذي يسقط عنده الفحص الرخو */
    public function test_equal_value_and_price_shows_no_savings_either(): void
    {
        $bundle = $this->bundle([$this->course(['price_coins' => 400]), $this->product(['price_coins' => 100])], [
            'price_coins' => 500,
        ]);

        $html = $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertDontSee('وفّرت')
            ->assertDontSee(setting('store.bundle.ledger_title'))
            ->getContent();

        $this->assertStringNotContainsString('line-through', $html);
        $this->assertDoesNotMatchRegularExpression('/أقلّ بـ0%/u', $html);
    }

    // ============================================================ صفر Dark Pattern

    /**
     * ⭐ **مسحٌ على المخرَج نفسه**: لا وعدَ استرجاع، ولا «بلا مخاطرة»، ولا رأيَ
     * عميلٍ مفبرك، ولا «شاهد الآن X شخصًا» — ورابط سياسة عدم الاسترجاع **حاضر**.
     */
    public function test_the_page_never_promises_a_refund_or_fakes_social_proof(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()]);

        $html = $this->actingAs($this->trainee(1000))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            // 19.4: «لا استرجاع نقديّ لأيّ مدفوعات» — فالرابط **قبل** الزرّ لا بعده
            ->assertSee(setting('store.bundle.no_refund_notice'))
            ->assertSee(route('store.refund-policy'))
            // البديل الأمين: وصولٌ دائم — وهو أقوى لأنّه صادق
            ->assertSee(setting('store.bundle.fact_lifetime'))
            ->getContent();

        foreach ([
            'ضمان استرجاع', 'استرجاع فلوسك', 'استرد فلوسك', 'بلا مخاطرة', 'من غير مخاطرة',
            'شاهد الآن', 'شخصًا يشاهدون', 'رأي العملاء', 'آراء العملاء', 'شهادات العملاء',
            'العرض ينتهي خلال ساعة',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html,
                "الصفحة فيها «{$forbidden}» — وده Dark Pattern أو وعدٌ يخالف 19.4.");
        }
    }

    // ============================================================ الشهادة بشرطها

    /** ⭐ 8: لا وعدَ بشهادةٍ إلّا لتدريبٍ **له امتحانٌ مفعَّل**، ومعها **شرطها ودرجته**. */
    public function test_the_certificate_block_appears_only_with_a_real_exam(): void
    {
        $course = $this->course();
        $bundle = $this->bundle([$course, $this->product()]);

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertDontSee(setting('store.bundle.certificate_title'));

        \App\Models\Exam::create([
            'examable_type' => $course::class,
            'examable_id' => $course->id,
            'title_ar' => 'الامتحان النهائيّ',
            'pass_score' => 75,
            'is_active' => true,
        ]);

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(setting('store.bundle.certificate_title'))
            // الدرجة **من الامتحان** لا رقمًا محروقًا
            ->assertSee('75%');
    }

    // ============================================================ حالة المالك

    /** ⭐ من اشترى الباقة يرى «معاك بالفعل» ورابط المكتبة **بدل** الزرّ (24.5). */
    public function test_an_owner_sees_the_library_link_instead_of_the_buy_button(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()], ['price_coins' => 420]);
        $user = $this->trainee(1000);

        LibraryEntitlement::create([
            'user_id' => $user->id,
            'itemable_type' => $bundle::class,
            'itemable_id' => $bundle->id,
            'source' => 'purchase',
        ]);

        $this->actingAs($user)
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(setting('store.bundle.owned_text'))
            ->assertSee(setting('store.bundle.library_link_text'))
            ->assertDontSee(setting('store.bundle.cta_label'));
    }
}
