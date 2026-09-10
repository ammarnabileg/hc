<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

/**
 * ⭐ [2026-09-10] شاشة قائمة الفعاليّات (12.11): «معاينة صفحة الفعاليّة قبل
 * النشر» · «القائمة جدولًا لا كروتًا» بعمودَي الغلاف والسعر.
 */
class AdminEventsListScreenTest extends AdminVolunteerTestCase
{
    private function publishedEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'title_ar' => 'فعاليّة القائمة',
            'title_en' => 'List Event',
            'slug' => 'list-event-test',
            'mode' => 'online',
            'starts_at' => now()->addDays(2),
            'status' => 'published',
            'attendance_code' => 'LST12345',
        ], $overrides));
    }

    /**
     * ⭐ «معاينة صفحة الفعاليّة قبل النشر» (12.11) — الرابط يفتح `events.show`
     * ومحروسٌ بـ`events.manage` تحديدًا (لا `events.edit`)، لأنّ
     * `Trainee\EventController::visible()` يسمح برؤية المسوَّدة لمالك هذه
     * الصلاحيّة وحده.
     */
    public function test_preview_link_appears_for_a_user_with_events_manage(): void
    {
        $event = $this->publishedEvent();
        $admin = $this->grant($this->makeUser(), 'events.list', 'events.manage');

        $html = $this->actingAs($admin)
            ->get(route('admin.events.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString(route('events.show', $event->slug), $html, 'رابط المعاينة غير ظاهر لمن يملك events.manage.');
    }

    /** والمحظور يُخفى لا يُعطَّل (2.15-أ-7): بلا events.manage لا يظهر الرابط إطلاقًا */
    public function test_preview_link_is_hidden_without_events_manage(): void
    {
        $this->publishedEvent();
        $admin = $this->grant($this->makeUser(), 'events.list', 'events.edit');

        $html = $this->actingAs($admin)
            ->get(route('admin.events.index'))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString(setting('admin.events.index.maayna', 'معاينة'), $html, 'رابط المعاينة ظهر لمن لا يملك events.manage.');
    }

    /** الجدول الجديد يعرض أعمدته الثمانية فعليًّا — لا كروتًا بلا غلافٍ ولا سعر */
    public function test_the_table_view_shows_cover_and_price_columns(): void
    {
        $event = $this->publishedEvent([
            'title_ar' => 'ورشة مدفوعة بغلاف',
            'slug' => 'paid-cover-event-test',
            'cover_path' => 'media/events/cover-test.webp',
            'price_coins' => 25,
            'price_tickets' => 1,
        ]);

        $admin = $this->grant($this->makeUser(), 'events.list');

        $html = $this->actingAs($admin)
            ->get(route('admin.events.index', ['view' => 'table']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('<table', $html, 'القائمة ما زالت كروتًا لا جدولًا.');
        $this->assertStringContainsString(Storage::url($event->cover_path), $html, 'الغلاف غير ظاهر في الجدول.');
        $this->assertStringContainsString('25', $html, 'سعر الكوينز غير ظاهر في الجدول.');
        $this->assertStringContainsString(setting('admin.events.index.col_alsar', 'السعر'), $html);
        $this->assertStringContainsString(setting('admin.events.index.col_ghlaf', 'الغلاف'), $html);
    }

    /** ⭐ 24.2: بحثٌ بلا نتائج يقول كده صراحةً بدل «لا فعاليّات — أنشئ أوّل لقاء». */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        $this->publishedEvent();
        $admin = $this->grant($this->makeUser(), 'events.list');

        $response = $this->actingAs($admin)
            ->get(route('admin.events.index', ['view' => 'table', 'q' => 'zzzznotexist']))
            ->assertOk();

        $response->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $response->assertDontSee(
            setting('events.empty_message', 'لا فعاليّات — أنشئ أوّل لقاء.'),
            false,
        );
    }

    /** وشاشةٌ فارغةٌ فعليًّا (بلا فلتر ولا فعاليّات، حتّى على التبويب الافتراضيّ) تعرض الرسالة الأصليّة. */
    public function test_an_actually_empty_screen_without_filters_keeps_the_original_start_message(): void
    {
        Event::query()->delete();
        $admin = $this->grant($this->makeUser(), 'events.list');

        $response = $this->actingAs($admin)
            ->get(route('admin.events.index', ['view' => 'table']))
            ->assertOk();

        $response->assertSee(
            setting('events.empty_message', 'لا فعاليّات — أنشئ أوّل لقاء.'),
            false,
        );
        $response->assertDontSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
    }

    /** وفعاليّة مجّانيّة بلا غلاف تُعرَض «مجّانيّة» بأيقونة افتراضيّة — لا انهيار على null */
    public function test_a_free_event_without_a_cover_shows_the_free_label(): void
    {
        $this->publishedEvent([
            'title_ar' => 'ورشة مجّانيّة',
            'slug' => 'free-event-test',
            'price_coins' => 0,
            'price_tickets' => 0,
        ]);

        $admin = $this->grant($this->makeUser(), 'events.list');

        $html = $this->actingAs($admin)
            ->get(route('admin.events.index', ['view' => 'table']))
            ->assertOk()->getContent();

        $this->assertStringContainsString(setting('admin.events.index.mjanya', 'مجّانيّة'), $html);
    }
}
