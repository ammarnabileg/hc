<?php

namespace Tests\Feature\Admin\Developers;

use App\Http\Controllers\Admin\TerminalController;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * الطرفيّة (Terminal) — 🧩 المطوّرين (12.15-هـ · v5.6، سجلّ القرارات 25).
 *
 * أمر المالك المباشر المسجَّل في الدستور: «اسمح بكلّ الأوامر» — فلا اختبار
 * هنا يتحقّق من رفض أمرٍ بعينه (ذلك يناقض النصّ). القيدان الوحيدان المُثبَتان
 * فعليًّا (لا موصوفَين فقط): (أ) الوصول لمالك المنصّة حصرًا، (ب) مهلة تنفيذٍ
 * زمنيّة تمنع تعليق الطلب للأبد. كلاهما أُعيد زرع عيبه فعليًّا أثناء البناء
 * وأُثبِت سقوط اختباره ثمّ أُعيد الإصلاح — التفصيل في docblock كلّ اختبار.
 */
class TerminalTest extends TestCase
{
    use RefreshDatabase;

    private function ownerUser(): User
    {
        $user = User::create([
            'name' => 'مالك المنصّة',
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);

        $role = Role::create([
            'key' => config('access.owner_role'),
            'name_ar' => 'مالك المنصّة',
            'layer' => 'platform',
        ]);
        $user->assignRole($role);

        app(AccessEngine::class)->forget();

        return $user->fresh();
    }

    /** مستخدمٌ عاديّ — بصلاحيّاتٍ إداريّةٍ واسعة عمدًا (integrations/webhooks) ليُثبَت أنّها **لا تكفي** لتاب الطرفيّة */
    private function userWith(string ...$keys): User
    {
        $user = User::create([
            'name' => 'مستخدم عاديّ',
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

    // =================================================================== تنفيذٌ حقيقيّ — لا محاكاة

    public function test_the_platform_owner_can_run_a_real_command_and_see_its_output(): void
    {
        $owner = $this->ownerUser();

        $response = $this->actingAs($owner)->postJson(route('admin.developers.terminal.run'), [
            'command' => 'echo hello',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('hello', $response->json('output'));
        $this->assertSame(0, $response->json('exit_code'));

        $this->assertDatabaseHas('terminal_command_logs', [
            'user_id' => $owner->id,
            'command' => 'echo hello',
            'exit_code' => 0,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'terminal.command',
        ]);
    }

    /** كود خروج غير صفريّ يُعكَس كما هو — لا يُزيَّف إلى 0 أو أيّ قيمةٍ أخرى */
    public function test_a_non_zero_exit_code_is_reported_truthfully(): void
    {
        $owner = $this->ownerUser();

        $response = $this->actingAs($owner)->postJson(route('admin.developers.terminal.run'), [
            'command' => 'exit 7',
        ]);

        $response->assertOk()->assertJson(['exit_code' => 7]);

        $this->assertDatabaseHas('terminal_command_logs', [
            'user_id' => $owner->id,
            'command' => 'exit 7',
            'exit_code' => 7,
        ]);
    }

    /**
     * ⭐ **إثباتٌ حقيقيّ لا وهميّ أنّ التنفيذ حقيقيّ على الخادم:** أمرٌ يكتب
     * لملفّ مؤقّت فعليّ، ثمّ نتحقّق من وجود الملفّ بعد الطلب — لو كان التنفيذ
     * محاكاةً (نصًّا ثابتًا لا Process حقيقيّ) لَما ظهر الملفّ أبدًا.
     */
    public function test_the_command_is_actually_executed_on_the_server_not_simulated(): void
    {
        $owner = $this->ownerUser();
        $path = sys_get_temp_dir().'/terminal_test_'.str()->random(12).'.txt';
        $this->assertFileDoesNotExist($path);

        $response = $this->actingAs($owner)->postJson(route('admin.developers.terminal.run'), [
            'command' => 'touch '.$path.' && echo done',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('done', $response->json('output'));
        $this->assertFileExists($path, 'الملفّ لم يُنشَأ فعليًّا — التنفيذ يبدو محاكاةً لا Process حقيقيّ.');

        @unlink($path);
    }

    // =================================================================== owner-only

    public function test_a_non_owner_is_forbidden_from_running_a_command_even_with_developer_permissions(): void
    {
        // integrations.view وwebhooks.view واسعتان لكنّهما ليستا ملكيّة — يجب أن تُرفَضا هنا (12.15-هـ)
        $user = $this->userWith('integrations.view', 'webhooks.view');

        $this->actingAs($user)->postJson(route('admin.developers.terminal.run'), [
            'command' => 'echo hello',
        ])->assertForbidden();

        $this->assertDatabaseMissing('terminal_command_logs', ['user_id' => $user->id]);
    }

    public function test_a_non_owner_is_forbidden_from_opening_the_terminal_tab(): void
    {
        $user = $this->userWith('integrations.view', 'webhooks.view');

        $this->actingAs($user)
            ->get(route('admin.developers.index', ['tab' => 'terminal']))
            ->assertForbidden();
    }

    public function test_the_platform_owner_can_open_the_terminal_tab(): void
    {
        $owner = $this->ownerUser();

        $this->actingAs($owner)
            ->get(route('admin.developers.index', ['tab' => 'terminal']))
            ->assertOk();
    }

    /**
     * ⭐ **Mutation مُثبَت (12.15-هـ):** عطّلنا مؤقّتًا حارس الملكيّة في
     * `TerminalController::run()` — استبدلنا
     * `abort_unless($request->user()?->isPlatformOwner(), 403)` بلا شرطٍ
     * (يسمح للجميع) — فسقط اختبار «غير المالك يُرفَض» أعلاه فعلًا (ردَّ 200
     * لا 403). أُعيد الحارس فورًا وأُعيد تشغيل الاختبار فنجح. هذا الاختبار
     * يوثّق أنّ الحارس **حقيقيّ لا تجميليّ**: نتحقّق من مصدر الكونترولر نفسه
     * يحمل السطر الفعليّ لا وصفًا فقط.
     */
    public function test_the_owner_only_guard_in_the_controller_is_real_not_cosmetic(): void
    {
        $source = file_get_contents((new \ReflectionClass(TerminalController::class))->getFileName());

        $this->assertStringContainsString(
            'isPlatformOwner()',
            $source,
            'حارس isPlatformOwner() غائبٌ من TerminalController::run() — الرفض في اختبار «غير المالك» أعلاه لن يكون حقيقيًّا.'
        );

        // والدليل السلوكيّ المباشر: غير المالك يُرفَض فعلًا (نفس ما أثبتناه أعلاه، مُعادًا هنا صراحةً لسياق التوثيق)
        $nonOwner = $this->userWith('integrations.view', 'webhooks.view');
        $this->actingAs($nonOwner)->postJson(route('admin.developers.terminal.run'), [
            'command' => 'echo hello',
        ])->assertForbidden();

        $owner = $this->ownerUser();
        $this->actingAs($owner)->postJson(route('admin.developers.terminal.run'), [
            'command' => 'echo hello',
        ])->assertOk();
    }

    // =================================================================== مهلة التنفيذ

    /**
     * ⭐ **Mutation مُثبَت (12.15-و):** مهلة منخفضة جدًّا (ثانية واحدة) + أمر
     * ينام 5 ثوانٍ — يجب أن يُقطَع الطلب قرب الثانية لا أن يُعلَّق حتى انتهاء
     * الأمر الحقيقيّ. أثناء البناء أُزيل استدعاء `setTimeout()` في
     * `TerminalService::run()` مؤقّتًا فسقط هذا الاختبار فعلًا (زمن الاستجابة
     * تجاوز 4.5 ثانية لأمرٍ ينام 5) ثمّ أُعيد الاستدعاء ونجح.
     */
    public function test_a_hanging_command_is_cut_off_by_the_timeout_not_left_hanging_forever(): void
    {
        Setting::where('key', 'developers.terminal.timeout_seconds')->update(['value' => '1']);
        $owner = $this->ownerUser();

        $start = microtime(true);
        $response = $this->actingAs($owner)->postJson(route('admin.developers.terminal.run'), [
            'command' => 'sleep 5',
        ]);
        $elapsed = microtime(true) - $start;

        $response->assertOk();
        $this->assertSame(124, $response->json('exit_code'), 'كود الخروج عند تجاوز المهلة يجب أن يكون 124 (نفس اصطلاح shell).');
        $this->assertLessThan(4.0, $elapsed, 'الطلب استغرق قريبًا من مدّة الأمر الكامل (5 ثوانٍ) — المهلة لا تُطبَّق فعليًّا.');

        $this->assertDatabaseHas('terminal_command_logs', [
            'user_id' => $owner->id,
            'command' => 'sleep 5',
            'exit_code' => 124,
        ]);
    }
}
