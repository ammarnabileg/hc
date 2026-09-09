<?php

namespace Tests\Feature\Admin\Content;

use App\Mail\AnnouncementMail;
use App\Models\EmailTemplate;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Support\Access\AccessEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * قوالب البريد (email_templates.* — 24.3 سطر 5065-5072): كانت صلاحيّةً بثمانية
 * أفعال بلا أيّ تنفيذ — لا Model ولا مايجريشن ولا كنترولر ولا شاشة. وعمودا
 * «نصّ القالب»/«مفعّل» في مصفوفة الإشعارات وزرّا الهيدر كانا بلا وظيفةٍ خلفهما.
 */
class EmailTemplatesTest extends AdminContentTestCase
{
    private function grant(User $user, string $key): void
    {
        $permission = Permission::firstOrCreate(['key' => $key], [
            'resource' => explode('.', $key)[0], 'action' => explode('.', $key)[1],
            'group' => 'اختبار', 'label_ar' => $key, 'allowed_scopes' => ['ALL'],
        ]);

        DB::table('permission_user')->insertOrIgnore([
            'permission_id' => $permission->id, 'user_id' => $user->id,
            'membership_id' => null, 'scope' => 'ALL', 'effect' => 'allow',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(AccessEngine::class)->forget($user);
    }

    // ------------------------------------------------------------ الشاشة والصلاحيّات

    public function test_the_screen_is_hidden_and_refused_without_the_permission(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('admin.guidance.email-templates.index'))->assertForbidden();

        // والزرّ في هيدر الإشعارات مخفيٌّ لا معطَّل (2.15-أ-7)
        $this->grant($user, 'announcements.view');
        $this->actingAs($user)->get(route('admin.guidance.notifications'))
            ->assertOk()
            ->assertDontSee(route('admin.guidance.email-templates.index'), false);
    }

    public function test_a_permitted_admin_sees_the_screen_and_the_header_button(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.guidance.email-templates.index'))->assertOk();

        $this->actingAs($admin)->get(route('admin.guidance.notifications'))
            ->assertOk()
            ->assertSee(route('admin.guidance.email-templates.index'), false);
    }

    // ------------------------------------------------------------ CRUD كامل

    public function test_create_edit_archive_and_delete_a_template(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.guidance.email-templates.store'), [
            'name' => 'قالب اختبار',
            'category' => 'account',
            'subject' => 'أهلًا بيك',
            'body' => 'مرحبًا [اسم]، اتقبل حسابك.',
            'is_enabled' => '1',
        ])->assertRedirect();

        $template = EmailTemplate::query()->where('name', 'قالب اختبار')->firstOrFail();
        $this->assertSame('account', $template->category);
        $this->assertTrue($template->is_enabled);
        $this->assertSame('active', $template->status);

        $this->actingAs($admin)->put(route('admin.guidance.email-templates.update', $template), [
            'name' => 'قالب اختبار محدَّث',
            'category' => 'account',
            'body' => 'نصٌّ جديد',
        ])->assertRedirect();

        $this->assertSame('قالب اختبار محدَّث', $template->fresh()->name);
        // الفورم بلا is_enabled = تشيك بوكس غير معلَّم — يُطفأ (سلوك HTML الطبيعيّ)
        $this->assertFalse($template->fresh()->is_enabled);

        $this->actingAs($admin)->post(route('admin.guidance.email-templates.archive', $template))->assertRedirect();
        $this->assertSame('archived', $template->fresh()->status);

        // نفس الزرّ يعيده — أرشفةٌ قابلة للرجوع لا حذفٌ نهائيّ
        $this->actingAs($admin)->post(route('admin.guidance.email-templates.archive', $template))->assertRedirect();
        $this->assertSame('active', $template->fresh()->status);

