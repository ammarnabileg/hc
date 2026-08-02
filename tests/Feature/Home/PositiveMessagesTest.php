<?php

namespace Tests\Feature\Home;

use App\Models\PositiveMessage;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\Engagement\PositiveMessages;

/** الرسائل الإيجابيّة (2.6-ب · 2.13 · 2.9) */
class PositiveMessagesTest extends HomeTestCase
{
    private function service(): PositiveMessages
    {
        return app(PositiveMessages::class);
    }

    /** الخدمة تُرجِع رسالة من سياقها هو — لا من سياق غيره */
    public function test_for_context_returns_a_message_of_that_context_only(): void
    {
        PositiveMessage::query()->delete();

        PositiveMessage::create(['context' => 'lesson_complete', 'body_ar' => 'رسالة الدرس', 'is_active' => true]);
        PositiveMessage::create(['context' => 'streak_broken', 'body_ar' => 'رسالة الستريك', 'is_active' => true]);

        $this->assertSame('رسالة الدرس', $this->service()->forContext('lesson_complete')?->body_ar);
        $this->assertSame('رسالة الستريك', $this->service()->forContext('streak_broken')?->body_ar);
    }

    /** الموقوفة لا تظهر أبدًا — والإيقاف لا يحذف (2.6-ب) */
    public function test_paused_messages_never_show(): void
    {
        PositiveMessage::query()->delete();
        PositiveMessage::create(['context' => 'lesson_complete', 'body_ar' => 'موقوفة', 'is_active' => false]);

        $this->assertNull($this->service()->forContext('lesson_complete'));
        $this->assertDatabaseHas('positive_messages', ['body_ar' => 'موقوفة']);
    }

    /** بلا تكرار ممل: النداءات المتتالية لا تعيد نفس الرسالة ما دام في المكتبة غيرها */
    public function test_messages_do_not_repeat_while_the_library_still_has_others(): void
    {
        PositiveMessage::query()->delete();

        foreach (['أ', 'ب', 'ج'] as $body) {
            PositiveMessage::create(['context' => 'lesson_complete', 'body_ar' => $body, 'is_active' => true]);
        }

        $this->startSession();

        $seen = [];
        for ($i = 0; $i < 3; $i++) {
            $seen[] = $this->service()->forContext('lesson_complete')?->body_ar;
        }

        $this->assertSame(['أ', 'ب', 'ج'], collect($seen)->sort()->values()->all());
    }

    /** إيقاف الميزة يوقف كلّ شيء بلا حذف (2.13-أ) */
    public function test_disabling_the_feature_stops_everything(): void
    {
        $this->forceSetting('engagement.positive.enabled', '0');

        $this->assertNull($this->service()->forContext('surprise'));
        $this->assertFalse($this->service()->shouldShowIcon());
    }

    /** الاحتمال إعداد حقيقيّ: 100% تظهر دائمًا و0% لا تظهر أبدًا (2.6-ب) */
    public function test_icon_chance_is_a_real_setting(): void
    {
        $this->forceSetting('engagement.positive.icon_chance_percent', '100');
        $this->assertTrue($this->service()->shouldShowIcon());

        $this->forceSetting('engagement.positive.icon_chance_percent', '0');
        $this->assertFalse($this->service()->shouldShowIcon());
    }

    /** الأيقونة تظهر فعلًا على الصفحة حين تنجح القرعة */
    public function test_surprise_icon_renders_on_the_landing_page(): void
    {
        $this->forceSetting('engagement.positive.icon_chance_percent', '100');

        $this->get('/')->assertOk()->assertSee('رسالة إيجابيّة مستنّياك');

        $this->forceSetting('engagement.positive.icon_chance_percent', '0');

        $this->get('/')->assertOk()->assertDontSee('رسالة إيجابيّة مستنّياك');
    }

    /** تذكرة المفاجأة: تُمنَح مرّة، والحدّ اليوميّ يوقف الثانية (2.9 — بلا استغلال) */
    public function test_surprise_ticket_respects_its_daily_cap(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('positive.ticket'))->assertRedirect();

        $this->assertSame(1, Transaction::where('user_id', $user->id)->where('source', 'positive_message')->count());

        $this->actingAs($user)->post(route('positive.ticket'))->assertRedirect();

