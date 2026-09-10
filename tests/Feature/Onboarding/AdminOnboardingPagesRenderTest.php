<?php

namespace Tests\Feature\Onboarding;

use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\AccountApproval;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 12.7-أ — تاب «تحت المراجعة وتمّ القبول» في شاشة محتوى الـOnboarding
 * (`app/Http/Controllers/Admin/OnboardingContentController@index`, تاب `pages`).
 *
 * قبل هذا الإصلاح لم توجد شاشة إدارة تكتب `onboarding.review.html` و
 * `onboarding.accepted.html` رغم أنّ صفحتَي `auth/pending` و`onboarding/accepted`
 * تقرآن منهما مباشرةً — فالإثبات هنا **حقيقيّ لا شكليّ**: الأدمن يحفظ من
 * الكنترولر الإداريّ، والنصّ نفسه يظهر بالحرف على الصفحة العامّة الفعليّة
 * التي يراها مستخدمٌ حقيقيّ في رحلته.
 */
class AdminOnboardingPagesRenderTest extends OnboardingTestCase
{
    /** أدمن يملك `onboarding.view`/`onboarding.edit` تحديدًا — والباقي ممنوع عنه فعلًا */
    private function adminEditor(): User
    {
        $admin = $this->member(['name' => 'أدمن محتوى الـOnboarding']);

        foreach (['onboarding.view', 'onboarding.edit'] as $key) {
            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => Permission::where('key', $key)->firstOrFail()->id,
                'user_id' => $admin->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($admin);

        return $admin->fresh();
    }

    public function test_pages_tab_shows_the_current_html_of_both_pages(): void
    {
        Setting::where('key', 'onboarding.review.html')->update(['value' => '<p>نصّ المراجعة الحاليّ.</p>']);
        Cache::forget('settings');

        $this->actingAs($this->adminEditor())->get(route('admin.ops.onboarding', ['tab' => 'pages']))
            ->assertOk()
            ->assertSee('تحت المراجعة')
            ->assertSee('نصّ المراجعة الحاليّ', false);
    }

    /**
     * ⭐ الفجوة المُصلَحة: الأدمن يحفظ HTML الصفحتين من التاب الجديد، وينعكس
     * بالحرف على الصفحتين العامّتين الحقيقيّتين لمستخدمٍ يمرّ برحلته فعليًّا.
     */
    public function test_admin_edited_html_renders_verbatim_on_the_real_review_and_accepted_pages(): void
    {
        $admin = $this->adminEditor();

        $this->actingAs($admin)->post(route('admin.ops.onboarding.pages'), [
            'review_html' => '<p>ملفّك وصلنا وبنراجعه دلوقتي — كتبها الأدمن من اللوحة.</p>',
            'accepted_html' => '<p>أهلًا بيك رسميًّا — النصّ ده كتبه الأدمن من اللوحة.</p>',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            '<p>ملفّك وصلنا وبنراجعه دلوقتي — كتبها الأدمن من اللوحة.</p>',
            Setting::where('key', 'onboarding.review.html')->value('value'),
        );
        $this->assertSame(
            '<p>أهلًا بيك رسميًّا — النصّ ده كتبه الأدمن من اللوحة.</p>',
            Setting::where('key', 'onboarding.accepted.html')->value('value'),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.onboarding.pages.updated']);

        Cache::forget('settings');

        // مستخدم واقفٌ فعلًا عند خطوة «تحت المراجعة» — لا محاكاة
        $reviewer = $this->newcomer(['instructions_agreed_at' => now()]);

        $this->actingAs($reviewer)->get('/pending')
            ->assertOk()
            ->assertSee('كتبها الأدمن من اللوحة', false);

        // نفس المستخدم بعد اعتماد حسابه — يصل لصفحة القبول بالترتيب المنصوص
        $approver = $this->member(['name' => 'معتمِد الحسابات']);
        app(AccountApproval::class)->approve($approver, $reviewer);

        $this->actingAs($reviewer->fresh())->get(route('onboarding.accepted'))
            ->assertOk()
            ->assertSee('النصّ ده كتبه الأدمن من اللوحة', false);
    }
}