        $this->actingAs($admin)->delete(route('admin.guidance.email-templates.destroy', $template))->assertRedirect();
        $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
    }

    /** قالبٌ واحدٌ فقط لكلّ نوع — الثاني على نفس النوع مرفوض. */
    public function test_only_one_template_per_category_is_allowed(): void
    {
        $admin = $this->admin();
        EmailTemplate::create(['category' => 'account', 'name' => 'الأوّل', 'body' => 'نصّ']);

        $this->actingAs($admin)->from(route('admin.guidance.email-templates.index'))->post(route('admin.guidance.email-templates.store'), [
            'name' => 'الثاني',
            'category' => 'account',
            'body' => 'نصّ آخر',
        ])->assertRedirect(route('admin.guidance.email-templates.index'));

        $this->assertSame(1, EmailTemplate::query()->where('category', 'account')->count());
    }

    /** قوالبٌ عامّة (category = null) بلا حدّ — ليست مربوطةً بنوعٍ بعد. */
    public function test_general_templates_with_no_category_have_no_uniqueness_limit(): void
    {
        $admin = $this->admin();

        foreach (['عامّ 1', 'عامّ 2'] as $name) {
            $this->actingAs($admin)->post(route('admin.guidance.email-templates.store'), [
                'name' => $name,
                'body' => 'نصّ',
            ])->assertRedirect();
        }

        $this->assertSame(2, EmailTemplate::query()->whereNull('category')->count());
    }

    public function test_export_and_import_round_trip(): void
    {
        $admin = $this->admin();
        EmailTemplate::create([
            'category' => 'exam', 'name' => 'نتيجة الامتحان', 'subject' => 'نتيجتك',
            'body' => 'مبروك [اسم]', 'is_enabled' => true,
        ]);

        $csv = $this->actingAs($admin)->get(route('admin.guidance.email-templates.export'))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('نتيجة الامتحان', $csv);

        $file = UploadedFile::fake()->createWithContent('templates.csv', $csv);

        EmailTemplate::query()->delete();

        $this->actingAs($admin)->post(route('admin.guidance.email-templates.import'), ['file' => $file])->assertRedirect();

        $this->assertDatabaseHas('email_templates', ['category' => 'exam', 'name' => 'نتيجة الامتحان', 'is_enabled' => true]);
    }

    // ------------------------------------------------------------ عمودا المصفوفة (نصّ القالب/مفعّل)

    public function test_the_matrix_row_template_button_creates_and_updates_the_category_template(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.guidance.notifications.matrix.template', 'certificate'), [
            'subject' => 'صدرت شهادتك',
            'body' => 'مبروك يا [اسم]، صدرت شهادتك.',
            'is_enabled' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('email_templates', [
            'category' => 'certificate', 'is_enabled' => true, 'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('admin.guidance.notifications'))
            ->assertOk()
            ->assertSee(setting('admin.guidance.notifications.mfaal', 'مفعّل'));
    }

    /** نوعٌ غير موجود في `notificationTypes()` — 404 لا إنشاء صفٍّ عشوائيّ. */
    public function test_the_matrix_template_endpoint_rejects_an_unknown_category(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.guidance.notifications.matrix.template', 'not-a-real-category'), ['body' => 'نصّ'])
            ->assertNotFound();
    }

    public function test_the_matrix_template_button_requires_the_edit_permission(): void
    {
        $user = $this->makeUser();
        $this->grant($user, 'announcements.view');

        $this->actingAs($user)
            ->post(route('admin.guidance.notifications.matrix.template', 'account'), ['body' => 'نصّ'])
            ->assertForbidden();
    }

    // ------------------------------------------------------------ Notifier يستهلك القالب فعليًّا

    /** ⭐ قالبٌ مفعّل يستبدل عنوان/جسم المستدعي — بالجرس وبالبريد معًا. */
    public function test_notifier_uses_the_enabled_template_instead_of_the_caller_text(): void
    {
        Mail::fake();
        $user = $this->makeUser(['email' => 'reader@test.local', 'name' => 'سارة']);

        EmailTemplate::create([
            'category' => 'account', 'name' => 'قبول الحساب', 'subject' => 'أهلًا [اسم]',
            'body' => 'اتقبل حسابك يا [اسم] 🎉', 'is_enabled' => true,
        ]);

        $notification = Notifier::send($user, 'account', 'عنوانٌ افتراضيّ من المستدعي', 'جسمٌ افتراضيّ');

        $this->assertSame('اتقبل حسابك يا سارة 🎉', $notification->body);
        // القالب لا يملك حقل عنوانٍ منفصلًا في السجلّ — الجرس عنوانه `title`، وهنا استبدلناه بالـsubject المخصَّص
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id, 'category' => 'account', 'body' => 'اتقبل حسابك يا سارة 🎉',
        ]);
    }

    /** وبلا تفعيل: يمرّ نصّ المستدعي كما هو — بلا أثر ولا كسر (توافقٌ خلفيّ). */
    public function test_notifier_falls_back_to_the_caller_text_when_no_template_is_enabled(): void
    {
        $user = $this->makeUser();

        EmailTemplate::create([
            'category' => 'account', 'name' => 'قبول الحساب', 'body' => 'نصّ القالب', 'is_enabled' => false,
        ]);

        $notification = Notifier::send($user, 'account', 'عنوان المستدعي', 'جسم المستدعي');

        $this->assertSame('جسم المستدعي', $notification->body);
    }

    /** والبريد الفعليّ (عمود المصفوفة) يحمل نفس نصّ القالب المستبدَل لا نصّ المستدعي. */
    public function test_the_real_email_sent_carries_the_template_text(): void
    {
        Mail::fake();
        $user = $this->makeUser(['email' => 'reader2@test.local']);

        Setting::updateOrCreate(
            ['key' => 'notifications.matrix.certificate.email'],
            ['group' => 'notifications', 'label_ar' => 'x', 'type' => 'bool', 'value' => '1'],
        );
        Cache::forget('settings');

        EmailTemplate::create([
            'category' => 'certificate', 'name' => 'شهادة', 'subject' => 'صدرت شهادتك فعلًا',
            'body' => 'مبروك، الشهادة جاهزة.', 'is_enabled' => true,
        ]);

        Notifier::send($user, 'certificate', 'عنوانٌ لن يُستعمَل', 'جسمٌ لن يُستعمَل');

        Mail::assertSent(AnnouncementMail::class, fn (AnnouncementMail $mail) => $mail->hasTo('reader2@test.local')
            && $mail->subjectLine === 'صدرت شهادتك فعلًا');
    }
}
