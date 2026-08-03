<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\RepRule;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\User;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Admin\Volunteer\OffboardingService;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Volunteer\Escalation\HandlerChain;
use App\Services\Volunteer\Retention\CommitteePath;
use App\Services\Volunteer\Retention\SuspensionService;
use App\Services\Volunteer\Tasks\TaskStatus;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **الدرجة الأخيرة من سلّم العتبات — التعليق عند −10** (23-0.2 — البند 4).
 *
 * «**تعليق الحساب بالكامل فورًا** — كلّ العضويّات والدخول للوحة التطوّع …
 * مهامه المفتوحة ⟵ مسار عدم التسليم **بلا خصومات إضافيّة أثناء التعليق** …
 * **⭐ تغطية بوزشنه فورًا:** … تنتقل **مسؤوليّاته الإشرافيّة تلقائيًّا لأبلاينه
 * المباشر** … فلا يبقى فريق بلا مراجِع طوال مدّة التحقيق».
 *
 * وكان المبنيّ **إحالة اللجنة وحدها**: `users.status = active` وكلّ العضويّات
 * نشطة، فيمرّ «المعلَّق» في المنظومة كأنّ شيئًا لم يكن.
 */
class SuspensionTest extends RetentionTestCase
{
    /** ⭐ «تعليق … **كلّ العضويّات والدخول للوحة التطوّع**» */
    public function test_reaching_minus_ten_suspends_every_membership_and_closes_the_dashboard(): void
    {
        [$user, , , $department] = $this->tree();

        $this->assertTrue($user->fresh()->isVolunteer());

        $this->drop($user, -9.8, -0.5);

        $this->assertSame(
            SuspensionService::MEMBERSHIP_STATUS,
            $this->membershipIn($user, $department)->status,
            'بلغ −10 وعضويّته نشطة — والنصّ: «تعليق الحساب بالكامل فورًا — كلّ العضويّات».',
        );

        $this->assertFalse(
            $user->fresh()->isVolunteer(),
            'باب لوحة التطوّع ما زال مفتوحًا — والنصّ يعلّق «الدخول للوحة التطوّع» معه.',
        );

        $this->assertDatabaseCount(SuspensionService::TABLE, 1);
    }

    /**
     * ولا يُقصى بها: «(أ) فرصة … **ويُعاد تفعيل حسابه**» — فالحالة **قابلة
     * للعكس**، والدور يبقى مقفوصًا في عضويّته لا يُسحَب.
     */
    public function test_suspension_is_reversible_not_an_ending(): void
    {
        [$user, , , $department] = $this->tree();

        $this->drop($user, -9.8, -0.5);

        $membership = $this->membershipIn($user, $department);

        $this->assertNull($membership->ended_at, 'العضويّة أُنهيت لا عُلِّقت — والإقصاء قرارٌ بشريّ لا نتيجةُ رقم (13.4-س-د).');
        $this->assertNull($membership->end_reason);
        $this->assertSame('active', $user->fresh()->status, 'حسابه كمتدرّب تعطّل — و13.4-س يُبقيه حتى في الإقصاء نفسه.');
    }

    /** «مهامه المفتوحة ⟵ مسار عدم التسليم … **بلا خصومات إضافيّة أثناء التعليق**» */
    public function test_open_tasks_move_to_the_no_delivery_path_without_new_deductions(): void
    {
        [$user, , , $department] = $this->tree();

        $task = Task::create([
            'entity_id' => $department->id,
            'owner_id' => $user->id,
            'title' => 'مهمّة مفتوحة',
            'status' => TaskStatus::IN_PROGRESS,
            'deadline_at' => now()->addDays(3),
        ]);

        $this->drop($user, -9.8, -0.5);
        $after = round(app(LedgerService::class)->balance($user->fresh(), LedgerService::REP), 2);

        $this->assertSame(TaskStatus::NO_DELIVERY, $task->fresh()->status);
        $this->assertEqualsWithDelta(-10.0, $after, 0.001, 'وقع خصمٌ إضافيّ أثناء التعليق — «عقوبته الآن هي التعليق ذاته».');
        $this->assertSame(0, DB::table('transactions')
            ->where('user_id', $user->id)->where('source', 'task')->count());
    }

