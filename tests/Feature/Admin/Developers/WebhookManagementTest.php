<?php

namespace Tests\Feature\Admin\Developers;

use App\Http\Controllers\Auth\AuthController;
use App\Jobs\DeliverWebhookJob;
use App\Models\CertificateType;
use App\Models\Permission;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Developers\WebhookDispatcher;
use App\Services\Developers\WebhookEventCatalog;
use App\Services\Developers\WebhookService;
use App\Services\Developers\WebhookSigner;
use App\Services\Security\OtpService;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * الويب-هوكس — 🧩 المطوّرين (12.15-ب · 12.15-ج).
 *
 * القيود الأمنيّة المُثبَتة هنا لا موصوفة فقط: حارس توقيع HMAC وحارس حدّ
 * إعادة المحاولة أُعيد زرع عيبهما فعليًّا أثناء البناء وأُثبِت سقوط
 * اختباريهما، ثمّ أُعيد الإصلاح فورًا — والتفصيل في `_STATUS.md`.
 *
 * ⛔ ولا اختبار هنا يلمس شبكةً حقيقيّة: `Http::fake` + `preventStrayRequests`.
 */
class WebhookManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function userWith(string ...$keys): User
    {
        $user = User::create([
            'name' => 'مطوّر تجريبيّ',
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);

        foreach ($keys as $key) {
            $permission = Permission::query()->where('key', $key)->first();

            if (! $permission) {
                [$resource, $action] = explode('.', $key);
                $permission = Permission::create([
                    'key' => $key,
                    'resource' => $resource,
                    'action' => $action,
                    'group' => 'النظام والتقارير',
                    'label_ar' => $key,
                    'allowed_scopes' => ['ALL'],
                ]);
            }

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget();

        return $user->fresh();
    }

    // =================================================================== التسجيل والعرض مرّة واحدة

    public function test_creating_a_webhook_shows_the_plain_secret_once_then_never_again(): void
    {
        $admin = $this->userWith('webhooks.view', 'webhooks.create');

        $created = $this->actingAs($admin)->post(route('admin.developers.webhooks.store'), [
            'name' => 'موقع تجريبيّ',
            'url' => 'https://hooks.test/incoming',
            'events' => ['user.registered'],
        ]);
        $created->assertRedirect();
        $created->assertSessionHas('plain_webhook_secret');

        $plainSecret = app('session.store')->get('plain_webhook_secret');
        $this->assertNotNull($plainSecret, 'السرّ الصريح لم يُمرَّر بالفلاش بعد التسجيل.');

        $shown = $this->actingAs($admin)->withSession(['plain_webhook_secret' => $plainSecret])
            ->get(route('admin.developers.index', ['tab' => 'webhooks']));
        $shown->assertOk()->assertSee($plainSecret, false);

        $reload = $this->actingAs($admin)->get(route('admin.developers.index', ['tab' => 'webhooks']));
        $reload->assertOk()->assertDontSee($plainSecret, false);
    }

    public function test_the_plain_secret_is_never_stored_as_plaintext_in_the_database(): void
    {
        $admin = $this->userWith('webhooks.view', 'webhooks.create');

        $result = app(WebhookService::class)->create('موقع', 'https://hooks.test/in', ['user.registered'], $admin);

        $stored = Webhook::query()->findOrFail($result['record']->id);

        $this->assertNotSame($result['plain_secret'], $stored->secret_encrypted, 'السرّ الخام مخزَّنٌ كما هو — يجب تشفيره.');
        $this->assertDatabaseMissing('webhooks', ['secret_encrypted' => $result['plain_secret']]);
        $this->assertSame($result['plain_secret'], Crypt::decryptString($stored->secret_encrypted), 'فكّ التشفير لا يعيد السرّ الأصليّ.');
    }

    public function test_rotating_the_secret_invalidates_the_old_one_immediately(): void
    {
        $admin = $this->userWith('webhooks.view', 'webhooks.create', 'webhooks.manage');
        $created = app(WebhookService::class)->create('يُدوَّر', 'https://hooks.test/in', ['user.registered'], $admin);

        $rotated = app(WebhookService::class)->rotateSecret($created['record'], $admin);

        $this->assertNotSame($created['plain_secret'], $rotated['plain_secret']);

        $stored = Webhook::query()->findOrFail($created['record']->id);
        $this->assertSame($rotated['plain_secret'], Crypt::decryptString($stored->secret_encrypted));
        $this->assertNotSame($created['plain_secret'], Crypt::decryptString($stored->secret_encrypted));
    }

    // =================================================================== الإطلاق والتوقيع

    public function test_dispatching_a_subscribed_event_creates_a_delivery_and_sends_a_correctly_signed_post(): void
    {
        Http::fake(['hooks.test/*' => Http::response('ok', 200)]);

        $admin = $this->userWith('webhooks.view', 'webhooks.create');
        $result = app(WebhookService::class)->create('مشترِك', 'https://hooks.test/incoming', ['user.registered'], $admin);
        $secret = $result['plain_secret'];

        WebhookDispatcher::dispatch('user.registered', ['user_id' => 999, 'code' => 'ABC123']);

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $result['record']->id,
            'event_key' => 'user.registered',
            'status' => 'success',
        ]);

        Http::assertSent(function ($request) use ($secret) {
            $expected = 'sha256='.WebhookSigner::sign($secret, $request->body());

            return $request->url() === 'https://hooks.test/incoming'
                && $request->hasHeader('X-Webhook-Signature', $expected)
                && $request->hasHeader('X-Webhook-Event', 'user.registered');
        });

        $webhook = $result['record']->fresh();
        $this->assertSame(200, $webhook->last_response_code);
        $this->assertSame(0, $webhook->consecutive_failures);
        $this->assertNotNull($webhook->last_triggered_at);
    }

    public function test_dispatching_an_event_the_webhook_did_not_subscribe_to_creates_no_delivery(): void
    {
        Http::fake(['hooks.test/*' => Http::response('ok', 200)]);

        $admin = $this->userWith('webhooks.view', 'webhooks.create');
        app(WebhookService::class)->create('غير مشترِك', 'https://hooks.test/in', ['order.paid'], $admin);

        WebhookDispatcher::dispatch('user.registered', ['user_id' => 1]);

        $this->assertDatabaseCount('webhook_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_an_unknown_event_key_outside_the_locked_catalog_is_ignored_silently(): void
    {
        $admin = $this->userWith('webhooks.view', 'webhooks.create');
        app(WebhookService::class)->create('كلّ الأحداث', 'https://hooks.test/in', WebhookEventCatalog::EVENT_KEYS, $admin);

        WebhookDispatcher::dispatch('not.a.real.event', ['x' => 1]);

        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_a_paused_webhook_receives_no_deliveries(): void
    {
        $admin = $this->userWith('webhooks.view', 'webhooks.create', 'webhooks.edit');
        $result = app(WebhookService::class)->create('موقوف', 'https://hooks.test/in', ['user.registered'], $admin);
        app(WebhookService::class)->pause($result['record'], $admin);

        WebhookDispatcher::dispatch('user.registered', ['user_id' => 1]);

        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    // =================================================================== الفشل وإعادة المحاولة

    public function test_a_failed_delivery_increments_attempts_and_schedules_a_retry(): void
    {
        Http::fake(['hooks.test/*' => Http::response('server error', 500)]);

        $admin = $this->userWith('webhooks.view', 'webhooks.create');
        $result = app(WebhookService::class)->create('فاشل', 'https://hooks.test/in', ['user.registered'], $admin);

        WebhookDispatcher::dispatch('user.registered', ['user_id' => 1]);

        // QUEUE_CONNECTION=sync في الاختبارات: كلّ إعادة الجدولة تُنفَّذ فورًا
        // داخل نفس الطلب — فبعد أوّل نداءٍ تكون كلّ الثلاث محاولات قد جرت.
        $delivery = WebhookDelivery::query()->where('webhook_id', $result['record']->id)->firstOrFail();

        $this->assertSame(3, $delivery->attempt_count, 'يجب أن تُستنفَد كلّ المحاولات الثلاث الافتراضيّة.');
        $this->assertSame('exhausted', $delivery->status);
        $this->assertNull($delivery->next_retry_at, 'لا موعد إعادةٍ بعد الاستنفاد.');

        $webhook = $result['record']->fresh();
        $this->assertSame(3, $webhook->consecutive_failures);
        $this->assertSame(500, $webhook->last_response_code);

        Http::assertSentCount(3);
    }

    /**
     * ⭐ **Mutation مُثبَت (12.15-ب):** عند تعطيل شرط `attempt_count < $maxRetries`
     * في `DeliverWebhookJob::handle()` مؤقّتًا (جُعِل الشرط `true` دائمًا) أثناء
     * البناء، سقط هذا الاختبار فعلًا — الحلقة استمرّت تعيد الجدولة إلى ما لا
     * نهاية بلا `exhausted` أبدًا (والاختبار السابق توقّف بلا نتيجة). أُعيد
     * الإصلاح فورًا. والتفصيل الكامل لخطوات الزرع والإسقاط في `_STATUS.md`.
     */
    public function test_the_retry_ceiling_guard_is_real_not_cosmetic(): void
    {
        Http::fake(['hooks.test/*' => Http::response('server error', 500)]);

        $admin = $this->userWith('webhooks.view', 'webhooks.create');
        $result = app(WebhookService::class)->create('حدّ حقيقيّ', 'https://hooks.test/in', ['user.registered'], $admin);

        WebhookDispatcher::dispatch('user.registered', ['user_id' => 1]);

        $delivery = WebhookDelivery::query()->where('webhook_id', $result['record']->id)->firstOrFail();

        // لا يتجاوز عدد المحاولات الحدّ الافتراضيّ (3) مهما فشلت الوجهة
        $this->assertLessThanOrEqual(3, $delivery->attempt_count);
        $this->assertSame('exhausted', $delivery->status, 'الحالة يجب أن تستقرّ على exhausted لا أن تظلّ pending/failed أبديًّا.');
    }

    public function test_a_successful_retry_after_a_failure_marks_the_delivery_success(): void
    {
        // أوّل نداءٍ يفشل، والثاني (إعادة الجدولة الفوريّة تحت sync) ينجح
        Http::fakeSequence('hooks.test/*')
            ->push('fail', 500)
            ->push('ok', 200);

        $admin = $this->userWith('webhooks.view', 'webhooks.create');
        $result = app(WebhookService::class)->create('نجاح بعد فشل', 'https://hooks.test/in', ['user.registered'], $admin);

        WebhookDispatcher::dispatch('user.registered', ['user_id' => 1]);

        $delivery = WebhookDelivery::query()->where('webhook_id', $result['record']->id)->firstOrFail();

        $this->assertSame('success', $delivery->status);
        $this->assertSame(2, $delivery->attempt_count);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_manual_retry_action_redispatches_a_failed_delivery(): void
    {
        Http::fake(['hooks.test/*' => Http::response('ok', 200)]);

        $admin = $this->userWith('webhooks.view', 'webhooks.create', 'webhooks.manage');
        $result = app(WebhookService::class)->create('يُعاد يدويًّا', 'https://hooks.test/in', ['user.registered'], $admin);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $result['record']->id,
            'event_key' => 'user.registered',
            'payload' => ['user_id' => 1],
            'status' => 'exhausted',
            'attempt_count' => 3,
        ]);

        $this->actingAs($admin)->post(route('admin.developers.webhook-deliveries.retry', $delivery))
            ->assertRedirect();

        $this->assertSame('success', $delivery->fresh()->status);
    }

    // =================================================================== الاختبار اليدويّ

    public function test_the_test_action_sends_an_immediate_sample_payload_without_a_real_event(): void
    {
        Http::fake(['hooks.test/*' => Http::response('ok', 200)]);

        $admin = $this->userWith('webhooks.view', 'webhooks.create', 'webhooks.manage');
        $result = app(WebhookService::class)->create('اختبار', 'https://hooks.test/in', [], $admin);

        $this->actingAs($admin)->post(route('admin.developers.webhooks.test', $result['record']))
            ->assertRedirect();

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $result['record']->id,
            'event_key' => 'webhook.test',
            'status' => 'success',
        ]);
        Http::assertSentCount(1);
    }

    // =================================================================== صلاحيّات لوحة الإدارة

    public function test_creating_a_webhook_requires_webhooks_create_permission(): void
    {
        $admin = $this->userWith('webhooks.view');

        $this->actingAs($admin)->post(route('admin.developers.webhooks.store'), [
            'name' => 'محاولة بلا صلاحيّة',
            'url' => 'https://hooks.test/in',
            'events' => ['user.registered'],
        ])->assertForbidden();
    }

    public function test_pausing_a_webhook_requires_webhooks_edit_permission(): void
    {
        $owner = $this->userWith('webhooks.view', 'webhooks.create');
        $result = app(WebhookService::class)->create('للإيقاف', 'https://hooks.test/in', ['user.registered'], $owner);

        $noEdit = $this->userWith('webhooks.view');
        $this->actingAs($noEdit)->post(route('admin.developers.webhooks.pause', $result['record']))
            ->assertForbidden();

        $withEdit = $this->userWith('webhooks.view', 'webhooks.edit');
        $this->actingAs($withEdit)->post(route('admin.developers.webhooks.pause', $result['record']))
            ->assertRedirect();

        $this->assertSame('paused', $result['record']->fresh()->status);
    }

    public function test_rotating_the_secret_requires_webhooks_manage_permission(): void
    {
        $owner = $this->userWith('webhooks.view', 'webhooks.create');
        $result = app(WebhookService::class)->create('للتدوير', 'https://hooks.test/in', ['user.registered'], $owner);

        $noManage = $this->userWith('webhooks.view', 'webhooks.edit');
        $this->actingAs($noManage)->post(route('admin.developers.webhooks.rotate-secret', $result['record']))
            ->assertForbidden();

        $withManage = $this->userWith('webhooks.view', 'webhooks.manage');
        $this->actingAs($withManage)->post(route('admin.developers.webhooks.rotate-secret', $result['record']))
            ->assertRedirect();
    }

    public function test_deleting_a_webhook_requires_webhooks_delete_permission(): void
    {
        $owner = $this->userWith('webhooks.view', 'webhooks.create');
        $result = app(WebhookService::class)->create('للحذف', 'https://hooks.test/in', ['user.registered'], $owner);

        $noDelete = $this->userWith('webhooks.view');
        $this->actingAs($noDelete)->delete(route('admin.developers.webhooks.destroy', $result['record']))
            ->assertForbidden();

        $withDelete = $this->userWith('webhooks.view', 'webhooks.delete');
        $this->actingAs($withDelete)->delete(route('admin.developers.webhooks.destroy', $result['record']))
            ->assertRedirect();

        $this->assertDatabaseMissing('webhooks', ['id' => $result['record']->id]);
    }

    public function test_retrying_a_delivery_requires_webhooks_manage_permission(): void
    {
        $owner = $this->userWith('webhooks.view', 'webhooks.create');
        $result = app(WebhookService::class)->create('محاولة', 'https://hooks.test/in', ['user.registered'], $owner);
        $delivery = WebhookDelivery::create([
            'webhook_id' => $result['record']->id,
            'event_key' => 'user.registered',
            'payload' => [],
            'status' => 'exhausted',
            'attempt_count' => 3,
        ]);

        $noManage = $this->userWith('webhooks.view');
        $this->actingAs($noManage)->post(route('admin.developers.webhook-deliveries.retry', $delivery))
            ->assertForbidden();
    }

    public function test_the_developers_page_requires_the_webhooks_view_permission_for_the_webhooks_tab(): void
    {
        // integrations.view وحدها تفتح باب الشاشة (GATE_KEYS) لكن لا تفتح تاب webhooks تحديدًا
        $user = $this->userWith('integrations.view');

        $this->actingAs($user)
            ->get(route('admin.developers.index', ['tab' => 'webhooks']))
            ->assertForbidden();
    }

    // =================================================================== الربط بأحداثٍ حقيقيّة (12.15-ب)

    /** ⭐ نقطة الدخول الحقيقيّة الأولى: `CertificateIssuer::issue()` — لا نداءٌ اصطناعيّ لـ`WebhookDispatcher` وحده */
    public function test_issuing_a_certificate_through_the_real_issuer_dispatches_the_webhook(): void
    {
        Http::fake(['hooks.test/*' => Http::response('ok', 200)]);

        $admin = $this->userWith('webhooks.view', 'webhooks.create');
        app(WebhookService::class)->create('شهادات', 'https://hooks.test/in', ['certificate.issued'], $admin);

        $type = CertificateType::create([
            'key' => 'wired-'.str()->random(6),
            'name_ar' => 'شهادة اختبار الربط',
            'name_en' => 'Wiring test certificate',
            'is_active' => true,
        ]);
        $holder = User::create([
            'name' => 'حامل شهادة',
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);

        app(CertificateIssuer::class)->issue($holder, $type->key);

        $this->assertDatabaseHas('webhook_deliveries', [
            'event_key' => 'certificate.issued',
            'status' => 'success',
        ]);
    }

    /** ⭐ نقطة الدخول الحقيقيّة الثانية: تسجيل مستخدمٍ فعليّ عبر شاشتَي 2.5-ب/2.5-ج كاملتين بطلبات HTTP حقيقيّة */
    public function test_a_real_user_registration_dispatches_the_webhook(): void
    {
        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);

        Http::fake(['hooks.test/*' => Http::response('ok', 200)]);

        $admin = $this->userWith('webhooks.view', 'webhooks.create');
        app(WebhookService::class)->create('تسجيلات', 'https://hooks.test/in', ['user.registered'], $admin);

        $this->post(route('onboarding.referral.skip'));

        $email = 'wired-register@test.local';
        $this->post(route('register.verify.send'), ['email' => $email]);
        $code = decrypt(DB::table('security_otp_codes')
            ->where('email', $email)
            ->where('purpose', OtpService::PURPOSE_REGISTER)
            ->value('code'), false);
        $this->post(route('register.verify.confirm'), ['email' => $email, 'code' => $code]);

        $this->post('/register', [
            'step' => AuthController::STEP_ACCOUNT,
            'email' => $email,
            'phone_national' => '10'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $this->post('/register', [
            'step' => AuthController::STEP_IDENTITY,
            'title' => 'السيد',
            'name_ar' => 'مستخدم ربط الويب هوك',
            'name_en' => 'Webhook Wiring User',
            'gender' => 'male',
            'address_line' => 'شارع الاختبار',
        ]);

        $this->assertDatabaseHas('webhook_deliveries', [
            'event_key' => 'user.registered',
            'status' => 'success',
        ]);
    }
}
