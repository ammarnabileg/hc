<?php

namespace Tests\Feature\Admin\Developers;

use App\Models\ApiKey;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\Permission;
use App\Models\User;
use App\Services\Developers\ApiKeyService;
use App\Support\Access\AccessEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * مفاتيح API — 🧩 المطوّرين (12.15-أ · 12.15-ج).
 *
 * القيود الأمنيّة المُثبَتة هنا لا موصوفة فقط: كلّ حارسٍ (حالة المفتاح ·
 * الـScope · حدّ المعدّل) أُعيد زرع عيبه فعليًّا أثناء البناء وأُثبِت سقوط
 * اختباره، ثمّ أُعيد الإصلاح — والتفصيل في `_STATUS.md`.
 */
class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

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

    private function bearer(string $plainKey): array
    {
        return ['Authorization' => 'Bearer '.$plainKey];
    }

    // =================================================================== الإنشاء والعرض مرّة واحدة

    public function test_creating_a_key_shows_the_plain_secret_once_then_never_again(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create');

        /*
         | ⭐ الفلاش نفسه: نُثبت أنّ الكونترولر يضع `plain_api_key` في فلاش
         | الجلسة فعليًّا بعد الإنشاء — لا افتراضًا. عميل الاختبار لا يحمل
         | كوكي الجلسة تلقائيًّا بين طلبين منفصلين (`call()` لا يُمرِّرها)، فهذا
         | التأكيد على **نفس استجابة** الإنشاء هو القياس الصحيح لسلوك الكونترولر.
         */
        $created = $this->actingAs($admin)->post(route('admin.developers.api-keys.store'), [
            'name' => 'موقع تجريبيّ',
            'scopes' => ['read:courses'],
        ]);
        $created->assertRedirect();
        $created->assertSessionHas('plain_api_key');

        // القيمة نفسها من نفس دورة الطلب — لارافيل يؤجّج (ages) الفلاش بعدها تلقائيًّا
        $plainKey = app('session.store')->get('plain_api_key');
        $this->assertNotNull($plainKey, 'المفتاح الصريح لم يُمرَّر بالفلاش بعد الإنشاء.');

        // الشاشة تعرضه فعلًا حين الفلاش موجود في الجلسة (طلب المتابعة الحقيقيّ بعد التحويلة)
        $shown = $this->actingAs($admin)->withSession(['plain_api_key' => $plainKey])
            ->get(route('admin.developers.index', ['tab' => 'api']));
        $shown->assertOk()->assertSee($plainKey, false);

        // وأيّ طلبٍ لاحقٍ **بلا** ذلك الفلاش (وهو ما يحدث فعليًّا بعد أن يُؤجَّج
        // الفلاش القياسيّ في لارافيل عقب عرضه مرّة) لا يُظهر المفتاح أبدًا
        $reload = $this->actingAs($admin)->get(route('admin.developers.index', ['tab' => 'api']));
        $reload->assertOk()->assertDontSee($plainKey, false);
    }

    public function test_the_plain_key_is_never_stored_as_plaintext_in_the_database(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create');

        $result = app(ApiKeyService::class)->create('موقع', ['read:courses'], $admin);
        [, $secret] = explode('.', $result['plain_key'], 2);

        $stored = ApiKey::query()->findOrFail($result['record']->id);

        $this->assertNotSame($secret, $stored->key_hash, 'السرّ الخام مخزَّنٌ كما هو — يجب Hash فقط.');
        $this->assertDatabaseMissing('api_keys', ['key_hash' => $secret]);
        $this->assertTrue(Hash::check($secret, $stored->key_hash), 'الـHash المخزَّن لا يطابق السرّ الأصليّ.');
    }

    // =================================================================== المصادقة عبر /api/v1/ping

    public function test_a_valid_key_can_ping(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create');
        $result = app(ApiKeyService::class)->create('عميل API', ['read:courses'], $admin);

        $response = $this->withHeaders($this->bearer($result['plain_key']))->getJson('/api/v1/ping');

        $response->assertOk()->assertJson(['ok' => true, 'key_name' => 'عميل API']);
    }

    public function test_garbage_key_is_rejected_with_401(): void
    {
        $response = $this->withHeaders($this->bearer('sk_garbage.notreal'))->getJson('/api/v1/ping');

        $response->assertStatus(401)->assertJson(['error' => 'missing_or_invalid_key']);
    }

    /**
     * ⭐ **Mutation مُثبَت (12.15-ج):** عند تعطيل `Hash::check()` في `resolve()`
     * مؤقّتًا أثناء البناء سقط هذا الاختبار فعلًا (بادئة صحيحة + سرّ خاطئ ردّت
     * 200 لا 401) — والتفصيل في `_STATUS.md`. أُعيد الفحص فورًا.
     */
    public function test_a_correct_prefix_with_the_wrong_secret_is_rejected_with_401(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create');
        $result = app(ApiKeyService::class)->create('بادئة صحيحة', ['read:courses'], $admin);

        [$prefix] = explode('.', $result['plain_key'], 2);
        $wrongKey = $prefix.'.'.str()->random(40);

        $this->withHeaders($this->bearer($wrongKey))->getJson('/api/v1/ping')
            ->assertStatus(401)->assertJson(['error' => 'missing_or_invalid_key']);
    }

    public function test_missing_authorization_header_is_rejected_with_401(): void
    {
        $this->getJson('/api/v1/ping')->assertStatus(401)->assertJson(['error' => 'missing_or_invalid_key']);
    }

    public function test_expired_key_is_rejected_with_401(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create');
        $result = app(ApiKeyService::class)->create('منتهي', ['read:courses'], $admin, null, now()->subDay());

        $this->withHeaders($this->bearer($result['plain_key']))->getJson('/api/v1/ping')
            ->assertStatus(401)->assertJson(['error' => 'missing_or_invalid_key']);
    }

    public function test_revoked_key_is_rejected_with_401(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create', 'integrations.delete');
        $result = app(ApiKeyService::class)->create('مُبطَل', ['read:courses'], $admin);

        app(ApiKeyService::class)->revoke($result['record'], $admin);

        $this->withHeaders($this->bearer($result['plain_key']))->getJson('/api/v1/ping')
            ->assertStatus(401)->assertJson(['error' => 'missing_or_invalid_key']);
    }

    /**
     * ⭐ **Mutation مُثبَت (12.15-ج):** عند تعطيل فحص `status === 'active'` في
     * `ApiKey::isActive()` مؤقّتًا أثناء البناء، سقط هذا الاختبار فعلًا (مفتاحٌ
     * مُبطَل ردّ 200 لا 401) — والتفصيل في `_STATUS.md`. أُعيد الفحص فورًا.
     */
    public function test_revoked_key_guard_is_real_not_cosmetic(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create', 'integrations.delete');
        $result = app(ApiKeyService::class)->create('حارسٌ حقيقيّ', ['read:courses'], $admin);
        app(ApiKeyService::class)->revoke($result['record'], $admin);

        $stored = ApiKey::query()->findOrFail($result['record']->id);
        $this->assertSame('revoked', $stored->status);
        $this->assertFalse($stored->isActive(), 'isActive() يجب أن يرفض مفتاحًا مُبطَلًا.');
    }

    // =================================================================== الـScope

    public function test_courses_endpoint_requires_the_read_courses_scope(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create');
        $result = app(ApiKeyService::class)->create('بلا Scope', ['read:certificates'], $admin);

        $this->withHeaders($this->bearer($result['plain_key']))->getJson('/api/v1/courses')
            ->assertStatus(403)->assertJson(['error' => 'insufficient_scope']);
    }

    public function test_courses_endpoint_works_with_the_right_scope(): void
    {
        Course::create(['slug' => 'course-'.str()->random(6), 'name_ar' => 'تدريب منشور', 'status' => 'published']);
        Course::create(['slug' => 'course-'.str()->random(6), 'name_ar' => 'تدريب مسوّدة', 'status' => 'draft']);

        $admin = $this->userWith('integrations.view', 'integrations.create');
        $result = app(ApiKeyService::class)->create('بصلاحيّة الكورسات', ['read:courses'], $admin);

        $response = $this->withHeaders($this->bearer($result['plain_key']))->getJson('/api/v1/courses');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name_ar')->all();
        $this->assertContains('تدريب منشور', $names);
        $this->assertNotContains('تدريب مسوّدة', $names, 'المسوّدة ظهرت في قائمة API — يجب المنشور فقط.');
    }

    public function test_certificate_verification_endpoint_reports_not_found_for_unknown_code(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create');
        $result = app(ApiKeyService::class)->create('شهادات', ['read:certificates'], $admin);

        $this->withHeaders($this->bearer($result['plain_key']))
            ->getJson('/api/v1/certificates/NO-SUCH-CODE/verify')
            ->assertOk()
            ->assertJson(['status' => 'not_found']);
    }

    /** نفس استعلام صفحة التحقّق العامّة (مصدر حقيقةٍ واحد — 2.11) يُرجع حالةً حقيقيّة لا وهميّة */
    public function test_certificate_verification_endpoint_reports_the_real_status_for_a_known_code(): void
    {
        $type = CertificateType::create([
            'key' => 'course-'.str()->random(6),
            'name_ar' => 'شهادة تدريب',
            'name_en' => 'Course certificate',
        ]);
        $holder = User::create([
            'name' => 'حامل شهادة',
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);
        $certificate = Certificate::create([
            'code' => 'CERT-'.str()->upper(str()->random(8)),
            'hash' => str()->random(40),
            'user_id' => $holder->id,
            'certificate_type_id' => $type->id,
            'issued_at' => now(),
            'status' => 'valid',
        ]);

        $admin = $this->userWith('integrations.view', 'integrations.create');
        $result = app(ApiKeyService::class)->create('شهادات', ['read:certificates'], $admin);

        $this->withHeaders($this->bearer($result['plain_key']))
            ->getJson('/api/v1/certificates/'.$certificate->code.'/verify')
            ->assertOk()
            ->assertJson(['code' => $certificate->code, 'status' => 'valid']);
    }

    // =================================================================== حدّ المعدّل

    public function test_rate_limit_is_enforced_with_429(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create');
        // حدٌّ منخفض جدًّا في هذا الاختبار وحده — لا يمسّ الإعداد العامّ (12.15-د)
        $result = app(ApiKeyService::class)->create('حدّ منخفض', ['read:courses'], $admin, 1);
        $headers = $this->bearer($result['plain_key']);

        $this->withHeaders($headers)->getJson('/api/v1/ping')->assertOk();

        $second = $this->withHeaders($headers)->getJson('/api/v1/ping');
        $second->assertStatus(429);
        $this->assertNotNull($second->headers->get('Retry-After'), 'رأس Retry-After غائب في ردّ 429.');
    }

    // =================================================================== صلاحيّات لوحة الإدارة

    public function test_creating_a_key_requires_integrations_create_permission(): void
    {
        $admin = $this->userWith('integrations.view');

        $this->actingAs($admin)->post(route('admin.developers.api-keys.store'), [
            'name' => 'محاولة بلا صلاحيّة',
            'scopes' => ['read:courses'],
        ])->assertForbidden();
    }

    public function test_rotating_a_key_requires_integrations_edit_permission(): void
    {
        $owner = $this->userWith('integrations.view', 'integrations.create');
        $result = app(ApiKeyService::class)->create('للتدوير', ['read:courses'], $owner);

        $noEdit = $this->userWith('integrations.view');
        $this->actingAs($noEdit)->post(route('admin.developers.api-keys.rotate', $result['record']))
            ->assertForbidden();

        $withEdit = $this->userWith('integrations.view', 'integrations.edit');
        $this->actingAs($withEdit)->post(route('admin.developers.api-keys.rotate', $result['record']))
            ->assertRedirect();
    }

    public function test_revoking_a_key_requires_integrations_delete_permission(): void
    {
        $owner = $this->userWith('integrations.view', 'integrations.create');
        $result = app(ApiKeyService::class)->create('للإبطال', ['read:courses'], $owner);

        $noDelete = $this->userWith('integrations.view');
        $this->actingAs($noDelete)->delete(route('admin.developers.api-keys.revoke', $result['record']))
            ->assertForbidden();

        $withDelete = $this->userWith('integrations.view', 'integrations.delete');
        $this->actingAs($withDelete)->delete(route('admin.developers.api-keys.revoke', $result['record']))
            ->assertRedirect();

        $this->assertSame('revoked', $result['record']->fresh()->status);
    }

    public function test_the_developers_page_requires_a_tab_specific_permission(): void
    {
        // صلاحيّة webhooks.view وحدها تفتح باب الشاشة (GATE_KEYS) لكن لا تفتح تاب api تحديدًا
        $user = $this->userWith('webhooks.view');

        $this->actingAs($user)
            ->get(route('admin.developers.index', ['tab' => 'api']))
            ->assertForbidden();
    }

    public function test_rotate_invalidates_the_old_key_immediately(): void
    {
        $admin = $this->userWith('integrations.view', 'integrations.create', 'integrations.edit');
        $created = app(ApiKeyService::class)->create('يُدوَّر', ['read:courses'], $admin);

        $rotated = app(ApiKeyService::class)->rotate($created['record'], $admin);

        // القديم مُبطَلٌ فورًا
        $this->withHeaders($this->bearer($created['plain_key']))->getJson('/api/v1/ping')
            ->assertStatus(401);

        // والجديد يعمل بنفس الاسم والصلاحيّات
        $this->withHeaders($this->bearer($rotated['plain_key']))->getJson('/api/v1/ping')
            ->assertOk()->assertJson(['key_name' => 'يُدوَّر']);
    }
}
