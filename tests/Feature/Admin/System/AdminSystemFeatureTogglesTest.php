<?php

namespace Tests\Feature\Admin\System;

use App\Models\AdAudience;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Features\FeatureCatalog;
use App\Services\Features\FeatureGate;
use App\Services\Features\FeatureRegistry;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * 🖥️ **مفاتيح المزايا** (24.3) — «إطفاء/تشغيل أيّ ميزة في المنصّة بلا نشر كود
 * — **البديل الوحيد للصيانة الجزئيّة الملغاة** (12.7-و)».
 *
 * وكلّ اختبار هنا يقابل **قاعدةً منصوصة**، لا شاشةً تفتح: الإطفاء يُحدِث أثرًا
 * على الخادم · الـOverride يعمل بمستخدمين مختلفين · «مَن يراها أثناء الإيقاف»
 * يُفرَض لا يُخفى · السبب يدخل الـAudit.
 */
class AdminSystemFeatureTogglesTest extends SystemTestCase
{
    private const ADMIN = [
        'feature_toggles.view', 'feature_toggles.list', 'feature_toggles.edit',
        'feature_toggles.manage', 'settings_general.view', 'settings_general.edit',
    ];

    // ==================================================== الشاشة نفسها

    public function test_the_features_tab_is_not_empty_and_shows_the_eight_fields(): void
    {
        $admin = $this->admin(self::ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => 'features']));

        $response->assertOk();

        // الأعمدة/الحقول الثمانية المنصوصة
        foreach ([
            'features.ui.col.feature', 'features.ui.col.group', 'features.ui.col.toggle',
            'features.ui.col.scope', 'features.ui.col.visible', 'features.ui.col.last',
            'features.ui.col.actions',
        ] as $key) {
            $response->assertSee((string) setting($key), false);
        }

        // المفتاح (Key) حاضرٌ تحت اسم الميزة — الحقل الثامن
        $response->assertSee('library.reader');
        // شارة عدد الموقوفة
        $response->assertSee(str_replace(':count', '0', (string) setting('features.ui.paused_badge')), false);
        // بلوك الإعدادات الخمسة
        $response->assertSee(route('admin.features.settings'), false);
    }

    /**
     * 2.15-ج: «الجداول كروت رأسيّة **بلا تمرير أفقيّ**» على الموبايل.
     *
     * ولا يدّعي هذا الاختبار قياسَ التمرير — ذاك لا يُقاس إلّا في متصفّح
     * (انظر `Tests\Feature\Ui\MobileLayoutTest`) — بل يحرس **النمط**: جدولُ
     * الديسكتوب مخفيٌّ تحت `md`، وكروتُ الموبايل مخفيّةٌ فوقه، ولا صندوقَ
     * تمريرٍ أفقيّ في الشاشة أصلًا.
     */
    public function test_the_table_becomes_vertical_cards_on_mobile(): void
    {
        $screen = file_get_contents(resource_path('views/admin/settings/tabs/features.blade.php'));

        $this->assertStringContainsString('hidden md:table', $screen);
        $this->assertStringContainsString('md:hidden', $screen);
        $this->assertStringNotContainsString('overflow-x-auto', $screen);

        $admin = $this->admin(self::ADMIN);
        $html = $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => 'features']))->getContent();

        $this->assertStringContainsString('hidden md:table', $html);
        $this->assertStringContainsString('md:hidden', $html);
    }

    public function test_every_catalog_feature_has_a_row_seeded_in_the_production_path(): void
    {
        $seeded = DB::table('feature_flags')->pluck('key')->all();

        $this->assertSame([], array_diff(FeatureCatalog::keys(), $seeded));
        $this->assertGreaterThanOrEqual(30, count($seeded));

        // والمجموعات التسع المنصوصة كلّها ممثَّلة
        $this->assertSame([], array_diff(
            FeatureCatalog::GROUPS,
            DB::table('feature_flags')->distinct()->pluck('group')->all(),
        ));
    }

    /**
     * ⭐ **قارئٌ واحد للمنصّة كلّها**: `feature()` و`feature_state()` يعطيان
     * نفس إجابة الحارس — فلا تفترق الواجهة عن الخادم.
     */
    public function test_the_single_reader_helper_answers_exactly_like_the_guard(): void
    {
        $admin = $this->admin(self::ADMIN);
        $trainee = $this->trainee();

        $this->assertTrue(feature('library.reader', $trainee));
        // مفتاحٌ لا وجود له ⟵ شغّال: الصمت لا يقفل بابًا
        $this->assertTrue(feature('does.not.exist', $trainee));

        $this->disable($admin, 'library.reader', [
            'behavior' => 'message',
            'visibility' => 'admins',
            'message_ar' => 'رسالة الميزة الموقوفة.',
        ]);

        $this->assertFalse(feature('library.reader', $trainee));
        $this->assertTrue(feature('library.reader', $admin));

        $state = feature_state('library.reader', $trainee);
        $this->assertFalse($state['enabled']);
        $this->assertSame('message', $state['behavior']);
        $this->assertSame('رسالة الميزة الموقوفة.', $state['message']);
        $this->assertTrue(feature_state('library.reader', $admin)['exempt']);
    }

    // ==================================== 1) الإطفاء يُحدِث أثرًا حقيقيًّا

    /** ⭐ سلوك «إخفاء كامل»: المسار يختفي — لا زرٌّ يُخفى وحده */
    public function test_disabling_a_feature_actually_closes_its_route_for_users(): void
    {
        $admin = $this->admin(self::ADMIN);
        $trainee = $this->trainee();

        $this->actingAs($trainee)->get(route('library.index'))->assertOk();

        $this->disable($admin, 'library.reader', ['behavior' => 'hide']);

        $this->actingAs($trainee)->get(route('library.index'))->assertNotFound();
    }

    /** ⭐ سلوك «إظهار رسالة»: **النصّ الذي كتبه الأدمن هو ما يراه المستخدم** */
    public function test_message_behavior_shows_the_admin_written_text_not_a_generic_wall(): void
    {
        $admin = $this->admin(self::ADMIN);
        $trainee = $this->trainee();

        $this->disable($admin, 'library.reader', [
            'behavior' => 'message',
            'message_ar' => 'المكتبة بتتظبط دلوقتي — ارجع بعد ساعة.',
        ]);

        $this->actingAs($trainee)->get(route('library.index'))
            ->assertStatus(403)
            ->assertSee('المكتبة بتتظبط دلوقتي — ارجع بعد ساعة.', false);
    }

    /** ⭐ «مَن يراها أثناء الإيقاف» = **الأدمن فقط** — والفرق يظهر بمستخدمين */
    public function test_admins_still_see_the_feature_while_it_is_off_for_everyone_else(): void
    {
        $admin = $this->admin(array_merge(self::ADMIN, ['my_library.list']));
        $trainee = $this->trainee();

        $this->disable($admin, 'library.reader', ['visibility' => 'admins']);

        // المتدرّب: الباب مقفول على الخادم — لا زرٌّ مخفيّ
        $this->actingAs($trainee)->get(route('library.index'))->assertNotFound();
        // والأدمن: يراها كما ضُبِط بالضبط
        $this->actingAs($admin)->get(route('library.index'))->assertOk();
    }

    // =============================== 2) Override لدور/شريحة — بمستخدمين

    public function test_a_role_override_opens_a_globally_disabled_feature_for_that_role_only(): void
    {
        $admin = $this->admin(self::ADMIN);
        $role = Role::create([
            'key' => 'beta_testers', 'name_ar' => 'المجرِّبون', 'layer' => 'platform',
            'is_system' => false, 'is_deletable' => true, 'requires_membership' => false,
        ]);

        $tester = $this->trainee();
        $tester->assignRole($role);
        $outsider = $this->trainee();

        $this->disable($admin, 'library.reader');

        $this->actingAs($admin)->postJson(route('admin.features.scope'), [
            'key' => 'library.reader', 'scope_type' => 'role', 'scope_id' => $role->id, 'enabled' => true,
        ])->assertOk();

        FeatureGate::forget();

        $this->actingAs($tester)->get(route('library.index'))->assertOk();
        $this->actingAs($outsider)->get(route('library.index'))->assertNotFound();
    }

    public function test_a_segment_override_closes_a_running_feature_for_that_segment_only(): void
    {
        $admin = $this->admin(self::ADMIN);
        $segment = AdAudience::create([
            'name' => 'شريحة اختبار', 'kind' => 'manual', 'segment_type' => 'static', 'is_active' => true,
        ]);

        $inside = $this->trainee();
        $outside = $this->trainee();
        $segment->members()->attach($inside->id);

        $this->actingAs($admin)->postJson(route('admin.features.scope'), [
            'key' => 'library.reader', 'scope_type' => 'segment', 'scope_id' => $segment->id, 'enabled' => false,
        ])->assertOk();

        FeatureGate::forget();

        $this->actingAs($inside)->get(route('library.index'))->assertNotFound();
        $this->actingAs($outside)->get(route('library.index'))->assertOk();
    }

    /** الأخصّ يعلو: شريحةٌ تفتح وميزةٌ موقوفة عامّةً ⟵ الشريحة تفوز */
    public function test_the_narrower_scope_wins_over_the_role_and_the_global_state(): void
    {
        $admin = $this->admin(self::ADMIN);

        $role = Role::create([
            'key' => 'blocked_role', 'name_ar' => 'دور مقفول', 'layer' => 'platform',
            'is_system' => false, 'is_deletable' => true, 'requires_membership' => false,
        ]);
        $segment = AdAudience::create(['name' => 'مستثنون', 'kind' => 'manual', 'segment_type' => 'static', 'is_active' => true]);

        $user = $this->trainee();
        $user->assignRole($role);
        $segment->members()->attach($user->id);

        $this->disable($admin, 'library.reader');

        $this->actingAs($admin)->postJson(route('admin.features.scope'), [
            'key' => 'library.reader', 'scope_type' => 'role', 'scope_id' => $role->id, 'enabled' => false,
        ])->assertOk();

        $this->actingAs($admin)->postJson(route('admin.features.scope'), [
            'key' => 'library.reader', 'scope_type' => 'segment', 'scope_id' => $segment->id, 'enabled' => true,
        ])->assertOk();

        FeatureGate::forget();

        $this->assertTrue(app(FeatureGate::class)->allows('library.reader', $user->fresh()));
    }

    // ============================ 3) الحصر على الخادم لا بإخفاء الزرّ

    /**
     * ⭐ نداءٌ مباشر لنقطة **كتابة** داخل ميزةٍ موقوفة ⟵ يُردّ.
     *
     * لا يكفي أن يختفي زرّ «سجّل حضورك»: الحمولة المزوَّرة تصل للمسار مباشرةً،
     * فإن لم يقف الخادم لها فالميزة **لم تُطفأ** — أُخفيت فقط.
     */
    public function test_a_forged_post_to_a_disabled_feature_is_rejected_not_merely_hidden(): void
    {
        $admin = $this->admin(self::ADMIN);
        $trainee = $this->trainee();

        // النقطة مفتوحةٌ قبل الإطفاء (أيًّا كانت نتيجتها المنطقيّة، ليست 404)
        $this->assertNotSame(404, $this->actingAs($trainee)->post(route('achievements.streak.checkin'))->status());

        $this->disable($admin, 'gamification.streak', ['behavior' => 'hide']);

        $this->actingAs($trainee)->post(route('achievements.streak.checkin'))->assertNotFound();
        $this->actingAs($trainee)->get(route('achievements.streak'))->assertNotFound();

        // وبسلوك «إظهار رسالة» يُردّ كذلك — 403 لا تمرير
        $this->disable($admin, 'gamification.streak', ['behavior' => 'message']);
        $this->actingAs($trainee)->post(route('achievements.streak.checkin'))->assertStatus(403);
    }

    /** ⛔ ولا تُقفَل شاشةُ المفاتيح على نفسها: `admin.*` خارج الحصر */
    public function test_the_toggles_screen_never_locks_itself_out(): void
    {
        $admin = $this->admin(self::ADMIN);

        foreach (FeatureCatalog::keys() as $key) {
            $this->assertNull(FeatureCatalog::featureForRoute('admin.settings.index'), $key);
        }

        $this->disable($admin, 'library.reader');

        $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => 'features']))->assertOk();
    }

    // ================================================ 4) الـAudit والسبب

    public function test_the_reason_is_mandatory_and_lands_in_a_readable_audit_row(): void
    {
        $admin = $this->admin(self::ADMIN);

        // بلا سبب ⟵ لا إيقاف
        $this->actingAs($admin)->postJson(route('admin.features.toggle'), [
            'key' => 'events.public', 'enabled' => false,
        ])->assertStatus(422);

        $this->assertTrue((bool) DB::table('feature_flags')->where('key', 'events.public')->value('enabled'));

        $this->disable($admin, 'events.public', ['reason' => 'الفعاليّات مؤجّلة لحدّ ما التقويم يتظبط.']);

        $flagId = DB::table('feature_flags')->where('key', 'events.public')->value('id');

        $log = AuditLog::query()
            ->where('auditable_type', 'feature_flag')
            ->where('auditable_id', $flagId)
            ->where('action', 'feature_toggles.disable')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('الفعاليّات مؤجّلة لحدّ ما التقويم يتظبط.', $log->new_values['reason']);
        $this->assertTrue($log->old_values['enabled']);
        $this->assertFalse($log->new_values['enabled']);
        $this->assertNotNull($log->created_at);

        // ونفس السجلّ يُقرَأ من نقطة الشاشة
        $this->actingAs($admin)->getJson(route('admin.features.audit', ['key' => 'events.public']))
            ->assertOk()
            ->assertJsonFragment(['reason' => 'الفعاليّات مؤجّلة لحدّ ما التقويم يتظبط.']);
    }

    // ============================================ الصلاحيّة والاسترجاع

    public function test_a_user_without_the_permission_cannot_toggle_anything(): void
    {
        $weak = $this->admin(['settings_general.view'], 'أدمن بلا مفاتيح');

        $this->actingAs($weak)->postJson(route('admin.features.toggle'), [
            'key' => 'library.reader', 'enabled' => false, 'reason' => 'محاولة',
        ])->assertForbidden();

        $this->assertTrue((bool) DB::table('feature_flags')->where('key', 'library.reader')->value('enabled'));
    }

    /**
     * **الحالة الرابعة:** بلا صلاحيّة ⟵ التاب **يُخفى** ولا يُعطَّل (2.15-أ-7).
     *
     * ⚠️ والعدّ لا الوجود: `resources/views/partials/sidebar-admin.blade.php`
     * يحمل رابطًا ثانيًا لنفس التاب محروسًا بـ`settings_general.view` وحدها —
     * وهو **ملفّ ليس لي** (تحت يد إيجنت آخر الآن)، فالرابط يبقى ظاهرًا لمن لا
     * يملك `feature_toggles.*`. لذلك يقيس الاختبار **رابط التاب في الشاشة**
     * (الذي أملكه) عبر عدد المرّات: واحدٌ من السايد بار، والثاني من التاب.
     */
    public function test_without_the_permission_the_tab_is_hidden_not_disabled(): void
    {
        $url = route('admin.settings.index', ['tab' => 'features']);

        $weak = $this->admin(['settings_general.view'], 'أدمن بلا مفاتيح');
        $strong = $this->admin(self::ADMIN);

        $weakPage = $this->actingAs($weak)->get(route('admin.settings.index'))->getContent();
        $strongPage = $this->actingAs($strong)->get(route('admin.settings.index'))->getContent();

        $this->assertSame(
            substr_count($strongPage, $url) - 1,
            substr_count($weakPage, $url),
            'رابط تاب «مفاتيح المزايا» لازم يختفي عمّن لا يملك مفتاحه (2.15-أ-7).',
        );

        // ومَن وصل بالرابط مباشرةً يجد نصّ «بلا صلاحيّة» لا جدولًا
        $this->actingAs($weak)->get($url)
            ->assertOk()
            ->assertSee((string) setting('features.ui.state.denied'), false)
            ->assertDontSee((string) setting('features.ui.col.toggle'), false);
    }

    public function test_reset_brings_the_feature_back_to_global_and_clears_overrides(): void
    {
        $admin = $this->admin(self::ADMIN);
        $role = Role::create([
            'key' => 'temp_role', 'name_ar' => 'دور مؤقّت', 'layer' => 'platform',
            'is_system' => false, 'is_deletable' => true, 'requires_membership' => false,
        ]);

        $this->disable($admin, 'library.reader', ['visibility' => 'admins']);
        $this->actingAs($admin)->postJson(route('admin.features.scope'), [
            'key' => 'library.reader', 'scope_type' => 'role', 'scope_id' => $role->id, 'enabled' => false,
        ])->assertOk();

        $this->actingAs($admin)->postJson(route('admin.features.reset'), ['key' => 'library.reader'])->assertOk();

        FeatureGate::forget();

        $flag = DB::table('feature_flags')->where('key', 'library.reader')->first();
        $this->assertTrue((bool) $flag->enabled);
        $this->assertSame('none', $flag->visibility);
        $this->assertSame(0, DB::table('feature_flag_overrides')->where('feature_flag_id', $flag->id)->count());
        $this->assertTrue($this->actingAs($this->trainee())->get(route('library.index'))->isOk());
    }

    public function test_export_and_import_round_trip_and_unknown_keys_are_skipped(): void
    {
        $admin = $this->admin(self::ADMIN);
        $this->disable($admin, 'library.reader');

        $payload = app(FeatureRegistry::class)->export();
        $payload['features'][] = ['key' => 'ghost.feature', 'enabled' => false];

        $this->actingAs($admin)->postJson(route('admin.features.reset'), ['key' => 'library.reader'])->assertOk();

        $result = app(FeatureRegistry::class)->import($payload, $admin);

        $this->assertSame(1, $result['skipped']);
        $this->assertGreaterThanOrEqual(30, $result['applied']);

        FeatureGate::forget();
        $this->assertFalse((bool) DB::table('feature_flags')->where('key', 'library.reader')->value('enabled'));
    }

    // ==================================================== أدوات مساعدة

    /** @param array<string, mixed> $extra */
    private function disable(User $admin, string $key, array $extra = []): void
    {
        $this->actingAs($admin)->postJson(route('admin.features.toggle'), array_merge([
            'key' => $key,
            'enabled' => false,
            'reason' => 'إيقاف مؤقّت لاختبار الأثر.',
        ], $extra))->assertOk();

        FeatureGate::forget();
    }

    /**
     * متدرّب **من طبقة المستخدم النهائيّ** — لا يفتح لوحة الإدارة.
     *
     * والفارق مقصود: «مَن يراها أثناء الإيقاف = الأدمن فقط» لا يُختبَر إلّا
     * بمستخدمٍ ليس أدمن حقًّا. ومنحُ الصلاحيّة مباشرةً (بلا دور) يجعل الطبقة
     * `null`، و`null !== 'user'` — فيُحسَب صاحبُها فاتحًا للوحة، ويمرّ الاختبار
     * لسببٍ خاطئ. لذلك الدور `trainee` أوّلًا ثمّ الصلاحيّات داخله.
     */
    private function trainee(): User
    {
        $role = Role::query()->where('key', 'trainee')->firstOrFail();

        foreach (['my_library.list', 'my_library.view', 'flip_reader.view', 'streaks.view', 'streaks.create'] as $key) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $role->id,
                'permission_id' => $this->permission($key)->id,
                'scope' => 'ALL',
                'effect' => 'allow',
                'conditions' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user = $this->makeUser('متدرّب');
        $user->assignRole($role);

        app(AccessEngine::class)->forget($user);

        return $user;
    }
}