        $this->assertSame(1, Transaction::where('user_id', $user->id)->where('source', 'positive_message')->count());
    }

    // ------------------------------------------------------------ شاشة الأدمن

    /** الشاشة محروسة بصلاحيّتها — والمتدرّب لا يفتحها (12.2.1) */
    public function test_admin_screen_is_permission_guarded(): void
    {
        $this->actingAs($this->user())->get(route('admin.positive.index'))->assertForbidden();
    }

    /** والزائر لا يصل أصلًا — يُوجَّه للدخول */
    public function test_admin_screen_is_closed_for_guests(): void
    {
        $this->get(route('admin.positive.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_list_add_edit_toggle_and_delete(): void
    {
        $admin = $this->user('gamification_admin', 'مسؤول التلعيب');

        $this->actingAs($admin)->get(route('admin.positive.index'))
            ->assertOk()
            ->assertSee('الرسائل الإيجابيّة');

        // إضافة
        $this->actingAs($admin)->post(route('admin.positive.store'), [
            'context' => 'lesson_complete',
            'body_ar' => 'رسالة أضافها الأدمن',
            'emoji' => '✅',
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $message = PositiveMessage::where('body_ar', 'رسالة أضافها الأدمن')->firstOrFail();
        $this->assertSame($admin->id, $message->created_by);

        // تعديل
        $this->actingAs($admin)->put(route('admin.positive.update', $message), [
            'context' => 'lesson_complete',
            'body_ar' => 'نصّ بعد التعديل',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('نصّ بعد التعديل', $message->fresh()->body_ar);

        // إيقاف
        $this->actingAs($admin)->post(route('admin.positive.toggle', $message))->assertRedirect();
        $this->assertFalse($message->fresh()->is_active);

        // حذف
        $this->actingAs($admin)->delete(route('admin.positive.destroy', $message))->assertRedirect();
        $this->assertDatabaseMissing('positive_messages', ['id' => $message->id]);
    }

    /** سياق خارج القائمة المعتمَدة مرفوض — رسالة لن تظهر أبدًا لا تُحفَظ صامتة */
    public function test_unknown_context_is_rejected(): void
    {
        $admin = $this->user('gamification_admin', 'مسؤول التلعيب');

        $this->actingAs($admin)->post(route('admin.positive.store'), [
            'context' => 'context_does_not_exist',
            'body_ar' => 'رسالة بسياق غير معروف',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('positive_messages', ['body_ar' => 'رسالة بسياق غير معروف']);
    }

    /** إعدادات الميزة تُحفَظ وتسري فورًا بلا إعادة نشر (2.13-د) */
    public function test_admin_can_save_and_reset_feature_settings(): void
    {
        $admin = $this->user('gamification_admin', 'مسؤول التلعيب');

        $this->actingAs($admin)->post(route('admin.positive.settings'), [
            'settings' => ['engagement.positive.icon_chance_percent' => '7'],
        ])->assertRedirect();

        $this->assertSame('7', Setting::where('key', 'engagement.positive.icon_chance_percent')->value('value'));
        $this->assertSame(7, (int) setting('engagement.positive.icon_chance_percent'));

        $this->actingAs($admin)->post(route('admin.positive.settings.reset'))->assertRedirect();

        $this->assertSame('3', Setting::where('key', 'engagement.positive.icon_chance_percent')->value('value'));
    }

    /** السياقات نفسها إعداد: يضيف الأدمن سياقًا جديدًا ويربط به رسالة بلا كود (2.13) */
    public function test_admin_can_add_a_new_context_and_bind_a_message_to_it(): void
    {
        $admin = $this->user('gamification_admin', 'مسؤول التلعيب');

        $this->actingAs($admin)->post(route('admin.positive.settings'), [
            'settings' => ['engagement.positive.contexts' => "any = أيّ لحظة\nnew_context = سياق جديد"],
        ])->assertRedirect();

        $this->assertSame('سياق جديد', $this->service()->contexts()['new_context'] ?? null);

        // نفرّغ السياق العامّ حتى تكون الرسالة العائدة من السياق الجديد وحده
        PositiveMessage::where('context', PositiveMessages::ANY)->delete();

        $this->actingAs($admin)->post(route('admin.positive.store'), [
            'context' => 'new_context',
            'body_ar' => 'رسالة السياق الجديد',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('رسالة السياق الجديد', $this->service()->forContext('new_context')?->body_ar);
    }
}