    /** «**ومساهماته تُسحَب بلا أثر**» */
    public function test_open_contributions_are_withdrawn(): void
    {
        [$user, , , $department] = $this->tree();
        $owner = $this->makeUser('مالك المهمّة');

        $task = Task::create([
            'entity_id' => $department->id,
            'owner_id' => $owner->id,
            'title' => 'مهمّة مشتركة',
            'status' => TaskStatus::IN_PROGRESS,
            'deadline_at' => now()->addDays(5),
        ]);

        $contribution = TaskContribution::create([
            'task_id' => $task->id,
            'contributor_id' => $user->id,
            'invited_by' => $owner->id,
            'item_title' => 'تصوير',
            'vxp_value' => 5,
            'vxp_source' => 'task_pool',
            'held_amount' => 0,
            'status' => 'accepted',
            'invited_at' => now(),
            'internal_deadline_at' => now()->addDays(3),
        ]);

        $this->drop($user, -9.8, -0.5);

        $this->assertSame('withdrawn', $contribution->fresh()->status);
    }

    /**
     * ⭐⭐ **تغطية البوزشن فورًا** — وهي **الاختبار الحاكم**: «تنتقل مسؤوليّاته
     * الإشرافيّة تلقائيًّا **لأبلاينه المباشر** … فلا يبقى فريق بلا مراجِع».
     */
    public function test_the_position_is_covered_by_the_direct_upline_at_once(): void
    {
        [$user, $lead, $dir, $department, $down] = $this->tree(withDownline: true);

        $chain = app(HandlerChain::class);

        $this->assertSame($user->id, $chain->firstHandlerFor($down, $department->id)?->id);

        $this->drop($user, -9.8, -0.5);

        $this->assertSame(
            $lead->id,
            $chain->firstHandlerFor($down->fresh(), $department->id)?->id,
            'داونلاين المعلَّق بقي بلا مراجِع — أو ذهبت نافذته للمعلَّق نفسه.',
        );

        $row = DB::table(SuspensionService::TABLE)->where('user_id', $user->id)->first();
        $coverage = json_decode((string) $row->coverage, true);

        $this->assertSame(1, (int) $row->positions_covered);
        $this->assertSame($lead->id, (int) $coverage[0]['cover_user_id']);
        $this->assertSame(1, (int) $coverage[0]['downline']);

        // ⛔ ولا تنقطع السلسلة عند الحلقة المعلَّقة فتُقرأ «بلغنا السقف»
        $this->assertFalse(
            $chain->isTop($chain->firstHandlerFor($down->fresh(), $department->id), $department->id),
            'السلسلة انقطعت عند المعلَّق فصار داونلاينه على السقف — فتتسوّى قراراته آليًّا بلا مراجِع.',
        );
        $this->assertSame($dir->id, $chain->nextHandlerAfter($lead, $department->id)?->id);
    }

    /**
     * ⭐ **«لحظة التعليق» تشمل النوافذ المفتوحة** — «تنتقل مسؤوليّاته الإشرافيّة
     * … (المراجعات · **نوافذ محرّك التصعيد** · دفعات الصب-تاسكات) **لحظة
     * التعليق**». فالنافذة القائمة لا تُترَك تنضج على مكتبٍ مقفول حتى تفوت.
     */
    public function test_open_decision_windows_move_at_the_moment_of_suspension(): void
    {
        [$user, $lead, , $department, $down] = $this->tree(withDownline: true);

        $task = Task::create([
            'entity_id' => $department->id,
            'owner_id' => $down->id,
            'title' => 'مهمّة الداونلاين',
            'status' => TaskStatus::IN_PROGRESS,
            'deadline_at' => now()->addDays(3),
        ]);

        $case = app(EscalationEngine::class)->open(CaseCatalog::EXTENSION, $task, $down, ['reason' => 'ظرف']);

        $this->assertSame($user->id, (int) $case->current_handler_id, 'النافذة لم تُفتَح على أبلاين صاحبها أصلًا.');

        $before = $case->window_due_at;
        $level = $case->level;

        $this->drop($user, -9.8, -0.5);

        $case = $case->fresh();

        $this->assertSame(
            $lead->id,
            (int) $case->current_handler_id,
            'النافذة المفتوحة بقيت على مكتبٍ معلَّق حتى تفوت — والنصّ ينقلها «لحظة التعليق».',
        );

        // انتقل صاحب المكتب لا الحالة: لا درجةٌ تُحرَق ولا وقتٌ يُربَح أو يُخسَر
        $this->assertSame($level, $case->level, 'ارتفع مستوى الحالة بلا قرار — والنقل ليس تصعيدًا.');
        $this->assertEquals($before, $case->window_due_at, 'تحرّكت النافذة بالنقل — فرِبح أحدٌ وقتًا أو خسره.');
        $this->assertFalse((bool) $case->slowdown_penalty_applied, 'وقع أثر تباطؤ على مَن لم يفوّت شيئًا.');
    }

