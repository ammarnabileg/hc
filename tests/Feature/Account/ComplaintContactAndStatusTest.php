<?php

namespace Tests\Feature\Account;

use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\Setting;
use App\Services\Account\ComplaintService;
use App\Services\Admin\Content\GuidanceComposer;
use Illuminate\Support\Facades\Cache;

/**
 * الشكاوى: حقل التواصل · مفتاح الأسباب الموحَّد · دورة الحالة (الدستور 11).
 */
class ComplaintContactAndStatusTest extends AccountTestCase
{
    /**
     * ⚠️ العطل المُثبَت: العمود موجود وشاشتا الأدمن تقرآنه، والفورم لا يحويه
     * والمتحكّم لا يحفظه — فكان إرسال `wants_contact=1` صراحةً يُخزَّن 0،
     * ويرى الأدمن «لا أحد يطلب التواصل» أبدًا.
     */
    public function test_wants_contact_is_saved_and_reaches_the_admin(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->post(route('complaints.store'), [
            'type' => 'complaint',
            'wants_contact' => 1,
            'category' => 'المنصّة',
            'title' => 'الفيديو بيقف',
            'body' => 'الدرس التالت بيقف عند الدقيقة السابعة.',
        ])->assertRedirect();

        $complaint = Complaint::where('user_id', $user->id)->firstOrFail();

        $this->assertTrue((bool) $complaint->wants_contact);
    }

    public function test_saying_no_to_contact_is_stored_as_no(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->post(route('complaints.store'), [
            'type' => 'suggestion',
            'wants_contact' => 0,
            'category' => 'المنصّة',
            'title' => 'اقتراح بسيط',
            'body' => 'يا ريت نضيف تنبيه قبل المهلة بيوم.',
        ])->assertRedirect();

        $this->assertFalse((bool) Complaint::where('user_id', $user->id)->firstOrFail()->wants_contact);
    }

    /** الحقل **مطلوب** كبقيّة حقول القسم 11 */
    public function test_contact_question_is_required(): void
    {
        $this->actingAs($this->trainee())->post(route('complaints.store'), [
            'type' => 'complaint',
            'category' => 'المنصّة',
            'title' => 'عنوان كافٍ',
            'body' => 'نصّ طويل بما يكفي للتحقّق.',
        ])->assertSessionHasErrors('wants_contact');
    }

    /**
     * ⚠️ العطل المُثبَت: مفتاحان لمعنًى واحد — الفورم يقرأ
     * `account.complaints.categories` والأدمن يقرأ `complaints.reasons`،
     * فتحرير الأدمن بلا أثر على ما يراه المستخدم.
     */
    public function test_admin_edited_reasons_reach_the_user_form(): void
    {
        Setting::updateOrCreate(
            ['key' => ComplaintService::REASONS_KEY],
            [
                'group' => 'complaints',
                'label_ar' => 'أسباب الشكاوى والمقترحات',
                'type' => 'json',
                'value' => json_encode(['سبب اختباريّ'], JSON_UNESCAPED_UNICODE),
                'default_value' => json_encode(ComplaintService::defaultReasons(), JSON_UNESCAPED_UNICODE),
            ],
        );

        Cache::forget('settings');

        $this->assertSame(['سبب اختباريّ'], ComplaintService::categories());
        $this->assertSame(['سبب اختباريّ'], app(GuidanceComposer::class)->complaintReasons()->all());

        $this->actingAs($this->trainee())->get(route('complaints.index'))
            ->assertOk()
            ->assertSee('سبب اختباريّ', false);
    }

    /**
     * ⚠️ العطل المُثبَت: `answered` غير قابلة للوصول — الأدمن يعرف 3 حالات
     * والتحقّق `in:open,in_review,closed`، فالردّ لا يقدّم الحالة إطلاقًا
     * وعدّاد «تمّ الردّ» صفرٌ دائمًا.
     */
    public function test_admin_reply_advances_the_ticket_to_answered(): void
    {
        $user = $this->trainee();
        $admin = $this->trainee(['code' => 'UADMREP1']);

        $complaint = Complaint::create([
            'number' => 'TK-ST-1', 'user_id' => $user->id, 'type' => 'complaint',
            'title' => 'استفسار', 'body' => 'محتاج مساعدة.', 'status' => 'open',
        ]);

        app(GuidanceComposer::class)->reply($complaint, $admin, 'اتظبطت، جرّب تاني.', internal: false);

        $this->assertSame('answered', $complaint->fresh()->status);
        $this->assertSame(1, ComplaintMessage::where('complaint_id', $complaint->id)->count());
    }

    /** الملاحظة الداخليّة لا تقدّم الحالة — لأنّها لم تصل للمستخدم أصلًا */
    public function test_internal_note_does_not_advance_the_status(): void
    {
        $user = $this->trainee();
        $admin = $this->trainee(['code' => 'UADMINT1']);

        $complaint = Complaint::create([
            'number' => 'TK-ST-2', 'user_id' => $user->id, 'type' => 'complaint',
            'title' => 'استفسار', 'body' => 'محتاج مساعدة.', 'status' => 'open',
        ]);

        app(GuidanceComposer::class)->reply($complaint, $admin, 'راجعوا الدرس ده.', internal: true);

        $this->assertSame('open', $complaint->fresh()->status);
    }

    /** «مفتوحة» عند المستخدم و«جديدة» عند الأدمن كانتا حالتين في العين الواحدة */
    public function test_status_labels_are_one_for_user_and_admin(): void
    {
        $this->assertSame(
            ComplaintService::statusLabels(),
            GuidanceComposer::complaintStatuses(),
        );

        $this->assertArrayHasKey('answered', GuidanceComposer::complaintStatuses());
    }
}
