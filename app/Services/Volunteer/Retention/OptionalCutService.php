<?php

namespace App\Services\Volunteer\Retention;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Notifications\Notifier;
use App\Services\Volunteer\Escalation\FlowLedger;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\People\PositionRoleAssigner;
use App\Services\Volunteer\Tasks\NoDeliverySweeper;
use App\Services\Volunteer\Tasks\TaskStatus;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * ⭐ **الدرجة الوسطى من سلّم العتبات: «بتر الاختياريّ» عند −9.5**.
 *
 * ================== النصّ الحاكم ==================
 * **13.4-س-أ:** «**إقصاء** — **حصرًا** عبر سلّم العتبات (−8 إنذار ⟵ **−9.5 بتر
 * الاختياريّ** ⟵ −10 تعليق ولجنة تحقيق ⟵ قرار بشريّ من مشرف عام التطوّع)».
 *
 * **23-0.2 (إجراء عتبات الهبوط — 2):** «**عند بلوغ −9.5 (بتر الاختياري):**
 * **تُنهى فورًا عضويّاته في المحافظات والملفات إن وُجدت** — أيًّا كان كيان
 * المعاملة التي بلغت بها العتبة: تُقفَل العضويّات بتاريخها في التايم-لاين ·
 * مهامه المفتوحة هناك ⟵ **مسار عدم التسليم عند أبلاين كلٍّ منها بلا خصم جديد
 * عليه** (خصومه وقعت لحظتها أصلًا) · مساهماته المفتوحة هناك **تُسحَب بلا أثر
 * على أيّ طرف** · نقاط الإنتاج (VXP) **تبقى كاملة** · **ويستمرّ حسابه وعضويّة
 * قسمه شغّالَين** — القسم هو العمود الفقري وساحة الإصلاح الأخيرة. *(لا عضويّات
 * اختياريّة عنده؟ العتبة تمرّ بلا أثر.)*»
 *
 * **23-0.2 (3):** «**الحرمان:** لا يفتح عضويّة جديدة في مسارَي المحافظات
 * والملفات **حتى التصفير الشهري التالي** — وبعده الانضمام متاح عاديًّا».
 *
 * ================== ثلاثة أسئلة يحسمها النصّ ==================
 * **مَن يقرّره؟** لا أحد. **النظام** عند بلوغ الرقم: «تُنهى **فورًا**» بلا فاعل
 * ولا اعتماد — بخلاف الإقصاء الذي ينصّ 13.4-س-د أنّه «**لمشرف عام التطوّع**».
 * فهي الدرجة الآليّة الوحيدة ذات الأثر التنظيميّ في السلّم.
 *
 * **على ماذا يقع؟** على **العضويّات في مسارَي «محافظة» و«ملفّ» وحدهما**، لأنّ
 * 23-0.2-7 يسمّيها بالحرف: «المحافظات والملفات **عضويّات اختياريّة** تُبتَر
 * أوّلًا حمايةً للطرفين؛ أمّا **الأقسام فأساسيّة — العمود الفقري للكيان كلّه**».
 * فـ«الاختياريّ» **صفة العضويّة المبتورة لا صفة القرار** — ليس بترًا «يجوز
 * تنفيذه»، بل بترٌ **لِما هو اختياريّ**.
 *
 * **ما الذي يميّزه عن التعليق؟** التعليق (−10) يوقف **الحساب كلّه** ويفتح لجنة
 * تحقيق وينتهي بقرار بشريّ. أمّا البتر فيُبقي **الحساب وعضويّة القسم شغّالَين**
 * — «القسم هو العمود الفقري **وساحة الإصلاح الأخيرة**». فهو تضييقٌ للدائرة لا
 * إخراجٌ من الباب: يرفع عنه العبء الذي لم يعد يحتمله ويُبقي له مكانًا يصلح فيه.
 */
class OptionalCutService
{
    public const TABLE = 'volunteer_optional_cuts';

