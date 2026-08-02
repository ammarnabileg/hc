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
}
