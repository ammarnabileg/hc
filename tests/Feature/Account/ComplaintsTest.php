<?php

namespace Tests\Feature\Account;

use App\Models\Complaint;
use App\Models\ComplaintMessage;

/**
 * الشكاوى والمقترحات (الدستور 11 · 24.5).
 */
class ComplaintsTest extends AccountTestCase
{
    public function test_user_opens_a_ticket_and_its_body_becomes_the_first_message(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->post(route('complaints.store'), [
            'type' => 'suggestion',
            'category' => 'المنصّة',
            'title' => 'اقتراح تحسين المنصّة',
            'body' => 'يا ريت يبقى فيه تنبيه قبل انتهاء مهلة الامتحان بيوم.',
        ])->assertRedirect();

        $complaint = Complaint::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('open', $complaint->status);
        $this->assertNotEmpty($complaint->number);
        $this->assertSame(1, ComplaintMessage::where('complaint_id', $complaint->id)->count());
    }

    public function test_index_shows_status_counters_and_the_selected_thread(): void
    {
        $user = $this->trainee();

        $complaint = Complaint::create([
            'number' => 'TK-T-1', 'user_id' => $user->id, 'type' => 'complaint',
            'title' => 'الفيديو بيقف', 'body' => 'الدرس التالت بيقف عند الدقيقة السابعة.', 'status' => 'open',
        ]);

        ComplaintMessage::create([
            'complaint_id' => $complaint->id, 'user_id' => $user->id, 'body' => 'الدرس التالت بيقف عند الدقيقة السابعة.',
        ]);

        $this->actingAs($user)
            ->get(route('complaints.index', ['ticket' => $complaint->id]))
            ->assertOk()
            ->assertSee('الشكاوى والمقترحات')
            ->assertSee('TK-T-1')
            ->assertSee('الدرس التالت بيقف عند الدقيقة السابعة.')
            ->assertViewHas('counts', fn ($counts) => ($counts['open'] ?? 0) === 1);
    }

    public function test_closed_ticket_is_read_only_with_a_badge(): void
    {
        $user = $this->trainee();

        $complaint = Complaint::create([
            'number' => 'TK-T-2', 'user_id' => $user->id, 'type' => 'complaint',
            'title' => 'استفسار', 'body' => 'محتاج مساعدة.', 'status' => 'open',
        ]);

        $this->actingAs($user)->post(route('complaints.close', $complaint))->assertRedirect();

        $complaint->refresh();
        $this->assertSame('closed', $complaint->status);
        $this->assertNotNull($complaint->closed_at);

        // المغلقة قراءة فقط بشارة (24.5) — والردّ عليها لا يضيف رسالة
        $this->actingAs($user)
            ->get(route('complaints.index', ['ticket' => $complaint->id]))
            ->assertOk()
            ->assertSee('مغلقة — قراءة فقط');

        $this->actingAs($user)->post(route('complaints.reply', $complaint), ['body' => 'محاولة ردّ']);
        $this->assertSame(0, ComplaintMessage::where('complaint_id', $complaint->id)->count());
    }

    public function test_reply_adds_a_message_to_an_open_ticket(): void
    {
        $user = $this->trainee();

        $complaint = Complaint::create([
            'number' => 'TK-T-3', 'user_id' => $user->id, 'type' => 'complaint',
            'title' => 'استفسار', 'body' => 'محتاج مساعدة.', 'status' => 'answered',
        ]);

        $this->actingAs($user)
            ->post(route('complaints.reply', $complaint), ['body' => 'شكرًا، بس لسّه فيه مشكلة صغيرة.'])
            ->assertRedirect();

        $this->assertSame(1, ComplaintMessage::where('complaint_id', $complaint->id)->count());
        $this->assertSame('in_review', $complaint->fresh()->status);
    }

    public function test_a_user_cannot_touch_someone_elses_ticket(): void
    {
        $owner = $this->trainee();
        $other = $this->trainee();

        $complaint = Complaint::create([
            'number' => 'TK-T-4', 'user_id' => $owner->id, 'type' => 'complaint',
            'title' => 'خاصّة', 'body' => 'محتوى خاصّ.', 'status' => 'open',
        ]);

        $this->actingAs($other)->post(route('complaints.reply', $complaint), ['body' => 'تطفّل'])->assertForbidden();
        $this->actingAs($other)->post(route('complaints.close', $complaint))->assertForbidden();
    }
}
