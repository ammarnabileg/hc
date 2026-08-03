<?php

namespace App\Services\Volunteer\Retention;

use App\Models\Membership;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Notifications\Notifier;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Volunteer\Escalation\FlowLedger;
use App\Services\Volunteer\Tasks\NoDeliverySweeper;
use App\Services\Volunteer\Tasks\TaskStatus;
use App\Services\Wallet\LedgerService;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ **الدرجة الأخيرة من سلّم العتبات: التعليق عند −10** (23-0.2 — البند 4).
 *
 * ================== النصّ الحاكم حرفيًّا ==================
 * «**عند بلوغ −10 (التعليق قبل أيّ إنهاء):**
 *  · **تعليق الحساب بالكامل فورًا** — كلّ العضويّات والدخول للوحة التطوّع.
 *    *(وبحكم البند 2، المعاملة الكاسرة لـ−10 تكون في القسم بالضرورة — الاختياري
 *    انتهى قبلها.)*
 *  · مهامه المفتوحة ⟵ مسار عدم التسليم عند أبلاينها **بلا خصومات إضافيّة أثناء
 *    التعليق** (عقوبته الآن هي التعليق ذاته) · ومساهماته تُسحَب بلا أثر.
 *  · **⭐ تغطية بوزشنه فورًا:** لو كان له **داونلاين**، تنتقل **مسؤوليّاته
 *    الإشرافيّة تلقائيًّا لأبلاينه المباشر** (تفويض مؤقّت: المراجعات · نوافذ
 *    محرّك التصعيد · دفعات الصب-تاسكات) لحظة التعليق — فلا يبقى فريق بلا مراجِع
 *    طوال مدّة التحقيق. ويعود التفويض تلقائيًّا عند إعادة التفعيل، أو **يتحوّل
 *    شغورًا حقيقيًّا** (سلّم الترقية) عند قرار الإقصاء.
 *  · **التصفير الشهري لا يفكّ التعليق** — الدرجة رقمٌ يتصفّر، والتعليق **حالة
 *    حساب** لا تُلغى بخوارزميّة تقويم؛ يظلّ معلَّقًا حتى قرار اللجنة والقمّة.»
 *
 * ================== أربعة أسئلة يحسمها النصّ ==================
 * **ما مدى «الحساب بالكامل»؟** النصّ يفسّر نفسه بالشرطة مباشرةً: «— **كلّ
 * العضويّات والدخول للوحة التطوّع**». فهو تعليق **طبقة التطوّع** لا طرد من
 * المنصّة: 13.4-س يقرّر أنّ الخروج نفسه — وهو أشدّ — يُبقي «**الحساب كمتدرّب
 * عاديّ وتختفي طبقة التطوّع فقط**»، فما دون الخروج أولى. ويقطع بذلك نصُّ اللجنة
 * نفسه: «دعوة المعلَّق **عبر بيانات تواصله** … **حسابه معلَّق فلا لوحة له**» —
 * فالمنفيّ عنه **اللوحة** لا الوجود.
 *
 * **وكيف يُقفَل الدخول عمليًّا؟** بلا حارسٍ جديد ولا مفتاحٍ مخترَع: أدوار التطوّع
 * كلّها **مقفوصة داخل عضويّاتها** (`role_user.membership_id` — 12.2.3)،
 * و`AccessEngine` لا يقيّم إلّا داخل **العضويّة النشطة**. فتعليق العضويّات هو
 * بذاته إقفال اللوحة — وأيّ حارسٍ إضافيّ فوقه يكون مصدرًا ثانيًا للحقيقة.
 *
 * **لماذا `suspended` لا `ended`؟** لأنّ للدرجة **عكسًا منصوصًا**: «(أ) فرصة …
 * **ويُعاد تفعيل حسابه وعضويّة قسمه**». والإنهاء طريقٌ بلا رجعة يُسقِط الدور
 * ويفتح الشاغر، وهو **قرار الإقصاء** الذي ينصّ 13.4-س-د أنّه لمشرف عام التطوّع
 * وحده — لا نتيجةٌ آليّة لرقم.
 *
 * **ومَن يراجع لفريقه؟** «تغطية بوزشنه فورًا». وتنفيذها هنا **خريطة تغطية**
 * تُكتَب في الصفّ ويقرأها `HandlerChain` حيّةً، فتنزلق نوافذ داونلاينه إلى
 * أبلاينه المباشر بلا لمس صفٍّ واحد من صفوف العضويّات — فيرجع كلٌّ لمكانه لحظة
 * الإفراج بلا إعادة بناء.
 */
