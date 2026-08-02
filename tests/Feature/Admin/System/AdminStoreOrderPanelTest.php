<?php

namespace Tests\Feature\Admin\System;

use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * بانل الطلب (24.3-أوّلًا · 2.15-أ-6): «التفاصيل في بانل لا صفحة جديدة».
 * وكانت القائمة ترندر بينما فتح أيّ طلب ينهار بـ500 — فلا فاتورة ولا سطور.
 */
class AdminStoreOrderPanelTest extends SystemTestCase
{
    private function order(): Order
    {
        $order = Order::create([
            'number' => 'ORD-TEST-1',
            'user_id' => $this->admin(['orders.list'])->id,
            'subtotal' => 300,
            'discount' => 50,
            'total' => 250,
            'currency_id' => Currency::query()->where('code', 'coins')->value('id'),
            'status' => 'paid',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'purchasable_type' => 'product',
            'purchasable_id' => 1,
            'title' => 'دورة التصميم',
            'price' => 300,
            'quantity' => 1,
        ]);

        return $order;
    }

    public function test_opening_an_order_renders_its_invoice_and_lines(): void
    {
        $admin = $this->admin(['orders.list']);
        $order = $this->order();

        $this->actingAs($admin)
            ->get(route('admin.store.orders.show', $order))
            ->assertOk()
            ->assertSee('ORD-TEST-1')
            ->assertSee('دورة التصميم')
            ->assertSee('سطور الفاتورة')
            // ⛔ القاعدة الثابتة تُعرَض مع الفاتورة: لا استرجاع نقديّ (19.4)
            ->assertSee('مافيش استرجاع نقديّ');
    }

    public function test_the_panel_is_closed_to_whoever_does_not_own_orders(): void
    {
        $this->actingAs($this->admin([]))
            ->get(route('admin.store.orders.show', $this->order()))
            ->assertForbidden();
    }
}
