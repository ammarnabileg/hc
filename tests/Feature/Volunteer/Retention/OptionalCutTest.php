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
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Retention\CommitteePath;
use App\Services\Volunteer\Retention\OptionalCutService;
use App\Services\Volunteer\Tasks\TaskStatus;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * سلّم العتبات **ثلاث درجات لا درجتان** (13.4-س-أ):
 * «−8 إنذار ⟵ **−9.5 بتر الاختياريّ** ⟵ −10 تعليق ولجنة تحقيق».
 *
 * وكانت الدرجة الوسطى **مفتاحًا بلا قارئ**: `limit.optional_cut = -9.5` مزروعة
 * في `rep_rules` ولا يقرأها سطرٌ واحد في الكود، فالسلّم يقفز من الإنذار إلى
 * التعليق — أي أنّ الحماية المنصوصة («تُبتَر الاختياريّات أوّلًا **حمايةً
 * للطرفين**») لم تكن موجودة أصلًا.
 */
class OptionalCutTest extends RetentionTestCase
{
    /** الدرجة الأولى (−8): مؤشّر أحمر — ولا شيء يُبتَر ولا يُعلَّق */
    public function test_first_rung_minus_eight_marks_red_and_cuts_nothing(): void
    {
        [$user, $governorate] = $this->volunteerWithOptionalMembership();

        $this->setRep($user, -8.25);

        $this->assertTrue(app(RepService::class)->isRedIndicator($user), 'المؤشّر الأحمر لم يشتعل عند تخطّي −8.');
        $this->assertSame('danger', app(RepService::class)->state(-8.25));

        app(OptionalCutService::class)->sweep();
        app(CommitteePath::class)->sweep();

        $this->assertDatabaseCount(OptionalCutService::TABLE, 0);
        $this->assertDatabaseCount(CommitteePath::TABLE, 0);
        $this->assertSame('active', $this->membershipIn($user, $governorate)->status);
    }

    /**
     * ⭐ الدرجة الوسطى (−9.5): «**تُنهى فورًا عضويّاته في المحافظات والملفات**
     * … **ويستمرّ حسابه وعضويّة قسمه شغّالَين**».
     */
    public function test_middle_rung_cuts_optional_memberships_and_keeps_the_department(): void
    {
        [$user, $governorate, $caseFile, $department] = $this->volunteerWithOptionalMembership(withCaseFile: true);

        $this->setRep($user, -9.5);

        $result = app(OptionalCutService::class)->sweep();

        $this->assertSame(1, $result['cut'], 'العتبة الوسطى لم تُنفَّذ — الدرجة ما زالت بلا قارئ.');

        // الاختياريّتان مقفولتان بتاريخهما وبسببهما
        foreach ([$governorate, $caseFile] as $entity) {
            $membership = $this->membershipIn($user, $entity);
            $this->assertSame('ended', $membership->status);
            $this->assertNotNull($membership->ended_at);
            $this->assertSame(OptionalCutService::END_REASON, $membership->end_reason);
        }

        // القسم — العمود الفقري وساحة الإصلاح الأخيرة — لم يُمَسّ
        $this->assertSame('active', $this->membershipIn($user, $department)->status);

        // والحساب شغّال، ولا لجنة عند هذه الدرجة
        $this->assertSame('active', $user->fresh()->status);
        $this->assertDatabaseCount(CommitteePath::TABLE, 0);

        $row = DB::table(OptionalCutService::TABLE)->where('user_id', $user->id)->first();
        $this->assertSame(2, (int) $row->memberships_ended);
        $this->assertEqualsWithDelta(-9.5, (float) $row->threshold, 0.001);
    }