class SuspensionService
{
    public const TABLE = 'volunteer_suspensions';

    /** حالة العضويّة أثناء التعليق — قابلة للعكس، بخلاف `ended` */
    public const MEMBERSHIP_STATUS = 'suspended';

    /** أسباب الإفراج المنصوصة (23-0.2-4 — قرار الميتينج الأوّل) */
    public const RELEASE_CHANCE = 'committee_chance';

    public const RELEASE_DISMISSAL = 'dismissal';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly NoDeliverySweeper $noDelivery,
        private readonly CommitteePath $committee,
    ) {}

    // ------------------------------------------------------------------ القراءة

    /** عتبة التعليق (−10) — من جدول Rep لا رقمًا محروقًا (2.13 · 13.4-ن) */
    public function threshold(): float
    {
        return rep_rule('limit.suspension', -10);
    }

    /** التعليق السارية آثاره الآن — وهو ما يقرأه كلّ حارسٍ في المجال */
    public function openSuspensionOf(?User $user): ?object
    {
        if (! $user || ! Schema::hasTable(self::TABLE)) {
            return null;
        }

        return DB::table(self::TABLE)
            ->where('user_id', $user->id)
            ->whereNull('released_at')
            ->latest('id')
            ->first();
    }

    public function isSuspended(?User $user): bool
    {
        return $this->openSuspensionOf($user) !== null;
    }

    /**
     * ⭐ **مَن يحمل مسؤوليّاته الإشرافيّة الآن؟** — يقرأها `HandlerChain` لحظةَ
     * حساب صاحب أيّ نافذة قرار، فلا يبقى داونلاينه بلا مراجِع (23-0.2-4).
     *
     * والخريطة تُقرأ **بالكيان أوّلًا**: مَن له عضويّتان في كيانين مختلفين
     * يغطّيه في كلٍّ **أبلاينُ ذلك الكيان** — لا سلطة عابرة للكيانات (23-0.2-4
     * من قواعد العضويّات المتعدّدة).
     */
    public function coverFor(?User $handler, ?int $entityId = null): ?User
    {
        $row = $this->openSuspensionOf($handler);

        if (! $row) {
            return null;
        }

        $coverage = json_decode((string) ($row->coverage ?? '[]'), true) ?: [];

        $match = null;

        foreach ($coverage as $entry) {
            if (empty($entry['cover_user_id'])) {
                continue;
            }

            if ($entityId !== null && (int) ($entry['entity_id'] ?? 0) === $entityId) {
                $match = $entry;
                break;
            }

            $match ??= $entry;
        }

        return $match ? User::query()->find((int) $match['cover_user_id']) : null;
    }

    // ------------------------------------------------------------------ التنفيذ

    /**
     * «**تعليق الحساب بالكامل فورًا**» — إن بلغ الرقم الظاهر العتبة.
     *
     * رخيصةٌ ومحروسة: تقرأ الرصيد، فإن لم يبلغ العتبة خرجت قبل أيّ استعلامٍ آخر.
     */
    public function enforce(?User $user, ?Transaction $breaking = null): ?array
    {
        if (! $user || ! Schema::hasTable(self::TABLE)) {
            return null;
        }

        $displayed = round($this->ledger->balance($user, LedgerService::REP), 2);

        if ($displayed > $this->threshold()) {
            return null;
        }

        // تعليقٌ واحد لا تعليقان — والصفّ المفتوح هو القفل
        if ($this->openSuspensionOf($user) !== null) {
            return null;
        }

        return $this->apply($user, $displayed, $breaking);
    }

    /**
     * تنفيذ الدرجة كاملةً كما ينصّ 23-0.2-4.
     *
     * @return array{suspension_id:int,rep:float,memberships:int,covered:int,tasks:int,contributions:int,windows:int,referral_id:?int}
     */
    public function apply(User $user, ?float $displayed = null, ?Transaction $breaking = null): array
    {
        $displayed ??= round($this->ledger->balance($user, LedgerService::REP), 2);

        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with('entity')
            ->get();

        /*
         | ⭐⭐ **الصفّ قبل الأثر** — قفلُ الدخول لا ترتيبُ ذوق.
         |
         | نفس العطب الذي أوقع `OptionalCutService` في تعاقبٍ لا نهائيّ: التنفيذ
         | نفسه يمرّ بمسارات قد تكتب حركة Rep، وكلّ حركة تمرّ على `RepLadder`
         | فتقرأ «لا تعليقَ مفتوحًا» وتبدأ تعليقًا داخل تعليق. ووجود الصفّ من
         | اللحظة الأولى يجعل الدرجة تقع مرّةً واحدة مهما تشعّبت آثارها.
         */
        $suspensionId = (int) DB::table(self::TABLE)->insertGetId([
            'user_id' => $user->id,
            'threshold' => $this->threshold(),
            'rep_at_suspension' => $displayed,
            'memberships_suspended' => 0,
            'tasks_handed_over' => 0,
            'contributions_withdrawn' => 0,
            'positions_covered' => 0,
            'coverage' => null,
            'detail' => null,
            'referral_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tasks = 0;
        $contributions = 0;
        $detail = [];

        /*
         | ⭐ **المهامّ والمساهمات قبل التغطية والتعليق** — وترتيبها منصوصٌ عليه
         | بالأثر: `NoDeliverySweeper::miss()` **يتخطّى** صاحب المهمّة إن كان في
         | وضع تفويض، فلو غطّينا بوزشنه أوّلًا لَبقيت مهامّه المفتوحة معلَّقةً
         | على مكتب معلَّق — والنصّ يقول «مهامه المفتوحة ⟵ مسار عدم التسليم».
         */
        foreach ($memberships as $membership) {
            $tasks += $this->handOverTasks($user, (int) $membership->entity_id);
            $contributions += $this->withdrawContributions($user, (int) $membership->entity_id);
        }

        $coverage = [];

        foreach ($memberships as $membership) {
            $downline = Membership::query()
                ->where('upline_id', $membership->id)
                ->where('status', 'active')
                ->count();

            $cover = $this->uplineUserOf($membership);

            $coverage[] = [
                'membership_id' => (int) $membership->id,
                'entity_id' => (int) $membership->entity_id,
                'entity' => (string) ($membership->entity->name_ar ?? ''),
                'downline' => $downline,
                // «لو كان له **داونلاين**، تنتقل مسؤوليّاته الإشرافيّة … لأبلاينه المباشر»
                'covered' => $downline > 0 && $cover !== null,
                'cover_user_id' => $cover?->id,
                'cover_name' => (string) ($cover->name ?? ''),
            ];

            $detail[] = [
                'membership_id' => (int) $membership->id,
                'entity_id' => (int) $membership->entity_id,
                'position_id' => (int) $membership->position_id,
                'was' => (string) $membership->status,
            ];

            // «تعليق … كلّ العضويّات» — والدور يبقى مقفوصًا فيها فيصير خاملًا معها
            $membership->forceFill(['status' => self::MEMBERSHIP_STATUS])->save();
        }

        // كاش الصلاحيّات يقرأ العضويّة النشطة — وقد صار لا نشطةَ له
        app(AccessEngine::class)->forget($user);

        // «**التعليق قبل أيّ إنهاء**» — واللجنة تولد معه لا بعده
        $referralId = $this->committee->refer(
            $user,
            CommitteePath::TRIGGER_DISPLAYED,
            $displayed,
            $this->threshold(),
            null,
        );

        $covered = collect($coverage)->where('covered', true)->count();

        DB::table(self::TABLE)->where('id', $suspensionId)->update([
            'memberships_suspended' => $memberships->count(),
            'tasks_handed_over' => $tasks,
            'contributions_withdrawn' => $contributions,
            'positions_covered' => $covered,
            'coverage' => json_encode($coverage, JSON_UNESCAPED_UNICODE),
            'detail' => json_encode([
                'memberships' => $detail,
                'breaking_transaction_id' => $breaking?->id,
                'breaking_entity_id' => $breaking?->entity_id,
            ], JSON_UNESCAPED_UNICODE),
            'referral_id' => $referralId,
            'updated_at' => now(),
        ]);

        /*
         | ⭐ **والنوافذ المفتوحة تنتقل الآن لا عند فواتها** — والخريطة كُتِبت
         | قبلها لأنّ `HandlerChain` يقرؤها منها. «لحظة التعليق» في النصّ تشمل
         | «**نوافذ محرّك التصعيد**» بالاسم، فلا تُترَك تنضج على مكتبٍ مقفول.
         */
        $windows = app(EscalationEngine::class)->reassignOpenWindows($user);

        $this->announce($user, $displayed);
        $this->announceCoverage($user, $coverage);

        AuditTrail::log(null, 'volunteer_suspension.apply', null, [], [
            'suspension_id' => $suspensionId,
            'user_id' => $user->id,
            'rep' => $displayed,
            'threshold' => $this->threshold(),
            'memberships_suspended' => $memberships->count(),
            'positions_covered' => $covered,
            'tasks_handed_over' => $tasks,
            'contributions_withdrawn' => $contributions,
            'windows_moved' => $windows,
            'referral_id' => $referralId,
        ]);

        return [
            'suspension_id' => $suspensionId,
            'rep' => $displayed,
            'memberships' => $memberships->count(),
            'covered' => $covered,
            'tasks' => $tasks,
            'contributions' => $contributions,
            'windows' => $windows,
            'referral_id' => $referralId,
        ];
    }

    /**
     * ⭐ **الإفراج — قرارٌ بشريّ لا انقضاء مدّة.**
     *
     * «(أ) فرصة: … **ويُعاد تفعيل حسابه وعضويّة قسمه** · المؤشّر الأحمر يظلّ
     * قائمًا كإنذار · **الاختياريّات تظلّ منتهية بحرمانها حتى التصفير**».
     *
     * ولذلك تُعاد **العضويّات المعلَّقة وحدها** إلى `active` — والمنتهية عند
     * −9.5 (`ended` بـ`optional_cut`) لا تُمَسّ أصلًا: النصّ يبقيها منتهية،
     * وحالتها ليست `suspended` فلا تدخل هذه الحلقة أساسًا.
     *
     * و«**ويعود التفويض تلقائيًّا عند إعادة التفعيل**»: التغطية ليست صفوفًا
     * تُحذَف بل قراءةٌ من الصفّ المفتوح — فإغلاق الصفّ هو عودةُ التفويض بذاته.
     *
     * @param  string  $reason  `committee_chance` أو `dismissal`
     */
    public function release(User $user, ?User $actor = null, string $reason = self::RELEASE_CHANCE, ?string $note = null): ?array
    {
        $row = $this->openSuspensionOf($user);

        if (! $row) {
            return null;
        }

        $detail = json_decode((string) ($row->detail ?? '{}'), true) ?: [];
        $ids = collect($detail['memberships'] ?? [])->pluck('membership_id')->map(fn ($id) => (int) $id)->all();

        $restored = 0;

        if ($ids !== [] && $reason !== self::RELEASE_DISMISSAL) {
            $restored = Membership::query()
                ->whereIn('id', $ids)
                ->where('status', self::MEMBERSHIP_STATUS)
                ->update(['status' => 'active', 'updated_at' => now()]);
        }

        DB::table(self::TABLE)->where('id', $row->id)->update([
            'released_at' => now(),
            'released_by' => $actor?->id,
            'release_reason' => $reason,
            'release_note' => $note,
            'updated_at' => now(),
        ]);

        app(AccessEngine::class)->forget($user);

        if ($reason !== self::RELEASE_DISMISSAL) {
            Notifier::send(
                $user,
                'account',
                $this->fill(setting('volunteer.suspension.release_title', 'رجع حسابك في التطوّع ✓')),
                $this->fill(setting('volunteer.suspension.release_body', 'اترفع التعليق ورجعت عضويّة قسمك شغّالة. المؤشّر الأحمر لسّه قايم كإنذار، والعضويّات الاختياريّة بتفضل مقفولة لحدّ التصفير الشهريّ.')),
                null,
                'volunteer',
            );
        }

        AuditTrail::log($actor, 'volunteer_suspension.release', $user, [], [
            'suspension_id' => (int) $row->id,
            'user_id' => $user->id,
            'reason' => $reason,
            'memberships_restored' => $restored,
        ]);

        return [
            'suspension_id' => (int) $row->id,
            'reason' => $reason,
            'memberships_restored' => $restored,
        ];
    }

    /**
     * ⭐ **قرار الميتينج الأوّل — (أ) فرصة** (23-0.2-4):
     * «إضافة **+1 يدويّة لمعدّل الالتزام** — معاملة موثَّقة بمرجع قرار اللجنة —
     * فيرتفع من −10 إلى **−9** ويُعاد تفعيل حسابه وعضويّة قسمه».
     *
     * والترتيب هنا **ملزِم**: تقع المعاملة **قبل** الإفراج. فلو أُفرِج عنه ورقمُه
     * ما زال −10 لَأعادت أوّلُ مسحةٍ (أو أيّ حركةٍ سالبة) الدورةَ من أوّلها —
     * والنصّ يجعل استئناف الدورة معلَّقًا بـ«**أيّ بلوغ جديد** لـ−10» أي بهبوطٍ
     * جديد بعد الارتفاع، لا بلحظةِ إفراجٍ على نفس الرقم.
     */
    public function grantChance(User $user, ?User $actor = null, ?string $note = null): ?array
    {
        $row = $this->openSuspensionOf($user);

        if (! $row) {
            return null;
        }

        $value = rep_rule('task.committee_chance', 1);
        $before = round($this->ledger->balance($user, LedgerService::REP), 2);

        FlowLedger::rep(
            $user,
            $value,
            'behavior',
            null,
            $this->fill(setting('volunteer.suspension.chance_reason', 'فرصة لجنة التحقيق — قرار موثَّق بمرجع اللجنة')),
            $actor?->id,
        );

        $released = $this->release($user, $actor, self::RELEASE_CHANCE, $note);

        return ($released ?? []) + [
            'rep_before' => $before,
            'rep_after' => round($this->ledger->balance($user, LedgerService::REP), 2),
            'chance' => $value,
        ];
    }

    /**
     * شبكة الأمان اليوميّة — ومكانها **بعد** مسحة البتر: النصّ يفترض عند −10
     * أنّ الاختياريّ انتهى قبله.
     *
     * @return array{scanned:int,suspended:int}
     */
    public function sweep(): array
    {
        $userIds = Membership::query()
            ->where('status', 'active')
            // «أخوكم» خارج كلّ العدّادات والمسارات التشغيليّة (13.4-ص-ج)
            ->whereDoesntHave('position', fn ($q) => $q->where('is_honorary', true))
            ->pluck('user_id')->unique()->values()->all();

        $result = ['scanned' => count($userIds), 'suspended' => 0];

        foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
            if ($this->enforce($user) !== null) {
                $result['suspended']++;
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------ داخليّ

    /** صاحب العضويّة الأعلى مباشرةً — «أبلاينه المباشر» بالحرف */
    private function uplineUserOf(Membership $membership): ?User
    {
        if (! $membership->upline_id) {
            return null;
        }

        $upline = Membership::query()->find($membership->upline_id);

        if (! $upline || $upline->status !== 'active') {
            return null;
        }

        return User::query()->find($upline->user_id);
    }

    /**
     * «مهامه المفتوحة ⟵ مسار عدم التسليم عند أبلاينها **بلا خصومات إضافيّة
     * أثناء التعليق** (عقوبته الآن هي التعليق ذاته)».
     *
     * فالمسار هو هو، والمرفوع **الخصم وحده** — بنفس عَلَم `deduct: false` الذي
     * كُتِب لدرجة البتر، فلا يتفرّق منطق التصعيد بين مستدعيَين.
     */
    private function handOverTasks(User $user, int $entityId): int
    {
        $tasks = Task::query()
            ->where('owner_id', $user->id)
            ->where('entity_id', $entityId)
            ->whereIn('status', TaskStatus::OPEN)
            ->get();

        $count = 0;

        foreach ($tasks as $task) {
            if ($this->noDelivery->miss($task, $this->fill(setting('volunteer.suspension.task_reason', 'اتعلّق حساب صاحب المهمّة عند عتبة التعليق — المهمّة تدور على مالك جديد')), deduct: false)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * «**ومساهماته تُسحَب بلا أثر**» — الحالة `withdrawn`، **والرصيد المعلَّق
     * يُحرَّر لصاحبه** كما يفعل المحرّك في حالة «سحب مساهم» (23-5)، فلا يبقى
     * VXP معلَّقًا على مساهمةٍ لن تُسلَّم.
     */
    private function withdrawContributions(User $user, int $entityId): int
    {
        $rows = TaskContribution::query()
            ->where('contributor_id', $user->id)
            ->whereIn('status', ['invited', 'accepted', 'returned', 'delivered'])
            ->whereHas('task', fn ($q) => $q->where('entity_id', $entityId))
            ->get();

        $count = 0;

        foreach ($rows as $contribution) {
            $held = (float) $contribution->held_amount;
            $owner = $contribution->invited_by ? User::query()->find($contribution->invited_by) : null;

            if ($owner && $held > 0 && $contribution->vxp_source === 'owner_balance') {
                FlowLedger::creditVxp(
                    $owner,
                    $held,
                    'contribution.hold_released',
                    $contribution,
                    $this->fill(setting('volunteer.suspension.hold_release_reason', 'تحرير الرصيد المعلَّق بعد سحب المساهمة عند تعليق الحساب')),
                    $owner->id,
                );
            }

            $contribution->forceFill(['status' => 'withdrawn', 'held_amount' => 0])->save();
            $count++;
        }

        return $count;
    }

    /** الرسالة تقول ماذا وقع وماذا بعده — ولا تعاتب ولا تفضح (2.17 · 24.4) */
    private function announce(User $user, float $displayed): void
    {
        Notifier::send(
            $user,
            'account',
            $this->fill(setting('volunteer.suspension.user_title', 'اتعلّقت عضويّاتك في التطوّع مؤقّتًا')),
            $this->fill(setting('volunteer.suspension.user_body', 'درجة الالتزام وصلت :score، فاتعلّقت عضويّاتك ولوحة التطوّع لحدّ ما لجنة التحقيق تسمع منك. مهامّك المفتوحة راحت لأبلايناتها بلا أيّ خصم جديد، ونقاط الإنتاج كلّها باقية. هنكلّمك على بيانات تواصلك لميعاد الميتينج.'), [':score' => number_format($displayed, 2)]),
            null,
            'volunteer',
        );
    }

    /**
     * «فلا يبقى فريق بلا مراجِع طوال مدّة التحقيق» — ومَن حمل الحمل يجب أن يعلم،
     * وإلّا صارت التغطية توجيهًا صامتًا لنوافذ تفوت على مكتبٍ لا يعرف أنّها عنده.
     *
     * @param  array<int,array<string,mixed>>  $coverage
     */
    private function announceCoverage(User $user, array $coverage): void
    {
        foreach ($coverage as $entry) {
            if (empty($entry['covered']) || empty($entry['cover_user_id'])) {
                continue;
            }

            $cover = User::query()->find((int) $entry['cover_user_id']);

            if (! $cover) {
                continue;
            }

            Notifier::send(
                $cover,
                'account',
                $this->fill(setting('volunteer.suspension.cover_title', 'انتقلت لك مسؤوليّات إشرافيّة مؤقّتًا')),
                $this->fill(setting('volunteer.suspension.cover_body', 'حساب :name اتعلّق مؤقّتًا، ومسؤوليّاته الإشرافيّة في :entity بقت عندك (المراجعات ونوافذ التصعيد ودفعات الصب-تاسكات) لحدّ ما ينتهي التحقيق. عدد اللي تحته: :downline.'), [
                    ':name' => (string) $user->name,
                    ':entity' => (string) ($entry['entity'] ?? ''),
                    ':downline' => (string) ($entry['downline'] ?? 0),
                ]),
                null,
                'volunteer',
            );
        }
    }

    /**
     * ملءُ متغيّرات نصٍّ **جاء من الإعدادات** (2.13).
     *
     * ⚠️ ولماذا يُمرَّر النصّ ولا يُمرَّر مفتاحه؟ لأنّ `settings:hardcoded` يقرأ
     * **الاستدعاء الأقرب** للنصّ الحرفيّ: ما كان داخل `setting()` ممتثلٌ لأنّه
     * قيمةٌ افتراضيّة يعدّلها المالك، وما كان داخل غلافٍ خاصّ **يُحسَب محروقًا**
     * ولو قرأ الإعدادَ بنفسه. والحارس محقٌّ: غلافٌ كهذا يخفي المفتاح عن كلّ
     * أداةٍ تمسح الكود بحثًا عن النصوص القابلة للتعديل. فيبقى `setting()`
     * ظاهرًا في موضع الاستعمال، وهذه تملأ المتغيّرات وحدها.
     *
     * @param  array<string,string>  $vars
     */
    private function fill(mixed $value, array $vars = []): string
    {
        $value = (string) $value;

        return $vars === [] ? $value : str_replace(array_keys($vars), array_values($vars), $value);
    }
}
