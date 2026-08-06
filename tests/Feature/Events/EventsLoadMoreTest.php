<?php

namespace Tests\Feature\Events;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ تمرير تدريجيّ بدل ترقيم الصفحات (13.1 · قرار §25 دستوريّ صريح — «مرفوض ⛔:
 * ترقيم الصفحات بدل التمرير اللانهائيّ»). لا `?page=` هنا إطلاقًا — الجلبة
 * التالية بـ`offset` وردّها Fragment وحده (كروت لا صفحة كاملة).
 */
class EventsLoadMoreTest extends EventsTestCase
{
    public function test_cards_view_shows_first_batch_then_loads_the_rest_by_offset_not_page(): void
    {
        $this->setSetting('events.list.per_page', '2');

        $this->makeEvent(['slug' => 'e1', 'title_ar' => 'فعاليّة الأولى', 'starts_at' => now()->addDays(1), 'ends_at' => now()->addDays(1)->addHour()]);
        $this->makeEvent(['slug' => 'e2', 'title_ar' => 'فعاليّة الثانية', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
        $this->makeEvent(['slug' => 'e3', 'title_ar' => 'فعاليّة الثالثة', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);
        $user = $this->trainee();

        // الفترة الافتراضيّة «upcoming» ترتّب بالأقرب أوّلًا — فأوّل صفحة تحمل e1,e2
        $first = $this->actingAs($user)->get(route('events.index'))
            ->assertOk()
            ->assertSee('فعاليّة الأولى')
            ->assertSee('فعاليّة الثانية')
            ->assertDontSee('فعاليّة الثالثة');

        $first->assertSee(route('events.more', ['offset' => 2]), false);
        $first->assertDontSee('?page=', false);

        $this->actingAs($user)
            ->get(route('events.more', ['offset' => 2]))
            ->assertOk()
            ->assertSee('فعاليّة الثالثة')
            ->assertDontSee('فعاليّة الأولى')
            ->assertDontSee('فعاليّة الثانية');

        $this->actingAs($user)
            ->get(route('events.more', ['offset' => 4]))
            ->assertOk()
            ->assertDontSee('فعاليّة الأولى')
            ->assertDontSee('فعاليّة الثانية')
            ->assertDontSee('فعاليّة الثالثة');
    }

    public function test_load_more_carries_active_filters_forward(): void
    {
        $this->setSetting('events.list.per_page', '20');

        $this->makeEvent(['slug' => 'online-one', 'title_ar' => 'فعاليّة أونلاين', 'mode' => 'online']);
        $this->makeEvent(['slug' => 'offline-one', 'title_ar' => 'فعاليّة أوفلاين', 'mode' => 'offline']);
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('events.index', ['mode' => 'online']))
            ->assertOk()
            ->assertSee('فعاليّة أونلاين')
            ->assertDontSee('فعاليّة أوفلاين');

        // فلتر النوع لازم يفضل شغّال على الجلبة التالية كمان
        $this->actingAs($user)
            ->get(route('events.more', ['mode' => 'online', 'offset' => 0]))
            ->assertOk()
            ->assertSee('فعاليّة أونلاين')
            ->assertDontSee('فعاليّة أوفلاين');
    }

    public function test_calendar_view_has_no_load_more_button(): void
    {
        $this->makeEvent(['title_ar' => 'فعاليّة التقويم']);
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('events.index', ['view' => 'calendar']))
            ->assertOk()
            ->assertDontSee('data-load-more', false);
    }

    private function setSetting(string $key, string $value): void
    {
        Setting::query()->where('key', $key)->update(['value' => $value]);
        Cache::forget('settings');
    }
}
