<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\RepRule;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Profile\NotesPanel;
use App\Services\Volunteer\Retention\WarningRung;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ⭐ **الدرجة الأولى من سلّم العتبات — الإنذار عند −8** (23-0.2 — البند 1).
 *
 * النصّ أربعة أشياء لا واحد: «يظهر **المؤشّر الأحمر** · إشعار **لكلّ أبلايناته
 * النشطين** عبر عضويّاته · **التزام التواصل الموثَّق خلال 48 ساعة** يقع على
 * **أبلاين العضويّة التي وقعت فيها المعاملة الكاسرة** · ويُسجَّل التواصل في
 * **الملاحظات الإداريّة** بالبروفايل».
 *
 * وكان المبنيّ **المؤشّر الأحمر وحده** — ربعُ الدرجة: لونٌ على شاشةٍ بلا أن
 * يعلم به أحدٌ ممّن يقدر أن يفعل شيئًا.
 */
class WarningRungTest extends RetentionTestCase
{
    /** المؤشّر الأحمر يظلّ كما هو — والدرجة تضيف فوقه ولا تستبدله */
    public function test_the_red_indicator_still_lights_and_the_row_is_written_with_it(): void
    {
        [$user] = $this->tree();

        $this->drop($user, -7.8, -0.5);

        $this->assertTrue(app(RepService::class)->isRedIndicator($user->fresh()));
        $this->assertDatabaseCount(WarningRung::TABLE, 1);
    }

