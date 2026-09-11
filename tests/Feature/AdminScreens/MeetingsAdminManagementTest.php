<?php

namespace Tests\Feature\AdminScreens;

use App\Models\MediaItem;
use App\Models\Meeting;
use App\Models\MeetingPost;
use App\Models\MeetingQuestion;
use App\Models\Permission;
use App\Support\Access\AccessEngine;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ⭐ [2026-09-11] اتّساع شاشة «الاجتماعات» في اللوحة إلى نصّ 24.2-أوّلًا:
 * **+ اجتماع** · **تبديل (تقويم/جدول)** · عمودا **المحضر والمرفقات**
 * و**التسجيل** · وإجراءات **إدارة الكود/الأسئلة** و**رفع المحضر** و**تثبيت
 * بوست** و**إلغاء بسبب**.
 *
 * وسؤال هذه الاختبارات ليس «هل الزرّ موجود؟» بل **«هل الفعل من اللوحة يُنتج
 * ما يُنتجه الفعل من لوحة التطوّع حرفيًّا؟»** — لأنّ الغرض المعلَن من التوسعة
 * هو **إعادة استخدام** خدمات لوحة التطوّع لا بناء بابٍ ثانٍ بعقدٍ شبيه.
 * ولذلك يقارن أكثرُ من اختبارٍ هنا **الصفَّ الناتج من البابين** لا حالةَ
 * بابٍ واحد.
 */
class MeetingsAdminManagementTest extends ScreensTestCase
{
    /**
     * ⭐ الدليل الحاسم على إعادة الاستخدام: نفس الحمولة على البابين ⟵ صفّان
     * متطابقان في كلّ ما يعنينا (الجمهور · الكيان · المالك · الكود · الأسئلة
     * · الحالة). ولو نُسِخ المنطق يومًا وانحرف، ينكسر هذا الاختبار أوّلًا.
     */
    public function test_creating_from_the_panel_produces_the_same_row_as_the_volunteer_screen(): void
    {
        $owner = $this->owner();
        $entity = $this->makeEntity();

        $payload = fn (string $title) => [
            'title' => $title,
            'description' => 'أجندة الشهر',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'audience' => 'entity',
            'entity_id' => $entity->id,
            'external_link' => 'https://meet.example.test/room',
            'attendance_code' => 'HC-2026',
            'questions' => [['prompt' => 'إيه الأجندة؟', 'options' => 'أ,ب', 'correct_answer' => 'أ']],
        ];

        $this->actingAs($owner)->post(route('admin.meetings.store'), $payload('اجتماع من اللوحة'))->assertRedirect();
        $this->actingAs($owner)->post(route('volunteer.meetings.store'), $payload('اجتماع من التطوّع'))->assertRedirect();

        $fromPanel = Meeting::query()->where('title', 'اجتماع من اللوحة')->firstOrFail();
        $fromVolunteer = Meeting::query()->where('title', 'اجتماع من التطوّع')->firstOrFail();

        $shape = fn (Meeting $m) => [
            'audience' => $m->audience,
            'entity_id' => (int) $m->entity_id,
            'owner_id' => (int) $m->owner_id,
            'status' => $m->status,
            'attendance_code' => $m->attendance_code,
            'external_link' => $m->external_link,
            'questions' => $m->questions()->count(),
        ];

        $this->assertSame($shape($fromVolunteer), $shape($fromPanel));
        $this->assertSame($entity->id, (int) $fromPanel->entity_id);
        $this->assertSame(1, $fromPanel->questions()->count());
    }