    /** ولا تُغطّى إلّا الوظيفة التي لها داونلاين — «**لو كان له داونلاين**» */
    public function test_a_position_without_downline_is_not_marked_covered(): void
    {
        [$user] = $this->tree();

        $this->drop($user, -9.8, -0.5);

        $row = DB::table(SuspensionService::TABLE)->where('user_id', $user->id)->first();

        $this->assertSame(0, (int) $row->positions_covered);
    }

    /** «**التعليق قبل أيّ إنهاء**» — واللجنة تولد معه لا بعده */
    public function test_the_committee_referral_is_born_with_the_suspension(): void
    {
        [$user] = $this->tree();

        $this->drop($user, -9.8, -0.5);

        $row = DB::table(SuspensionService::TABLE)->where('user_id', $user->id)->first();

        $this->assertNotNull($row->referral_id, 'عُلِّق الحساب بلا ملفّ لجنة — والنصّ يولّده لحظة التعليق.');
        $this->assertDatabaseHas(CommitteePath::TABLE, [
            'user_id' => $user->id,
            'trigger' => CommitteePath::TRIGGER_DISPLAYED,
            'status' => 'open',
        ]);
        $this->assertDatabaseCount(CommitteePath::TABLE, 1);
    }

    /**
     * ⭐ «**التصفير الشهري لا يفكّ التعليق** — الدرجة رقمٌ يتصفّر، والتعليق
     * **حالة حساب** لا تُلغى بخوارزميّة تقويم».
     */
    public function test_a_reset_of_the_number_does_not_release_the_state(): void
    {
        [$user, , , $department] = $this->tree();

        $this->drop($user, -9.8, -0.5);

        // التصفير: الرقم يعود صفرًا
        DB::table('wallet_balances')
            ->where('user_id', $user->id)
            ->update(['balance' => 0]);

        app(SuspensionService::class)->sweep();

        $this->assertTrue(app(SuspensionService::class)->isSuspended($user->fresh()));
        $this->assertSame(SuspensionService::MEMBERSHIP_STATUS, $this->membershipIn($user, $department)->status);
    }

    /**
     * ⭐ **قرار الميتينج الأوّل — (أ) فرصة**: «+1 يدويّة … فيرتفع من −10 إلى
     * **−9** ويُعاد تفعيل حسابه وعضويّة قسمه» — **ويعود التفويض تلقائيًّا**.
     */
    public function test_the_committee_chance_lifts_the_number_then_the_state(): void
    {
        [$user, $lead, , $department, $down] = $this->tree(withDownline: true);

        $this->drop($user, -9.8, -0.5);

        $gm = $this->makeUser('مشرف عام');
        $out = app(SuspensionService::class)->grantChance($user->fresh(), $gm, 'قرار اللجنة.');

        $this->assertEqualsWithDelta(-10.0, $out['rep_before'], 0.001);
        $this->assertEqualsWithDelta(-9.0, $out['rep_after'], 0.001, 'الفرصة لم ترفع الرقم — فأوّل مسحة تعيد الدورة على نفس الرقم.');
        $this->assertSame(1, $out['memberships_restored']);

        $this->assertSame('active', $this->membershipIn($user, $department)->status);
        $this->assertTrue($user->fresh()->isVolunteer());
        $this->assertFalse(app(SuspensionService::class)->isSuspended($user->fresh()));

        // «ويعود التفويض تلقائيًّا عند إعادة التفعيل»
        $this->assertSame(
            $user->id,
            app(HandlerChain::class)->firstHandlerFor($down->fresh(), $department->id)?->id,
            'التفويض لم يعد لصاحبه بعد إعادة التفعيل.',
        );
        $this->assertNotSame($lead->id, app(HandlerChain::class)->firstHandlerFor($down->fresh(), $department->id)?->id);
    }