    /** «إشعار **لكلّ أبلايناته النشطين عبر عضويّاته**» — أبلاين كلّ عضويّة */
    public function test_every_upline_across_his_memberships_is_notified(): void
    {
        [$user, $lead, $dir, , $govLead] = $this->tree(withGovernorate: true);

        $this->drop($user, -7.8, -0.5);

        $row = $this->row($user);
        $notified = collect(json_decode((string) $row->uplines, true))->pluck('user_id')->all();

        $this->assertContains($lead->id, $notified, 'أبلاين القسم لم يُشعَر.');
        $this->assertContains($govLead->id, $notified, 'أبلاين المحافظة لم يُشعَر — والنصّ: «عبر **عضويّاته**».');
        $this->assertNotContains($dir->id, $notified, 'أُشعِرت السلسلة كلّها — والنصّ «أبلايناته» لا سلسلة السلّم إلى القمّة.');
        $this->assertSame(2, (int) $row->uplines_notified);

        $this->assertDatabaseHas('app_notifications', ['user_id' => $lead->id]);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $govLead->id]);
    }

    /**
     * ⭐ «التزام التواصل … يقع على **أبلاين العضويّة التي وقعت فيها المعاملة
     * الكاسرة**» — لا على أبلاينٍ آخر، ولو تعدّدت العضويّات.
     */
    public function test_the_duty_falls_on_the_upline_of_the_breaking_membership(): void
    {
        [$user, $lead, , $governorate, $govLead] = $this->tree(withGovernorate: true);

        // المعاملة الكاسرة في **المحافظة** لا في القسم
        $this->drop($user, -7.8, -0.5, $governorate);

        $row = $this->row($user);

        $this->assertSame(
            $govLead->id,
            (int) $row->responsible_upline_id,
            'الالتزام وقع على أبلاينٍ غير أبلاين العضويّة الكاسرة.',
        );
        $this->assertNotSame($lead->id, (int) $row->responsible_upline_id);
        $this->assertSame($governorate->id, (int) $row->breaking_entity_id);
        $this->assertNotNull($row->breaking_transaction_id, 'المعاملة الكاسرة لم تُختَم — فلا يُعرَف صاحب الواجب أصلًا.');
    }

    /** «خلال **48 ساعة**» — مهلةٌ محسوبةٌ من إعداد لا رقم محروق (2.13) */
    public function test_the_contact_window_comes_from_the_setting(): void
    {
        $this->assertSame(48, app(WarningRung::class)->contactHours());

        Setting::query()->where('key', 'volunteer.rep_warning.contact_hours')->update(['value' => '12']);
        Cache::forget('settings');

        $this->assertSame(12, app(WarningRung::class)->contactHours());

        [$user] = $this->tree();
        $this->drop($user, -7.8, -0.5);

        $due = Carbon::parse($this->row($user)->contact_due_at);

        $this->assertEqualsWithDelta(12, now()->diffInHours($due, false), 1);
    }

    /** «ويُسجَّل التواصل في **الملاحظات الإداريّة** بالبروفايل» */
    public function test_documenting_the_contact_writes_an_admin_note_and_stamps_the_row(): void
    {
        [$user, $lead] = $this->tree();

        $this->drop($user, -7.8, -0.5);
        $row = $this->row($user);

        app(WarningRung::class)->documentContact((int) $row->id, $lead, 'اتكلّمنا واتفقنا على خطّة أسبوع.');

        $after = $this->row($user);

        $this->assertNotNull($after->contacted_at);
        $this->assertSame($lead->id, (int) $after->contacted_by);
        $this->assertNotNull($after->contact_note_id);

        $note = DB::table(NotesPanel::TABLE)->where('id', $after->contact_note_id)->first();

        $this->assertSame($user->id, (int) $note->user_id);
        $this->assertStringContainsString('اتفقنا على خطّة أسبوع', (string) $note->body);
    }

    /** الالتزام على صاحبه — ولا يوثّقه مَن ليس عليه (وإلّا صار الواجب بلا صاحب) */
    public function test_a_stranger_cannot_document_the_contact(): void
    {
        [$user] = $this->tree();
        $stranger = $this->makeUser('غريب');

        $this->drop($user, -7.8, -0.5);

        $this->expectException(ValidationException::class);

        app(WarningRung::class)->documentContact((int) $this->row($user)->id, $stranger, 'كلّمته.');
    }

    /**
     * ⭐ **«حدث أم لا — وهو ما يحاسب الأبلاين أيضًا»** (23-0.2-4-لجنة-4):
     * فوات المهلة بلا توثيق **واقعةٌ مختومة** لا فراغ.
     */
    public function test_a_missed_contact_window_is_stamped_as_a_breach(): void
    {
        [$user, $lead] = $this->tree();

        $this->drop($user, -7.8, -0.5);
        $row = $this->row($user);

        DB::table(WarningRung::TABLE)->where('id', $row->id)->update(['contact_due_at' => now()->subHour()]);

        $result = app(WarningRung::class)->sweepOverdue();

        $this->assertSame(1, $result['breached']);
        $this->assertNotNull($this->row($user)->breached_at);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $lead->id]);

        // ولا يُختَم مرّتين — الواقعة واحدة
        $this->assertSame(0, app(WarningRung::class)->sweepOverdue()['breached']);
    }

    /**
     * ⭐ **الغائب المفوَّض لا يقع عليه الالتزام** (23-6): «كلّ نوافذ القرار
     * الواردة إليه **تُوجَّه للبديل مباشرةً**» — والالتزام نافذةٌ بمهلة.
     */
    public function test_an_absent_upline_hands_the_duty_to_his_delegate(): void
    {
        [$user, $lead, $dir, , , $leadMembership, $dirMembership] = $this->tree(returnMemberships: true);

        MembershipAbsence::create([
            'membership_id' => $leadMembership->id,
            'delegate_membership_id' => $dirMembership->id,
            'from_date' => today()->toDateString(),
            'to_date' => today()->addDays(3)->toDateString(),
            'created_by' => $dir->id,
        ]);

        $this->drop($user, -7.8, -0.5);

        $this->assertSame(
            $dir->id,
            (int) $this->row($user)->responsible_upline_id,
            'وقع الالتزام على غائبٍ له بديل — والنصّ يوجّه نوافذه للبديل مباشرةً.',
        );
        $this->assertNotSame($lead->id, (int) $this->row($user)->responsible_upline_id);
    }

    /** إنذارٌ واحد لكلّ دورة — والدورة تنتهي بالتصفير الشهريّ، فلا ضجيج يوميّ */
    public function test_the_warning_does_not_repeat_within_the_same_cycle(): void
    {
        [$user] = $this->tree();

        $this->drop($user, -7.8, -0.5);
        Integrations::post($user, LedgerService::REP, -0.5, 'behavior', 'خصم تالٍ', null, null, 'volunteer');
        app(WarningRung::class)->sweep();

        $this->assertDatabaseCount(WarningRung::TABLE, 1);
    }

    /** ولا إنذار فوق العتبة أصلًا — الدرجة تقع بالتخطّي لا بأيّ خصم */
    public function test_nothing_happens_above_the_threshold(): void
    {
        [$user] = $this->tree();

        $this->drop($user, -5.0, -0.5);

        $this->assertDatabaseCount(WarningRung::TABLE, 0);
    }

    /** ⭐ **لا رقم محروق** (2.13): العتبة من `rep_rule('limit.red_indicator')` */
    public function test_the_threshold_follows_the_rep_rule(): void
    {
        RepRule::query()->where('key', 'limit.red_indicator')->update(['value' => -3.0]);
        Cache::forget('rep_rules');

        $this->assertSame(-3.0, app(WarningRung::class)->threshold());

        [$user] = $this->tree();
        $this->drop($user, -2.8, -0.5);

        $this->assertDatabaseCount(WarningRung::TABLE, 1);
        $this->assertEqualsWithDelta(-3.0, (float) $this->row($user)->threshold, 0.001);
    }

    // ------------------------------------------------------------------ أدوات

    /**
     * شجرةٌ صغيرة: دايركتور ⟵ تيم ليدر ⟵ البطل، ومعها (اختياريًّا) عضويّة
     * محافظة بأبلاينٍ آخر — فيظهر الفرق بين «كلّ أبلايناته» و«أبلاين الكاسرة».
     *
     * @return array<int,mixed>
     */
    private function tree(bool $withGovernorate = false, bool $returnMemberships = false): array
    {
        $department = $this->makeEntity('قسم الإعلام');

        $dir = $this->makeUser('دايركتور');
        $lead = $this->makeUser('تيم ليدر');
        $user = $this->makeUser('البطل');

        $dirMembership = $this->makeMembership($dir, $department, position: 'director');
        $leadMembership = $this->makeMembership($lead, $department, $dirMembership, 'team_leader');
        $this->makeMembership($user, $department, $leadMembership);

        $governorate = null;
        $govLead = null;

        if ($withGovernorate) {
            $governorate = $this->makeEntity('محافظة القاهرة', 'governorate');
            $govLead = $this->makeUser('تيم ليدر المحافظة');
            $govLeadMembership = $this->makeMembership($govLead, $governorate, position: 'team_leader');
            $this->makeMembership($user, $governorate, $govLeadMembership)
                ->forceFill(['is_primary' => false])->save();
        }

        return $returnMemberships
            ? [$user, $lead, $dir, $governorate, $govLead, $leadMembership, $dirMembership]
            : [$user, $lead, $dir, $governorate, $govLead];
    }

    /** يضع الرصيد قرب العتبة ثمّ يُنزِله بحركةٍ حقيقيّة — فيقع الحدث بكاسرته */
    private function drop(User $user, float $start, float $movement, ?Entity $entity = null): void
    {
        DB::table('wallet_balances')->updateOrInsert(
            [
                'user_id' => $user->id,
                'currency_id' => Currency::query()->where('code', LedgerService::REP)->value('id'),
            ],
            [
                'balance' => $start,
                'lifetime_earned' => 0,
                'lifetime_spent' => abs($start),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        /*
         | المرجع **عضويّةُ الكيان الذي وقعت فيه المخالفة** — ومنه يختم الجسرُ
         | `entity_id` على الصفّ قبل أن يمرّ على السلّم. وهذا هو المسار الحقيقيّ:
         | معاملة السلوك (`BehaviorTransaction`) تحمل `membership_id`، فيُشتقّ
         | كيانُها منه بلا أن يُمرَّر يدويًّا.
         */
        $reference = $entity
            ? Membership::query()->where('user_id', $user->id)->where('entity_id', $entity->id)->first()
            : null;

        Integrations::post(
            $user, LedgerService::REP, $movement, 'behavior', 'مخالفة الاختبار', null, $reference, 'volunteer',
        );
    }

    private function row(User $user): object
    {
        return DB::table(WarningRung::TABLE)->where('user_id', $user->id)->latest('id')->first();
    }
}