    /** «بلا صلاحيّة: يرى اجتماعات نطاقه فقط **بلا إنشاء** ولا إدارة كود» (24.2-أوّلًا) */
    public function test_creating_needs_the_same_create_permission_the_volunteer_route_needs(): void
    {
        $reader = $this->admin(['meetings.list', 'meetings.view']);

        $this->actingAs($reader)
            ->post(route('admin.meetings.store'), [
                'title' => 'اجتماع ممنوع',
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'audience' => 'entity',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('meetings', ['title' => 'اجتماع ممنوع']);

        // والزرّ نفسه مخفيّ لا معطَّل (2.15-أ-7)
        $this->actingAs($reader)
            ->get(route('admin.meetings.index'))
            ->assertOk()
            ->assertDontSee('+ اجتماع');
    }

    /** ⭐ رفع المحضر والمرفقات والتسجيل — والمرفق يدخل مكتبة الوسائط نفسها */
    public function test_uploading_minutes_attachments_and_a_recording(): void
    {
        Storage::fake('public');

        $meeting = $this->makeMeeting($this->makeUser('صاحب الاجتماع'), null, ['status' => 'ended']);

        $this->actingAs($this->owner())
            ->post(route('admin.meetings.minutes', $meeting), [
                'minutes' => "بند أوّل\nبند تاني",
                'recording_url' => 'https://rec.example.test/v/1',
                'attachments' => [UploadedFile::fake()->create('minutes.pdf', 12)],
            ])
            ->assertRedirect();

        $meeting->refresh();

        $this->assertStringContainsString('بند أوّل', (string) $meeting->minutes);
        $this->assertSame('https://rec.example.test/v/1', $meeting->recording_url);

        // المرفق في `media_items` بوسمِ الاجتماع — نفس مخزن مرفقات لوحة التطوّع
        $this->assertSame(1, MediaItem::query()->whereJsonContains('tags->meeting_id', $meeting->id)->count());
    }

    /** رفعٌ فارغ لا يُعَدّ نجاحًا — رسالةٌ تقول ماذا حدث وماذا تفعل (2.17-ب) */
    public function test_an_empty_minutes_upload_is_refused(): void
    {
        $meeting = $this->makeMeeting($this->makeUser('صاحب الاجتماع'), null, ['status' => 'ended']);

        $this->actingAs($this->owner())
            ->post(route('admin.meetings.minutes', $meeting), [])
            ->assertRedirect()
            ->assertSessionHas('problem');

        $this->assertNull($meeting->fresh()->minutes);
    }

    /**
     * ⭐ إدارة الكود/الأسئلة من اللوحة = إدارتها من لوحة التطوّع: نفس
     * `MeetingManager::saveCodeAndQuestions()`، فالصفّان الناتجان متطابقان.
     */
    public function test_managing_the_code_and_questions_matches_the_volunteer_route(): void
    {
        $owner = $this->owner();
        $viaPanel = $this->makeMeeting($this->makeUser('صاحب أوّل'));
        $viaVolunteer = $this->makeMeeting($this->makeUser('صاحب تاني'));

        $payload = [
            'attendance_code' => 'OTP-7788',
            'questions' => [['prompt' => 'مين حضر؟', 'options' => 'أنا,هو', 'correct_answer' => 'أنا']],
        ];

        $this->actingAs($owner)->post(route('admin.meetings.questions', $viaPanel), $payload)->assertRedirect();
        $this->actingAs($owner)->post(route('volunteer.meetings.questions', $viaVolunteer), $payload)->assertRedirect();

        $this->assertSame('OTP-7788', $viaPanel->fresh()->attendance_code);
        $this->assertSame($viaVolunteer->fresh()->attendance_code, $viaPanel->fresh()->attendance_code);

        $shape = fn (Meeting $m) => MeetingQuestion::query()
            ->where('meeting_id', $m->id)
            ->get(['type', 'prompt', 'options', 'correct_answer'])
            ->map->only(['type', 'prompt', 'options', 'correct_answer'])
            ->all();

        $this->assertSame($shape($viaVolunteer), $shape($viaPanel));
    }

    /** ⭐ تثبيت بوست — نفس فعل لوحة التطوّع، والتكرار يفكّ التثبيت */
    public function test_pinning_a_recap_post_from_the_panel(): void
    {
        $owner = $this->owner();
        $meeting = $this->makeMeeting($this->makeUser('صاحب الاجتماع'), null, ['status' => 'ended']);

        $post = MeetingPost::create([
            'meeting_id' => $meeting->id,
            'user_id' => $owner->id,
            'body' => 'خلاصة الاجتماع في ثلاث نقاط',
        ]);

        $this->actingAs($owner)->post(route('admin.meetings.pin', $meeting), ['post_id' => $post->id])->assertRedirect();
        $this->assertTrue((bool) $post->fresh()->is_pinned);

        $this->actingAs($owner)->post(route('admin.meetings.pin', $meeting), ['post_id' => $post->id])->assertRedirect();
        $this->assertFalse((bool) $post->fresh()->is_pinned);

        // بوست اجتماعٍ آخر لا يُثبَّت من صفّ هذا الاجتماع
        $other = $this->makeMeeting($this->makeUser('صاحب تاني'));
        $strayPost = MeetingPost::create(['meeting_id' => $other->id, 'user_id' => $owner->id, 'body' => 'بوست غريب']);

        $this->actingAs($owner)
            ->post(route('admin.meetings.pin', $meeting), ['post_id' => $strayPost->id])
            ->assertNotFound();
    }

    /**
     * ⭐ إلغاء بسبب: السبب إلزاميّ، والملغى **لا تُفتَح له نافذة حضور** —
     * فلا خصمَ غيابٍ على اجتماعٍ لم ينعقد.
     */
    public function test_cancelling_with_a_reason_closes_the_meeting_without_an_attendance_window(): void
    {
        $owner = $this->owner();
        $meeting = $this->makeMeeting($this->makeUser('صاحب الاجتماع'), null, ['scheduled_at' => now()->addDay()]);

        $this->actingAs($owner)
            ->post(route('admin.meetings.cancel', $meeting), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($owner)
            ->post(route('admin.meetings.cancel', $meeting), ['reason' => 'تعارض الموعد مع فعاليّة الكيان.'])
            ->assertRedirect();

        $meeting->refresh();

        $this->assertSame('cancelled', $meeting->status);
        $this->assertSame('تعارض الموعد مع فعاليّة الكيان.', $meeting->cancel_reason);
        $this->assertNotNull($meeting->cancelled_at);
        $this->assertNull($meeting->attendance_closes_at);

        $this->actingAs($owner)
            ->get(route('admin.meetings.index'))
            ->assertOk()
            ->assertSee('ملغيّ');

        /*
         | والحارس في الخدمة لا في الواجهة: «إنهاء» الملغى كان سيفتح نافذة
         | تسجيل، وقفلُها يخصم **غيابًا بلا اعتذار** على جمهور اجتماعٍ لم
         | ينعقد — فالمحاولة تُردّ من البابين معًا.
         */
        $this->actingAs($owner)
            ->post(route('admin.meetings.end', $meeting), ['window_hours' => 6])
            ->assertRedirect()
            ->assertSessionHas('problem');

        $this->actingAs($owner)
            ->post(route('volunteer.meetings.end', $meeting), ['window_hours' => 6])
            ->assertRedirect();

        $meeting->refresh();

        $this->assertSame('cancelled', $meeting->status);
        $this->assertNull($meeting->attendance_closes_at);
        $this->assertDatabaseCount('meeting_attendances', 0);
    }

    /** ⭐ عمودا «المحضر والمرفقات» و«التسجيل» يقولان حالة الصفّ الحقيقيّة */
    public function test_the_minutes_and_recording_columns_show_the_real_state(): void
    {
        $owner = $this->owner();

        $documented = $this->makeMeeting($this->makeUser('صاحب موثَّق'), null, [
            'title' => 'اجتماع موثَّق بتسجيل',
            'status' => 'ended',
            'minutes' => 'محضر مكتوب',
            'external_link' => 'https://meet.example.test/a',
            'recording_url' => 'https://rec.example.test/a',
        ]);

        MediaItem::create([
            'disk' => 'public',
            'path' => 'meetings/'.$documented->id.'/a.pdf',
            'name' => 'a.pdf',
            'mime' => 'application/pdf',
            'size' => 100,
            'tags' => ['meeting_id' => $documented->id],
            'uploaded_by' => $owner->id,
        ]);

        $this->makeMeeting($this->makeUser('صاحب ناقص'), null, [
            'title' => 'اجتماع أونلاين بلا تسجيل',
            'status' => 'ended',
            'external_link' => 'https://meet.example.test/b',
        ]);

        $html = $this->actingAs($owner)->get(route('admin.meetings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('المحضر والمرفقات', $html);
        $this->assertStringContainsString('محضر مرفوع', $html);
        $this->assertStringContainsString('https://rec.example.test/a', $html);
        $this->assertStringContainsString('بلا تسجيل', $html);
        // عدّاد المرفقات حقيقيّ لا رقمٌ مزخرِف
        $this->assertStringContainsString('1 مرفقًا', $html);
    }

    /** ⭐ تبديل (تقويم / جدول) — شبكة الشهر تعرض اجتماعاته وتُخفي الجدول */
    public function test_the_calendar_toggle_renders_the_month_grid(): void
    {
        $owner = $this->owner();
        $this->makeMeeting($this->makeUser('صاحب الاجتماع'), null, [
            'title' => 'اجتماع الشبكة',
            'scheduled_at' => now()->startOfMonth()->addDays(3)->setTime(10, 0),
        ]);

        $table = $this->actingAs($owner)->get(route('admin.meetings.index'))->assertOk()->getContent();
        $this->assertStringContainsString('نافذة التسجيل', $table);

        $calendar = $this->actingAs($owner)
            ->get(route('admin.meetings.index', ['view' => 'calendar']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('اجتماع الشبكة', $calendar);
        $this->assertStringContainsString('الشهر السابق', $calendar);
        // الجدول لا يُبنى في وضع التقويم — تبديلٌ لا تكديس
        $this->assertStringNotContainsString('نافذة التسجيل', $calendar);

        // وشهرٌ لا اجتماع فيه يقول ذلك صراحةً بدل شبكةٍ صامتة
        $empty = $this->actingAs($owner)
            ->get(route('admin.meetings.index', ['view' => 'calendar', 'month' => now()->addYear()->format('Y-m')]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('مفيش اجتماعات في الشهر ده', $empty);
    }

    /** كلّ الأفعال الجديدة محروسة بصلاحيّة — والقراءة وحدها لا تُغيّر شيئًا */
    public function test_read_only_permission_blocks_every_new_action(): void
    {
        $reader = $this->admin(['meetings.list', 'meetings.view']);
        $meeting = $this->makeMeeting($this->makeUser('صاحب الاجتماع'));

        $this->actingAs($reader)->post(route('admin.meetings.questions', $meeting), [])->assertForbidden();
        $this->actingAs($reader)->post(route('admin.meetings.minutes', $meeting), ['minutes' => 'محاولة'])->assertForbidden();
        $this->actingAs($reader)->post(route('admin.meetings.pin', $meeting), ['post_id' => 1])->assertForbidden();
        $this->actingAs($reader)->post(route('admin.meetings.cancel', $meeting), ['reason' => 'محاولة'])->assertForbidden();

        $meeting->refresh();

        $this->assertNull($meeting->minutes);
        $this->assertSame('scheduled', $meeting->status);
    }

    /**
     * ⭐ [2026-09-11] **تثبيتُ تغطيةٍ لا إصلاح** — تحقّقٌ من مسحٍ ختاميّ ادّعى
     * ثغرة هنا ولم تكن موجودة، والحكم عليه لا يصحّ بلا تحوّرٍ فعليّ (mutation).
     *
     * الادّعاء: صاحب `meetings.edit` بنطاقٍ ضيّق (`SELF`) — كما هو منصوصٌ فعليًّا
     * لكوردينيتور/تيم-ليدر في `database/data/permissions.json` — يملك المبدأ
     * فيقدر يُدير **أيّ اجتماعٍ على المنصّة** لأنّ المِدل-وير «لا يعرف عن أيّ
     * سجلٍّ نتكلّم». **وهذا غير صحيح**: تحقّقتُ بالتحوّر (إزالة الحراسة المقترَحة
     * كليًّا) قبل كتابة أيّ إصلاح، ونجح كلّ الطلبات على اجتماع «زميلٍ» رغم ذلك —
     * ثمّ تتبّعت السبب: `EnsurePermission::targetOf()` يمرّر سجلّ المسار كهدفٍ
     * **للنطاق** إلى `AccessEngine::allowsOnRecord()`، و`ScopeResolver::covers()`
     * لنطاق `SELF` يقرأ `owner_id` **من السجلّ نفسه** (`targetUserId()`) قبل أن
     * يقارنه بصاحب الطلب — فالحماية بالملكيّة قائمةٌ فعلًا **في طبقة النطاق**،
     * لا في شرط `is_owner` وحده الذي يتخطّاه `allowsOnRecord()` عمدًا (موثَّقٌ
     * في تعليق الصنف نفسه: النطاق وحده حقّ بوّابة المسار، والشرط حقّ المجال).
     * فلا حاجة لحارسٍ ثانٍ في المتحكّم يكرّر فحصًا يجريه المِدل-وير أصلًا.
     *
     * فبقي الاختبار **تثبيتَ تغطيةٍ** لا إصلاحًا: يثبت أنّ حماية المنصّة
     * القائمة (لا كودٌ جديد) تصمد حتى في الحالة الأدقّ — زميلٌ في **نفس الكيان**
     * (لا كيانٍ مختلف، الذي كان سيَحجب بفارق الكيان وحده ويُخفي الحقيقة). ولو
     * انكسر يومًا `targetUserId()`/`isScopable()` لتوقّف الميزة بلا إنذار،
     * فالحارس هنا يحرس ذلك الافتراض بعينه.
     */
    public function test_platform_scope_protection_already_confines_a_self_scoped_editor_to_their_own_meetings(): void
    {
        $this->seed(PermissionSeeder::class);

        $narrow = $this->makeUser('كوردينيتور نطاقه ضيّق');

        // meetings.edit (SELF · شرط is_owner) لأفعال الإدارة الستّة (وهي أيضًا
        // ما يحسم canManage() داخل show() نفسها) · meetings.view (SELF) لفتح
        // باب show() فقط — مِدل-ويره منفصلٌ عن meetings.manage/edit · و
        // meeting_attendance.manage (SELF · شرط is_owner كذلك) لباب grant() المنفصل
        foreach (['meetings.edit', 'meetings.view', 'meeting_attendance.manage'] as $key) {
            DB::table('permission_user')->insert([
                'permission_id' => Permission::query()->where('key', $key)->value('id'),
                'user_id' => $narrow->id,
                'membership_id' => null,
                'scope' => 'SELF',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        app(AccessEngine::class)->forget($narrow);

        // نفس الكيان للاثنين — انظر التعليق أعلاه: فارق النطاق وحده لا يكفي حارسًا
        $entity = $this->makeEntity();
        $own = $this->makeMeeting($narrow, $entity, ['status' => 'ended']);
        $stranger = $this->makeMeeting($this->makeUser('زميلٌ في الكيان نفسه'), $entity, ['status' => 'ended']);

        // على اجتماعه هو: يمرّ (403 لا يقع)
        $this->actingAs($narrow)->get(route('admin.meetings.show', $own))->assertOk();
        $this->actingAs($narrow)->post(route('admin.meetings.questions', $own), [])->assertRedirect();

        // على اجتماع غيره: يُرَدّ على كلّ فعلٍ — الباب فتحه المبدأ، والسجلّ ردّه النطاق
        $this->actingAs($narrow)->get(route('admin.meetings.show', $stranger))->assertForbidden();
        $this->actingAs($narrow)->post(route('admin.meetings.questions', $stranger), [])->assertForbidden();
        $this->actingAs($narrow)->post(route('admin.meetings.minutes', $stranger), ['minutes' => 'محاولة اختراق'])->assertForbidden();
        $this->actingAs($narrow)->post(route('admin.meetings.pin', $stranger), ['post_id' => 1])->assertForbidden();
        $this->actingAs($narrow)->post(route('admin.meetings.cancel', $stranger), ['reason' => 'محاولة اختراق'])->assertForbidden();
        $this->actingAs($narrow)->post(route('admin.meetings.end', $stranger), ['window_hours' => 6])->assertForbidden();
        $this->actingAs($narrow)->post(route('admin.meetings.grant', $stranger), [
            'user_id' => $narrow->id,
            'reason' => 'محاولة اختراق',
        ])->assertForbidden();

        $stranger->refresh();
        $this->assertNull($stranger->minutes);
        $this->assertSame('ended', $stranger->status);
    }
}
