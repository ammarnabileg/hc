<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Coupon;
use App\Models\Event;

/**
 * فورم الفعاليّة (12.11): الحقول الناقصة والانهيار عند الحقل الاختياريّ.
 */
class AdminEventFormFieldsTest extends AdminVolunteerTestCase
{
    /**
     * ⭐ العنوان الإنجليزيّ **اختياريّ** — وكان غيابه يُسقِط `POST /admin/events`
     * بـ500 لأنّ `$data['title_en']` يُقرأ بلا `??` عند بناء الـslug.
     */
    public function test_creating_an_event_without_english_title_does_not_crash(): void
    {
        $admin = $this->grant($this->makeUser(), 'events.list', 'events.create', 'events.edit');

        $this->actingAs($admin)
            ->post(route('admin.events.save'), [
                'title_ar' => 'لقاء بلا عنوان إنجليزيّ',
                'mode' => 'online',
                'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'join_link' => 'https://meet.example.com/room',
                'status' => 'published',
            ])
            ->assertRedirect();

        $event = Event::query()->where('title_ar', 'لقاء بلا عنوان إنجليزيّ')->firstOrFail();

        $this->assertNull($event->title_en);
        $this->assertNotEmpty($event->slug);
    }

    /** ⭐ السعر والكوبون والغلاف صاروا حقولًا فعليّة تُحفَظ (12.11). */
    public function test_event_price_coupon_and_cover_are_saved_from_the_form(): void
    {
        $admin = $this->grant($this->makeUser(), 'events.list', 'events.create', 'events.edit');

        $coupon = Coupon::create([
            'code' => 'EVT10',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.events.save'), [
                'title_ar' => 'ورشة مدفوعة',
                'mode' => 'online',
                'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'join_link' => 'https://meet.example.com/paid',
                'price_coins' => 40,
                'price_tickets' => 2,
                'coupon_id' => $coupon->id,
                'cover_path' => 'media/events/cover.webp',
                'status' => 'published',
            ])
            ->assertRedirect();

        $event = Event::query()->where('title_ar', 'ورشة مدفوعة')->firstOrFail();

        $this->assertSame(40.0, (float) $event->price_coins);
        $this->assertSame(2.0, (float) $event->price_tickets);
        $this->assertSame($coupon->id, (int) $event->coupon_id);
        $this->assertSame('media/events/cover.webp', $event->cover_path);
    }

    /**
     * ⭐ [2026-09-10] «المكان + الخريطة» (12.11) — الطبقة كانت نصف جاهزة:
     * الموديل يحوّل lat/lng والمايجريشن يملك عمودَيهما والصفحة العامّة تعرض
     * رابط الخريطة بالفعل، لكنّ فورم الإدارة كان بلا حقلَي إدخالٍ لهما فلا
     * سبيل لضبطهما أصلًا.
     */
    public function test_event_lat_lng_are_saved_from_the_form(): void
    {
        $admin = $this->grant($this->makeUser(), 'events.list', 'events.create', 'events.edit');

        $this->actingAs($admin)
            ->post(route('admin.events.save'), [
                'title_ar' => 'ورشة أوفلاين بموقع',
                'mode' => 'offline',
                'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'location' => 'مقرّ الجمعيّة',
                'lat' => '30.0444196',
                'lng' => '31.2357116',
                'status' => 'published',
            ])
            ->assertRedirect();

        $event = Event::query()->where('title_ar', 'ورشة أوفلاين بموقع')->firstOrFail();

        $this->assertSame('30.0444196', (string) $event->lat);
        $this->assertSame('31.2357116', (string) $event->lng);
    }

    /** وغياب الإحداثيّتين لا يُسقِط الحفظ — اختياريّتان دومًا */
    public function test_event_lat_lng_are_optional_and_do_not_crash_save(): void
    {
        $admin = $this->grant($this->makeUser(), 'events.list', 'events.create', 'events.edit');

        $this->actingAs($admin)
            ->post(route('admin.events.save'), [
                'title_ar' => 'ورشة أونلاين بلا موقع',
                'mode' => 'online',
                'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'join_link' => 'https://meet.example.com/no-geo',
                'status' => 'published',
            ])
            ->assertRedirect();

        $event = Event::query()->where('title_ar', 'ورشة أونلاين بلا موقع')->firstOrFail();

        $this->assertNull($event->lat);
        $this->assertNull($event->lng);
    }

    /** وقيمةٌ خارج مدى الإحداثيّات المعتبَر (±90/±180) تُرفَض لا تُحفَظ زورًا */
    public function test_event_lat_out_of_range_is_rejected(): void
    {
        $admin = $this->grant($this->makeUser(), 'events.list', 'events.create', 'events.edit');

        $this->actingAs($admin)
            ->post(route('admin.events.save'), [
                'title_ar' => 'ورشة بإحداثيّة غلط',
                'mode' => 'offline',
                'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'location' => 'مكان ما',
                'lat' => '200',
                'lng' => '31.2357116',
                'status' => 'published',
            ])
            ->assertSessionHasErrors('lat');

        $this->assertDatabaseMissing('events', ['title_ar' => 'ورشة بإحداثيّة غلط']);
    }
}
