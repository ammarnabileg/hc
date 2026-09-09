<?php

namespace Tests\Feature\Admin\Content;

use App\Mail\AnnouncementMail;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * مصفوفة النوع × القناة (24.3) — كانت شاشة عرضٍ بلا حفظٍ إطلاقًا: الخليّة
 * تتغيّر في المتصفّح ولا تصل أيّ مسار، فيرجع الوضع الأصليّ بأوّل تحديث.
 * وحتى لو حُفظت، لا شيء في `Notifier::send()` كان يقرؤها — فالجرس يُكتَب
 * دائمًا مهما كانت حالة الخليّة، والبريد لا يُرسَل مطلقًا لأنواعها.
 */
class NotificationMatrixTest extends AdminContentTestCase
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

    // ------------------------------------------------------------ الحفظ الحقيقيّ

    public function test_saving_the_matrix_actually_persists_it(): void
    {
        $owner = $this->admin();

        $this->assertFalse((bool) setting('notifications.matrix.account.email', false));

        $this->actingAs($owner)->post(route('admin.guidance.notifications.matrix.save'), [
            'matrix' => [
                'account' => ['bell' => '1', 'email' => '1'],
                'certificate' => ['bell' => '1'],
            ],
        ])->assertRedirect();

        Cache::forget('settings');

        $this->assertTrue((bool) setting('notifications.matrix.account.email', false));
        $this->assertTrue((bool) setting('notifications.matrix.account.bell', false));
        // خليّة لم تُرسَل في الفورم (تشيك بوكس غير معلَّم) = false — سلوك HTML الطبيعيّ
        $this->assertFalse((bool) setting('notifications.matrix.certificate.email', true));
    }

    public function test_saving_the_matrix_is_audited(): void
    {
        $owner = $this->admin();

        $this->actingAs($owner)->post(route('admin.guidance.notifications.matrix.save'), [
            'matrix' => ['wallet' => ['bell' => '1', 'email' => '1']],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'notifications.matrix.edit',
            'user_id' => $owner->id,
        ]);
    }

    /** `notifications.manage` صلاحيّةٌ عاديّة (12.2.2: «دائمًا») لا owner-only */
    public function test_a_user_without_the_permission_cannot_save_the_matrix(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('admin.guidance.notifications.matrix.save'), ['matrix' => ['account' => ['bell' => '1']]])
            ->assertForbidden();
    }

    // ------------------------------------------------------------ Notifier يقرأ المصفوفة فعليًّا

    /** ⭐ الجرس هو السجلّ الدائم نفسه — إيقافه لنوعٍ محكوم يعني لا سجلّ إطلاقًا */
    public function test_notifier_writes_no_row_when_bell_is_off_for_a_governed_type(): void
    {
        $user = $this->makeUser();
        Setting::updateOrCreate(['key' => 'notifications.matrix.account.bell'], ['group' => 'notifications', 'label_ar' => 'x', 'type' => 'bool', 'value' => '0']);
        Cache::forget('settings');

        Notifier::send($user, 'account', 'رسالة اختبار');

        $this->assertDatabaseMissing('app_notifications', ['user_id' => $user->id, 'category' => 'account']);
    }

    /** وأنواعٌ خارج المصفوفة (ليست من الستّة المحكومة) تصل دائمًا كما كانت */
    public function test_notifier_still_writes_ungoverned_categories_regardless_of_matrix(): void
    {
        $user = $this->makeUser();

        Notifier::send($user, 'task', 'رسالة اختبار', null, null, 'volunteer');

        $this->assertDatabaseHas('app_notifications', ['user_id' => $user->id, 'category' => 'task']);
    }

    /** ⭐ عمود «بريد» يعمل فعليًّا — لا يُعرَض وحده بلا أثر */
    public function test_notifier_sends_a_real_email_when_the_matrix_email_column_is_on(): void
    {
        Mail::fake();
        $user = $this->makeUser(['email' => 'reader@test.local']);
        Setting::updateOrCreate(['key' => 'notifications.matrix.certificate.email'], ['group' => 'notifications', 'label_ar' => 'x', 'type' => 'bool', 'value' => '1']);
        Cache::forget('settings');

        Notifier::send($user, 'certificate', 'صدرت شهادتك', 'تفاصيل الشهادة', '/certs/1');

        Mail::assertSent(AnnouncementMail::class, fn (AnnouncementMail $mail) => $mail->hasTo('reader@test.local') && $mail->subjectLine === 'صدرت شهادتك');
    }

    /** وبلا تفعيل العمود: نفس الحدث بلا بريد — كالوضع الحاليّ تمامًا */
    public function test_notifier_sends_no_email_when_the_matrix_email_column_is_off(): void
    {
        Mail::fake();
        $user = $this->makeUser(['email' => 'reader2@test.local']);

        Notifier::send($user, 'certificate', 'صدرت شهادتك');

        Mail::assertNothingSent();
        $this->assertDatabaseHas('app_notifications', ['user_id' => $user->id, 'category' => 'certificate']);
    }
}