    /** «الاختياريّات تظلّ **منتهية** بحرمانها» — والإفراج لا يحييها */
    public function test_release_does_not_revive_the_optional_memberships_cut_at_minus_nine_and_a_half(): void
    {
        [$user, , , $department] = $this->tree();

        $governorate = $this->makeEntity('محافظة القاهرة', 'governorate');
        $this->makeMembership($user, $governorate)->forceFill(['is_primary' => false])->save();

        $this->drop($user, -9.4, -0.6);

        $this->assertSame('ended', $this->membershipIn($user, $governorate)->status);
        $this->assertSame(SuspensionService::MEMBERSHIP_STATUS, $this->membershipIn($user, $department)->status);

        app(SuspensionService::class)->grantChance($user->fresh(), $this->makeUser('مشرف عام'));

        $this->assertSame('ended', $this->membershipIn($user, $governorate)->status, 'الاختياريّة رجعت مع الإفراج — والنصّ يبقيها منتهية.');
        $this->assertSame('active', $this->membershipIn($user, $department)->status);
    }

    /**
     * ⭐ **«أو يتحوّل شغورًا حقيقيًّا (سلّم الترقية) عند قرار الإقصاء»** (23-0.2-4).
     *
     * والحالة حاكمة لأنّ **الإقصاء لا يقع إلّا على معلَّق**: التعليق يسبق «أيّ
     * إنهاء»، والإقصاء يُرفَض لمن لم يبلغ العتبة (13.4-س-أ). فلو أنهى الأوفبوردنج
     * العضويّاتِ **النشِطة وحدها** لَخرج المُقصى وعضويّاته `suspended` إلى الأبد:
     * دورُه في يده، وحلقتُه قائمة في السلسلة، ولا شغور يُملأ.
     */
    public function test_dismissal_ends_the_suspended_memberships_and_turns_the_cover_into_a_real_vacancy(): void
    {
        [$user, , , $department] = $this->tree(withDownline: true);

        $this->drop($user, -9.8, -0.5);
        $this->assertSame(SuspensionService::MEMBERSHIP_STATUS, $this->membershipIn($user, $department)->status);

        $actor = $this->makeUser('مشرف عام');

        // «الإقصاء **حصرًا** عبر سلّم العتبات» — والعتبة مبلوغة بالتعليق نفسه
        $this->assertTrue(OffboardingService::reachedExclusionThreshold($user->fresh()));

        // بنود التصفية إعدادٌ يزرعه كتالوج التطوّع — لا قائمة محفورة (2.13)
        Setting::query()->updateOrCreate(
            ['key' => 'volunteer.offboarding.clearance_items'],
            [
                'group' => 'volunteer_offboarding',
                'label_ar' => 'بنود التصفية الإلزاميّة',
                'type' => 'json',
                'value' => json_encode(['نقل المهامّ', 'سحب المساهمات'], JSON_UNESCAPED_UNICODE),
                'default_value' => '[]',
            ],
        );
        Cache::forget('settings');

        // التصفية الإلزاميّة كاملةً (مختبَرةٌ على حدة في مسار الأوفبوردنج)
        $checklist = array_fill_keys(array_keys(OffboardingService::clearanceItems()), true);

        $record = OffboardingService::open($user->fresh(), 'exclusion', 'قرار اللجنة والقمّة', $actor, $checklist);

        OffboardingService::complete($record->fresh(), $actor);

        $membership = $this->membershipIn($user, $department);

        $this->assertSame('ended', $membership->status, 'خرج المُقصى وعضويّته «معلَّقة» إلى الأبد.');
        $this->assertSame('exclusion', $membership->end_reason);
        $this->assertFalse(app(SuspensionService::class)->isSuspended($user->fresh()), 'بقي صفّ التعليق مفتوحًا بعد الإقصاء — فالتغطية لم تصر شغورًا.');
        $this->assertSame(0, DB::table('role_user')->where('user_id', $user->id)->count(), 'بقي دور البوزشن في يد المُقصى.');
    }

