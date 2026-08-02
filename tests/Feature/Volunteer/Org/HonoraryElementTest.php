<?php

namespace Tests\Feature\Volunteer\Org;

use App\Models\Membership;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * العنصر الشرفيّ «أخوكم» (13.4-ص): يظهر في أعلى الشجرة وفي رأس صفحة الأعضاء،
 * و**لا يُحتسَب** في عدّاد الأعضاء ولا الشبكة ولا نطاقات الإشراف ولا صحّة القسم.
 */
class HonoraryElementTest extends OrgTestCase
{
    public function test_honorary_element_is_never_counted_in_department_counters(): void
    {
        $user = $this->actorWithRole('VOL-C1', 'coordinator');

        $response = $this->actingAs($user)->get(route('volunteer.department'));

        $counters = $response->viewData('counters');
        $cards = collect($response->viewData('cards'));
        $honoraryMemberships = Membership::whereHas('position', fn ($q) => $q->where('is_honorary', true))->count();

        $this->assertSame(1, $honoraryMemberships, 'لازم يكون فيه عنصر شرفيّ واحد في البيانات التجريبيّة');
        $this->assertSame(Membership::whereHas('position', fn ($q) => $q->where('is_honorary', false))->count(), $counters['members']);
        $this->assertFalse($cards->contains('code', 'VOL-HON'), 'العنصر الشرفيّ لا يظهر صفًّا في قائمة الأعضاء');
    }

    public function test_honorary_element_is_shown_as_an_honorary_line_only(): void
    {
        $user = $this->actorWithRole('VOL-C1', 'coordinator');

        $honorary = $this->actingAs($user)->get(route('volunteer.department'))->viewData('honorary');

        $this->assertNotNull($honorary);
        $this->assertSame(setting('volunteer.honorary.label_ar'), $honorary['label']);
    }

    public function test_honorary_node_sits_on_top_of_the_chart_without_any_operational_indicator(): void
    {
        $user = $this->actorWithRole('VOL-DIR', 'director');

        $chart = $this->actingAs($user)->get(route('volunteer.org'))->viewData('chart');
        $nodes = collect($chart['nodes']);
        $honorary = $nodes->firstWhere('honorary', true);

        $this->assertNotNull($honorary, 'عقدة «أخوكم» لازم تكون موجودة');
        $this->assertNull($honorary['parent'], 'موقعه دائمًا أعلى الشجرة');
        // بلا Rep · بلا حِمل · بلا إشغال (13.4-ص-ب)
        $this->assertNull($honorary['rep_label']);
        $this->assertNull($honorary['load']);
        $this->assertNull($honorary['occupancy']);

        // وكلّ الجذور الأخرى صارت تحته — ولا عقدة أخرى بلا أب
        $this->assertSame(1, $nodes->whereNull('parent')->count());
    }

    public function test_honorary_element_is_excluded_from_network_and_member_counters(): void
    {
        $user = $this->actorWithRole('VOL-DIR', 'director');

        $chart = $this->actingAs($user)->get(route('volunteer.org'))->viewData('chart');
        $nonHonorary = Membership::whereHas('position', fn ($q) => $q->where('is_honorary', false))->count();

        $this->assertSame($nonHonorary, $chart['entity_members']);
        // شبكة الدايركتور = كلّ الأعضاء عداه — والعنصر الشرفيّ خارجها تمامًا
        $this->assertSame($nonHonorary - 1, $chart['network_total']);
    }

    public function test_honorary_element_disappears_when_the_setting_is_off(): void
    {
        Setting::where('key', 'volunteer.honorary.enabled')->update(['value' => '0']);
        Cache::forget('settings');

        $user = $this->actorWithRole('VOL-DIR', 'director');
        $chart = $this->actingAs($user)->get(route('volunteer.org'))->viewData('chart');

        $this->assertFalse(collect($chart['nodes'])->contains('honorary', true));
    }
}