    /** «مهامه المفتوحة هناك ⟵ مسار عدم التسليم … **بلا خصم جديد عليه**» */
    public function test_open_tasks_go_to_the_no_delivery_path_without_a_new_deduction(): void
    {
        [$user, $governorate] = $this->volunteerWithOptionalMembership();

        $task = Task::create([
            'entity_id' => $governorate->id,
            'owner_id' => $user->id,
            'title' => 'تغطية فعاليّة المحافظة',
            'status' => TaskStatus::IN_PROGRESS,
            'deadline_at' => now()->addDays(3),
        ]);

        $this->setRep($user, -9.6);
        $before = round(app(LedgerService::class)->balance($user, LedgerService::REP), 2);

        app(OptionalCutService::class)->sweep();

        $this->assertSame(TaskStatus::NO_DELIVERY, $task->fresh()->status);
        $this->assertEqualsWithDelta(
            $before,
            round(app(LedgerService::class)->balance($user, LedgerService::REP), 2),
            0.001,
            'وقع خصمٌ جديد على المبتور — والنصّ: «بلا خصم جديد عليه (خصومه وقعت لحظتها أصلًا)».',
        );

        $this->assertSame(
            0,
            DB::table('transactions')->where('user_id', $user->id)->where('source', 'task')->count(),
            'كُتِبت حركة مهامّ على المبتور رغم أنّ النصّ يمنع الخصم الجديد.',
        );
    }

