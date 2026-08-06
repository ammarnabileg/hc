<?php

namespace Tests\Feature\Store;

/**
 * ⭐ تمرير تدريجيّ بدل ترقيم الصفحات (13.1 · قرار §25 دستوريّ صريح — «مرفوض ⛔:
 * ترقيم الصفحات بدل التمرير اللانهائيّ»). لا `?page=` هنا إطلاقًا — الجلبة
 * التالية بـ`offset` وردّها Fragment وحده (كروت لا صفحة كاملة).
 */
class StoreLoadMoreTest extends StoreTestCase
{
    public function test_grid_shows_first_batch_then_loads_the_rest_by_offset_not_page(): void
    {
        $this->setting('store.grid.per_page', '2');

        $this->product(['slug' => 'p1', 'name_ar' => 'منتج الأوّل', 'created_at' => now()->subMinutes(3)]);
        $this->product(['slug' => 'p2', 'name_ar' => 'منتج الثاني', 'created_at' => now()->subMinutes(2)]);
        $this->product(['slug' => 'p3', 'name_ar' => 'منتج الثالث', 'created_at' => now()->subMinutes(1)]);
        $user = $this->trainee(0);

        // أوّل تحميل: أحدث عنصرين فقط (الأحدث أوّلًا) — وبلا أيّ ?page=
        $first = $this->actingAs($user)->get(route('store.index'))
            ->assertOk()
            ->assertSee('منتج الثالث')
            ->assertSee('منتج الثاني')
            ->assertDontSee('منتج الأوّل');

        // زرّ التمرير التدريجيّ يحمل الإزاحة التالية — لا رقم صفحة (قرار §25)
        $first->assertSee(route('store.index.more', ['offset' => 2]), false);
        $first->assertDontSee('?page=', false);

        // الجلبة التالية Fragment: العنصر المتبقّي فقط — بلا تكرار لِما ظهر
        $this->actingAs($user)
            ->get(route('store.index.more', ['offset' => 2]))
            ->assertOk()
            ->assertSee('منتج الأوّل')
            ->assertDontSee('منتج الثاني')
            ->assertDontSee('منتج الثالث');

        // بعد النهاية: Fragment فارغ (بلا زرّ) لا خطأ ولا صفحة غير موجودة
        $this->actingAs($user)
            ->get(route('store.index.more', ['offset' => 4]))
            ->assertOk()
            ->assertDontSee('منتج الأوّل')
            ->assertDontSee('منتج الثاني')
            ->assertDontSee('منتج الثالث');
    }

    public function test_bundles_grid_also_uses_offset_load_more(): void
    {
        $this->setting('store.grid.per_page', '2');
        $product = $this->product();

        // ⭐ `bundleCards()` بلا فرز صريح — فترتيبها ترتيب الإدراج (رقم الصفّ) لا التاريخ
        $this->bundle([$product], ['slug' => 'b1', 'name_ar' => 'باقة الأولى']);
        $this->bundle([$product], ['slug' => 'b2', 'name_ar' => 'باقة الثانية']);
        $this->bundle([$product], ['slug' => 'b3', 'name_ar' => 'باقة الثالثة']);
        $user = $this->trainee(0);

        $this->actingAs($user)->get(route('store.bundles'))
            ->assertOk()
            ->assertSee('باقة الأولى')
            ->assertSee('باقة الثانية')
            ->assertDontSee('باقة الثالثة');

        $this->actingAs($user)
            ->get(route('store.bundles.more', ['offset' => 2]))
            ->assertOk()
            ->assertSee('باقة الثالثة');

        $this->actingAs($user)
            ->get(route('store.bundles.more', ['offset' => 4]))
            ->assertOk()
            ->assertDontSee('باقة الأولى')
            ->assertDontSee('باقة الثانية')
            ->assertDontSee('باقة الثالثة');
    }
}