    public const END_REASON = 'optional_cut';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly RepService $rep,
        private readonly NoDeliverySweeper $noDelivery,
        private readonly PositionRoleAssigner $roles,
    ) {}

    // ------------------------------------------------------------------ القراءة

    /** عتبة البتر (−9.5) — من جدول Rep الموحّد لا رقمًا محروقًا (2.13 · 13.4-ن) */
    public function threshold(): float
    {
        return rep_rule('limit.optional_cut', -9.5);
    }

    /**
     * مسارات العضويّات **الاختياريّة** — «المحافظات والملفات» (23-0.2-7).
     * والمفاتيح مفاتيح جدول `tracks` بالحرف، وهي إعداد لا قائمة محفورة.
     *
     * @return array<int,string>
     */
    public function optionalTrackKeys(): array
    {
        $keys = setting('volunteer.optional_cut.tracks', ['governorate', 'case_file']);

        $keys = is_array($keys) ? array_values(array_filter($keys, 'is_string')) : [];

        return $keys !== [] ? $keys : ['governorate', 'case_file'];
    }

    /** هل الكيان في مسارٍ اختياريّ؟ — والقسم ليس منها أبدًا (العمود الفقري) */
    public function isOptionalEntity(?Entity $entity): bool
    {
        $key = $entity?->track?->key;

        return $key !== null && in_array($key, $this->optionalTrackKeys(), true);
    }

    /** عضويّاته النشطة في المسارات الاختياريّة وحدها */
    public function optionalMembershipsOf(User $user)
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('entity.track', fn ($q) => $q->whereIn('key', $this->optionalTrackKeys()))
            ->with('entity.track')
            ->get();
    }

    /** صفّ البتر السارية آثاره الآن — وهو ما يقرأه بابُ الانضمام */
    public function openCutOf(User $user): ?object
    {
        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }

        return DB::table(self::TABLE)
            ->where('user_id', $user->id)
            ->whereNull('released_at')
            ->latest('id')
            ->first();
    }

    /**
     * ⭐ **الحرمان** (23-0.2-3): محرومٌ من فتح عضويّة جديدة في المسارين
     * **حتى التصفير الشهريّ التالي** — ولا يُفَكّ بعودة الرقم فوق العتبة داخل
     * الشهر نفسه، فالنصّ علّق الفكّ **بالتصفير** لا بالرقم.
     */
    public function isDeprived(User $user): bool
    {
        $cut = $this->openCutOf($user);

        if (! $cut) {
            return false;
        }

        return $cut->deprived_until === null || Carbon::parse($cut->deprived_until)->isFuture();
    }

    /**
     * حارس الانضمام — يُستدعى قبل فتح أيّ عضويّة (تسكين · دعوة ملفّ).
     *
     * @throws ValidationException
     */
    public function assertMayJoin(User $user, ?Entity $entity): void
    {
        if (! $this->isOptionalEntity($entity) || ! $this->isDeprived($user)) {
            return;
        }

        $cut = $this->openCutOf($user);
        $until = $cut?->deprived_until ? Carbon::parse($cut->deprived_until)->format('Y-m-d') : null;

        throw ValidationException::withMessages([
            'entity_id' => 'العضويّات الاختياريّة (المحافظات والملفات) مقفولة مؤقّتًا لهذا المتطوّع بعد بلوغه عتبة '
                .number_format($this->threshold(), 2).' — وتُفتَح مع التصفير الشهريّ'
                .($until ? ' يوم '.$until : '').'، وقسمه شغّال زيّ ما هو.',
        ]);
    }

    // ------------------------------------------------------------------ التنفيذ

    /**
     * ⭐ نقطة النداء من **جسور Rep** في المجال (`FlowLedger` · `MeetingLedger` ·
     * `LedgerBridge` · جسرَي `Integrations` · سلّم الخمول) — لأنّ النصّ يقول
     * «تُنهى **فورًا**» لا «في مسحة الغد».
     *
     * وهي محروسة بشرطين رخيصين قبل أيّ استعلام: **حركة سالبة** و**عملة Rep**؛
     * فلا تلمس مسار المنح ولا مسار VXP أصلًا.
     *
     * ولماذا `report()` لا رميُ الاستثناء؟ لأنّ فشل البتر يجب ألّا يُسقِط
     * **المعاملة الواقعة** ولا يمحوها — لكنّه **لا يمرّ صامتًا** أيضًا: يُسجَّل
     * في سجلّ الأخطاء، والمسحة اليوميّة تلتقط الحالة في مرورها التالي.
     */
    public static function afterRepMovement(?User $user, string $currencyCode, float $signedAmount): void
    {
        if ($user === null || $signedAmount >= 0 || $currencyCode !== LedgerService::REP) {
            return;
        }

        try {
            app(self::class)->enforce($user);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * ⭐ **«تُنهى فورًا»** — تنفيذ الدرجة إن بلغ الرقم الظاهر العتبة.
     *
     * وهي **رخيصة ومحروسة**: تقرأ الرصيد، فإن لم يبلغ العتبة خرجت فورًا.
     */
    public function enforce(?User $user): ?array
    {
        if (! $user || ! Schema::hasTable(self::TABLE)) {
            return null;
        }

        $displayed = round($this->ledger->balance($user, LedgerService::REP), 2);

        if ($displayed > $this->threshold()) {
            return null;
        }

        // بترٌ واحدٌ لكلّ دورةِ حرمان — والدورة تنتهي بالتصفير الشهريّ
        if ($this->openCutOf($user) !== null) {
            return null;
        }

        return $this->apply($user, $displayed);
    }

    /**
     * تنفيذ الدرجة كاملةً كما ينصّ 23-0.2-2.
     *
     * @return array{cut_id:int,rep:float,memberships:int,tasks:int,contributions:int,entities:array<int,string>}
     */
    public function apply(User $user, ?float $displayed = null): array
    {
        $displayed ??= round($this->ledger->balance($user, LedgerService::REP), 2);

        $memberships = $this->optionalMembershipsOf($user);

        /*
         | ⭐⭐ **الصفّ يُكتَب قبل الأثر لا بعده — وهذا شرط سلامة لا ترتيبُ ذوق.**
         |
         | لأنّ التنفيذ نفسه يمسّ المهامّ والمساهمات، وأيّ مسارٍ فرعيّ يكتب حركة
         | Rep سالبة سيمرّ على `afterRepMovement()` فيقرأ «لا بترَ مفتوحًا» ويبدأ
         | بترًا ثانيًا داخل الأوّل ⟵ **تعاقبٌ لا نهائيّ يُعلّق العمليّة كلّها**.
         | (وقع فعلًا أثناء اختبار الطفرة حين أُعيد الخصم إلى مسار عدم التسليم:
         | العمليّة لم تنتهِ ولم ترمِ خطأً — عَلِقت.) فوجود الصفّ من اللحظة
         | الأولى هو **قفل الدخول** الذي يجعل الدرجة تقع مرّةً واحدة مهما تشعّبت
         | آثارها، والعدّادات تُحدَّث عليه بعد الفراغ.
         */
        $cutId = (int) DB::table(self::TABLE)->insertGetId([
            'user_id' => $user->id,
            'threshold' => $this->threshold(),
            'rep_at_cut' => $displayed,
            'memberships_ended' => 0,
            'tasks_handed_over' => 0,
            'contributions_withdrawn' => 0,
            'deprived_until' => $this->rep->nextResetAt(),
            'detail' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tasks = 0;
        $contributions = 0;
        $entities = [];

        foreach ($memberships as $membership) {
            $entity = $membership->entity;

            $entities[] = [
                'membership_id' => (int) $membership->id,
                'entity_id' => (int) $membership->entity_id,
                'entity' => (string) ($entity->name_ar ?? ''),
                'track' => (string) ($entity->track->key ?? ''),
            ];

            // 1) مهامّه المفتوحة هناك ⟵ مسار عدم التسليم **بلا خصم جديد عليه**
            $tasks += $this->handOverTasks($user, (int) $membership->entity_id);

            // 2) مساهماته المفتوحة هناك ⟵ تُسحَب **بلا أثر على أيّ طرف**
            $contributions += $this->withdrawContributions($user, (int) $membership->entity_id);

            // 3) تُقفَل العضويّة **بتاريخها** — والدور يُسحَب بها (12.2.3-ب)
            $membership->forceFill([
                'status' => 'ended',
                'ended_at' => now(),
                'end_reason' => self::END_REASON,
            ])->save();

            $this->roles->revoke($membership);
        }

        // ⚠️ VXP لا يُمَسّ هنا بحرفٍ واحد: «نقاط الإنتاج (VXP) **تبقى كاملة**».
        // وعضويّة القسم لا تدخل الحلقة أصلًا: «ويستمرّ حسابه وعضويّة قسمه شغّالَين».

        DB::table(self::TABLE)->where('id', $cutId)->update([
            'memberships_ended' => $memberships->count(),
            'tasks_handed_over' => $tasks,
            'contributions_withdrawn' => $contributions,
            'detail' => json_encode($entities, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        $this->announce($user, $memberships->count(), $displayed);

        AuditTrail::log(null, 'volunteer_optional_cut.apply', null, [], [
            'cut_id' => $cutId,
            'user_id' => $user->id,
            'rep' => $displayed,
            'threshold' => $this->threshold(),
            'memberships_ended' => $memberships->count(),
            'tasks_handed_over' => $tasks,
            'contributions_withdrawn' => $contributions,
            'entities' => $entities,
        ]);

        return [
            'cut_id' => $cutId,
            'rep' => $displayed,
            'memberships' => $memberships->count(),
            'tasks' => $tasks,
            'contributions' => $contributions,
            'entities' => $entities,
        ];
    }

    /**
     * المسحة الدوريّة — شبكة أمان خلف «فورًا»، ومكانها **قبل مسحة اللجنة**:
     * النصّ يفترضه صراحةً عند −10 («وبحكم البند 2، المعاملة الكاسرة لـ−10 تكون
     * في القسم بالضرورة — **الاختياري انتهى قبلها**»)، فترتيب المسحتين ليس
     * ترتيبًا شكليًّا بل شرطُ صحّة السلّم.
     *
     * @return array{scanned:int,cut:int,released:int}
     */
    public function sweep(): array
    {
        $released = $this->releaseExpired();

        $userIds = Membership::query()
            ->where('status', 'active')
            // «أخوكم» خارج كلّ العدّادات والمسارات التشغيليّة (13.4-ص-ج)
            ->whereDoesntHave('position', fn ($q) => $q->where('is_honorary', true))
            ->pluck('user_id')->unique()->values()->all();

        $result = ['scanned' => count($userIds), 'cut' => 0, 'released' => $released];

        if ($userIds === []) {
            return $result;
        }

        foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
            if ($this->enforce($user) !== null) {
                $result['cut']++;
            }
        }

        return $result;
    }

    /**
     * ⭐ رفع الحرمان بعد التصفير الشهريّ — «وبعده الانضمام متاح عاديًّا».
     * وبلا هذا المُفرِج يصير الحرمان أبديًّا: عقوبةٌ مؤقّتة بالنصّ ودائمة بالكود.
     *
     * @return int عدد الحالات التي رُفِع عنها الحرمان
     */
    public function releaseExpired(): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return DB::table(self::TABLE)
            ->whereNull('released_at')
            ->whereNotNull('deprived_until')
            ->where('deprived_until', '<=', now())
            ->update(['released_at' => now(), 'updated_at' => now()]);
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * «مهامه المفتوحة هناك ⟵ **مسار عدم التسليم** عند أبلاين كلٍّ منها **بلا
     * خصم جديد عليه** — (خصومه وقعت لحظتها أصلًا)».
     *
     * فالمسار نفسه (تصعيد الحالة 4 على المحرّك) والخصم وحده هو المرفوع.
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
            if ($this->noDelivery->miss($task, 'انتهت عضويّته الاختياريّة عند عتبة البتر — المهمّة تدور على مالك جديد', deduct: false)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * «مساهماته المفتوحة هناك **تُسحَب بلا أثر على أيّ طرف**» — فالحالة
     * `withdrawn`، **والرصيد المعلَّق يُحرَّر لصاحبه** بنفس ما يفعله المحرّك في
     * حالة «سحب مساهم» (23-5)، فلا يبقى VXP معلَّقًا على مساهمةٍ لن تُسلَّم.
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
                    'تحرير الرصيد المعلَّق بعد سحب المساهمة عند بتر الاختياريّ',
                    $owner->id,
                );
            }

            $contribution->forceFill(['status' => 'withdrawn', 'held_amount' => 0])->save();
            $count++;
        }

        return $count;
    }

    /**
     * الرسالة تشرح **ماذا وقع وماذا بقي** ولا تعاتب (2.17 · 24.4) — وأهمّ ما
     * فيها أنّ القسم باقٍ: هذه هي رسالة الدرجة كلّها.
     */
    private function announce(User $user, int $ended, float $displayed): void
    {
        // العنوان يقول ما حدث فعلًا — فمن لا عضويّة اختياريّة عنده لم يُقفَل له شيء
        $title = $ended > 0
            ? 'اتقفلت عضويّاتك في المحافظات والملفات مؤقّتًا'
            : 'درجة الالتزام وصلت عتبة تخفيف الحمل';

        $body = $ended > 0
            ? 'درجة الالتزام وصلت '.number_format($displayed, 2).'، فاتقفلت عضويّاتك الاختياريّة ('
                .$ended.') عشان نخفّف الحمل. قسمك وحسابك شغّالين زيّ ما هما، ومهامّك هناك راحت لأبلايناتها '
                .'بلا أيّ خصم جديد عليك، ونقاط الإنتاج كلّها باقية. الانضمام لمحافظة أو ملفّ يرجع مع التصفير الشهريّ.'
            : 'درجة الالتزام وصلت '.number_format($displayed, 2).' — وما عندكش عضويّات اختياريّة تتقفل، فالعتبة عدّت بلا أثر. '
                .'قسمك وحسابك شغّالين، والانضمام لمحافظة أو ملفّ يرجع مع التصفير الشهريّ.';

        Notifier::send($user, 'account', $title, $body, null, 'volunteer');
    }
}