    /** «مساهماته المفتوحة هناك **تُسحَب بلا أثر على أيّ طرف**» */
    public function test_open_contributions_are_withdrawn(): void
    {
        [$user, $governorate] = $this->volunteerWithOptionalMembership();
        $owner = $this->makeUser('مالك المهمّة');

        $task = Task::create([
            'entity_id' => $governorate->id,
            'owner_id' => $owner->id,
            'title' => 'ملفّ إعلاميّ',
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

        $this->setRep($user, -9.7);
        app(OptionalCutService::class)->sweep();

        $this->assertSame('withdrawn', $contribution->fresh()->status);
    }

    /** «*(لا عضويّات اختياريّة عنده؟ العتبة تمرّ بلا أثر.)*» */
    public function test_a_volunteer_without_optional_memberships_passes_without_effect(): void
    {
        $department = $this->makeEntity('قسم الإعلام');
        $user = $this->makeUser('متطوّع قسمٍ فقط');
        $this->makeMembership($user, $department);

        $this->setRep($user, -9.5);
        app(OptionalCutService::class)->sweep();

        $this->assertSame('active', $this->membershipIn($user, $department)->status);

        $row = DB::table(OptionalCutService::TABLE)->where('user_id', $user->id)->first();
        $this->assertSame(0, (int) $row->memberships_ended, 'العتبة مرّت وأنهت شيئًا وليس عنده اختياريّات.');
    }

    /**
     * «**الحرمان:** لا يفتح عضويّة جديدة في مسارَي المحافظات والملفات **حتى
     * التصفير الشهري التالي**» — والقسم يبقى مفتوحًا بلا قيد.
     */
    public function test_deprivation_blocks_new_optional_memberships_until_the_monthly_reset(): void
    {
        [$user, $governorate] = $this->volunteerWithOptionalMembership();

        $this->setRep($user, -9.5);
        app(OptionalCutService::class)->sweep();

        $service = app(OptionalCutService::class);

        $this->assertTrue($service->isDeprived($user));

        $another = $this->makeEntity('محافظة الإسكندريّة', 'governorate');

        try {
            $service->assertMayJoin($user, $another);
            $this->fail('انضمّ لمحافظة جديدة وهو محرومٌ — الحرمان بلا حارس.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('entity_id', $exception->errors());
        }

        // القسم لا يُمنَع أبدًا — «القسم هو العمود الفقري وساحة الإصلاح الأخيرة»
        $service->assertMayJoin($user, $this->makeEntity('قسم آخر'));
        $this->assertTrue(true);

        // ⭐ ويُرفَع الحرمان بالتصفير الشهريّ لا قبله ولا للأبد
        DB::table(OptionalCutService::TABLE)->where('user_id', $user->id)
            ->update(['deprived_until' => now()->subMinute()]);

        $this->assertSame(1, $service->releaseExpired());
        $this->assertFalse($service->isDeprived($user->fresh()));
        $service->assertMayJoin($user->fresh(), $another);
    }

    /** الدرجة الثالثة (−10): اللجنة — **وبعد** أن يكون البتر قد وقع */
    public function test_third_rung_minus_ten_opens_the_committee_after_the_cut(): void
    {
        [$user, $governorate, , $department] = $this->volunteerWithOptionalMembership();

        $this->setRep($user, -10);

        $this->artisan('volunteers:inactivity')->assertSuccessful();

        $this->assertSame('ended', $this->membershipIn($user, $governorate)->status, 'بلغ −10 وعضويّته الاختياريّة قائمة — والنصّ ينفيه: «الاختياري انتهى قبلها».');
        $this->assertSame('active', $this->membershipIn($user, $department)->status);

        $this->assertDatabaseHas(CommitteePath::TABLE, [
            'user_id' => $user->id,
            'trigger' => CommitteePath::TRIGGER_DISPLAYED,
            'status' => 'open',
        ]);
    }

    /** «تُنهى **فورًا**» — لا في مسحة الغد: الحركة السالبة نفسها تُوقِع الدرجة */
    public function test_the_cut_lands_immediately_on_the_movement_not_on_tomorrows_sweep(): void
    {
        [$user, $governorate] = $this->volunteerWithOptionalMembership();

        $this->setRep($user, -8.8);
        $this->assertSame('active', $this->membershipIn($user, $governorate)->status);

        // معاملة سلوك جسيمة تمرّ من جسر الإدارة — وهي أكثر ما يُنزِل الدرجة
        Integrations::post(
            $user, LedgerService::REP, -1.0, 'behavior', 'مخالفة جسيمة', null, null, 'volunteer',
        );

        $this->assertSame(
            'ended',
            $this->membershipIn($user, $governorate)->status,
            'الحركة نزلت بالرقم تحت −9.5 ولم يقع البتر إلّا بمسحة — والنصّ يقول «فورًا».',
        );
    }

    /**
     * ⭐ **لا رقم محروق** (2.13): العتبة تُقرأ من `rep_rule('limit.optional_cut')`
     * — فإن عدّلها الأدمن تغيّر السلوك **فعلًا** لا شكلًا.
     */
    public function test_the_threshold_follows_the_rep_rule_not_a_burned_number(): void
    {
        [$user, $governorate] = $this->volunteerWithOptionalMembership(withCaseFile: false);

        RepRule::query()->where('key', 'limit.optional_cut')->update(['value' => -7.0]);
        Cache::forget('rep_rules');

        $this->assertSame(-7.0, app(OptionalCutService::class)->threshold());

        // −7.5 لا يبلغ −9.5 المزروعة أصلًا، لكنّه يبلغ العتبة الجديدة
        $this->setRep($user, -7.5);
        app(OptionalCutService::class)->sweep();

        $this->assertSame('ended', $this->membershipIn($user, $governorate)->status);
        $this->assertEqualsWithDelta(
            -7.0,
            (float) DB::table(OptionalCutService::TABLE)->where('user_id', $user->id)->value('threshold'),
            0.001,
        );
    }

    /**
     * ⭐ **مسارات البتر إعدادٌ لا قائمة محفورة** — والقسم لا يدخلها أبدًا،
     * فلو أدخله الأدمن سهوًا فذلك خطؤه المرئيّ لا خطؤنا الصامت.
     */
    public function test_the_optional_tracks_come_from_the_setting(): void
    {
        $this->assertSame(['governorate', 'case_file'], app(OptionalCutService::class)->optionalTrackKeys());

        Setting::query()->where('key', 'volunteer.optional_cut.tracks')
            ->update(['value' => '["case_file"]']);
        Cache::forget('settings');

        $this->assertSame(['case_file'], app(OptionalCutService::class)->optionalTrackKeys());

        [$user, $governorate, $caseFile] = $this->volunteerWithOptionalMembership();

        $this->setRep($user, -9.6);
        app(OptionalCutService::class)->sweep();

        $this->assertSame('ended', $this->membershipIn($user, $caseFile)->status);
        $this->assertSame('active', $this->membershipIn($user, $governorate)->status, 'بُتِرت المحافظة وهي خارج قائمة الإعداد.');
    }

    /**
     * ⭐ **قفل الدخول:** أيّ حركة Rep سالبة تقع **أثناء** تنفيذ البتر لا تفتح
     * بترًا ثانيًا داخل الأوّل — وإلّا صار تعاقبًا لا نهائيًّا يُعلّق العمليّة
     * كلّها بلا خطأ ولا سطر سجلّ. (وهو ما وقع فعلًا قبل أن يُكتَب صفّ البتر
     * **قبل** الأثر لا بعده.)
     */
    public function test_a_negative_movement_during_the_cut_does_not_start_a_second_cut(): void
    {
        [$user, $governorate] = $this->volunteerWithOptionalMembership(withCaseFile: false);

        $this->setRep($user, -9.6);

        $service = app(OptionalCutService::class);

        // خصمٌ يقع من داخل التنفيذ نفسه (يحاكي أيّ مسارٍ فرعيّ يكتب حركة)
        DB::table('wallet_balances')->where('user_id', $user->id)->update(['balance' => -9.9]);

        $result = $service->apply($user);

        Integrations::post($user, LedgerService::REP, -0.25, 'behavior', 'حركة أثناء البتر', null, null, 'volunteer');

        $this->assertDatabaseCount(OptionalCutService::TABLE, 1);
        $this->assertSame(1, $result['memberships']);
        $this->assertSame('ended', $this->membershipIn($user, $governorate)->status);
    }

    /** بترٌ واحد لكلّ دورة حرمان — فلا تُقفَل عضويّة مرّتين ولا يتضاعف الأثر */
    public function test_the_cut_does_not_repeat_within_the_same_deprivation_cycle(): void
    {
        [$user] = $this->volunteerWithOptionalMembership();

        $this->setRep($user, -9.6);

        app(OptionalCutService::class)->sweep();
        app(OptionalCutService::class)->sweep();

        $this->assertDatabaseCount(OptionalCutService::TABLE, 1);
    }

    // ------------------------------------------------------------------ أدوات

    /**
     * متطوّع بثلاث عضويّات: قسم (أساسيّة) + محافظة (اختياريّة) + ملفّ (اختياريّ).
     *
     * @return array{0:User,1:Entity,2:?Entity,3:Entity}
     */
    private function volunteerWithOptionalMembership(bool $withCaseFile = true): array
    {
        $user = $this->makeUser('عبد الرحمن سامي');

        $department = $this->makeEntity('قسم الإعلام');
        $governorate = $this->makeEntity('محافظة القاهرة', 'governorate');
        $caseFile = $withCaseFile ? $this->makeEntity('ملفّ المعرض السنويّ', 'case_file') : null;

        $this->makeMembership($user, $department);
        $this->makeMembership($user, $governorate);

        if ($caseFile) {
            $this->makeMembership($user, $caseFile);
        }

        return [$user, $governorate, $caseFile, $department];
    }

    private function membershipIn(User $user, Entity $entity): Membership
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('entity_id', $entity->id)
            ->firstOrFail();
    }

    /**
     * ضبط **الرقم الظاهر** مباشرةً على المحفظة — لا بحركةٍ من دفتر الأستاذ.
     *
     * ولماذا؟ لأنّ **حدّ الخسارة اليوميّ −2** (13.4-ن-و) يقصّ أيّ حركة أكبر،
     * فبلوغ −9.5 بحركة واحدة مستحيل أصلًا. والمقصود هنا اختبار **الدرجة** لا
     * اختبار الحدّ اليوميّ — وهو مختبَرٌ في `DailyLossCapDoesNotHideCumulativeTest`.
     * (وهو نفس ما يفعله `CumulativeThresholdTest` للسبب نفسه.)
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