    /** تعليقٌ واحد لا تعليقان — والصفّ المفتوح هو قفل الدخول */
    public function test_the_suspension_does_not_repeat(): void
    {
        [$user] = $this->tree();

        $this->drop($user, -9.8, -0.5);
        app(SuspensionService::class)->sweep();
        app(SuspensionService::class)->sweep();

        $this->assertDatabaseCount(SuspensionService::TABLE, 1);
    }

    /** ⭐ **لا رقم محروق** (2.13): العتبة من `rep_rule('limit.suspension')` */
    public function test_the_threshold_follows_the_rep_rule(): void
    {
        RepRule::query()->where('key', 'limit.suspension')->update(['value' => -6.0]);
        Cache::forget('rep_rules');

        $this->assertSame(-6.0, app(SuspensionService::class)->threshold());

        [$user, , , $department] = $this->tree();
        $this->drop($user, -5.8, -0.5);

        $this->assertSame(SuspensionService::MEMBERSHIP_STATUS, $this->membershipIn($user, $department)->status);
        $this->assertEqualsWithDelta(
            -6.0,
            (float) DB::table(SuspensionService::TABLE)->where('user_id', $user->id)->value('threshold'),
            0.001,
        );
    }

    /** «أخوكم» خارج كلّ العدّادات والمسارات التشغيليّة (13.4-ص-ج) */
    public function test_the_honorary_member_is_never_suspended(): void
    {
        $department = $this->makeEntity('قسم الإعلام');
        $user = $this->makeUser('أخوكم');

        Membership::create([
            'user_id' => $user->id,
            'entity_id' => $department->id,
            'position_id' => $this->honoraryPosition()->id,
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);

        $this->setRep($user, -10);
        app(SuspensionService::class)->sweep();

        $this->assertDatabaseCount(SuspensionService::TABLE, 0);
    }

    // ------------------------------------------------------------------ أدوات

    /**
     * دايركتور ⟵ تيم ليدر ⟵ **البطل** (⟵ داونلاين اختياريًّا).
     *
     * @return array{0:User,1:User,2:User,3:Entity,4:?User}
     */
    private function tree(bool $withDownline = false): array
    {
        $department = $this->makeEntity('قسم الإعلام');

        $dir = $this->makeUser('دايركتور');
        $lead = $this->makeUser('تيم ليدر');
        $user = $this->makeUser('البطل');

        $dirMembership = $this->makeMembership($dir, $department, position: 'director');
        $leadMembership = $this->makeMembership($lead, $department, $dirMembership, 'team_leader');
        $userMembership = $this->makeMembership($user, $department, $leadMembership);

        $down = null;

        if ($withDownline) {
            $down = $this->makeUser('داونلاين');
            $this->makeMembership($down, $department, $userMembership);
        }

        return [$user, $lead, $dir, $department, $down];
    }

    private function membershipIn(User $user, Entity $entity): Membership
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('entity_id', $entity->id)
            ->firstOrFail();
    }

    /** يضع الرصيد قرب العتبة ثمّ يُنزِله بحركةٍ حقيقيّة — فيقع السلّم بكاسرته */
    private function drop(User $user, float $start, float $movement): void
    {
        $this->setRep($user, $start);

        Integrations::post(
            $user, LedgerService::REP, $movement, 'behavior', 'مخالفة الاختبار', null, null, 'volunteer',
        );
    }

    /**
     * ضبط **الرقم الظاهر** مباشرةً — لأنّ حدّ الخسارة اليوميّ −2 (13.4-ن-و)
     * يقصّ أيّ حركة أكبر، فبلوغ −10 بحركةٍ واحدة مستحيل أصلًا. والمقصود هنا
     * اختبار **الدرجة** لا اختبار الحدّ اليوميّ.
     */
    private function setRep(User $user, float $target): void
    {
        DB::table('wallet_balances')->updateOrInsert(
            [
                'user_id' => $user->id,
                'currency_id' => Currency::query()->where('code', LedgerService::REP)->value('id'),
            ],
            [
                'balance' => $target,
                'lifetime_earned' => 0,
                'lifetime_spent' => abs($target),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
