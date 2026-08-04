<?php

namespace App\Services\Volunteer\Objections;

use App\Models\Objection;
use App\Models\ObjectionMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Escalation\HandlerChain;
use App\Services\Volunteer\Meetings\MeetingLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * الاعتراض على معاملة (13.4-ط).
 *
 * قواعد حاكمة:
 *  - **اعتراض واحد لكلّ معاملة** خلال مهلة `rep.objection.window_days`،
 *    ويضيف صاحبه تفاصيل أيّ وقت ما دام ساريًا.
 *  - يذهب إلى **المسؤول المباشر عن المعترِض** — لا إلى مَن أضاف المعاملة.
 *  - ⭐ **لا أحد يعدّل المعاملة الأصليّة**: التصحيح بمعاملة عكسيّة موثّقة.
 *  - ⭐ **مسار قائم بذاته لا يندرج ضمن الحالات التسع** (23-6): تصعيده هنا على
 *    جدوله هو (`objections.sla_due_at` · `current_handler_id` · حالة «مُصعَّد»)،
 *    **ولا يُكتَب له صفّ في `escalations`**. وكتابته هناك بنوع لا يعرفه
 *    `CaseCatalog` كانت تُسقِط دورة العمل كلّها عند فوات نافذته.
 *  - ⭐⭐ **وصاحب المكتب يُحسَب بـ`HandlerChain` وحدها** — لا بسلسلة الأبلاين
 *    الخام. فالسلسلة الخام تعرف «مَن فوقه في الجدول» ولا تعرف الاستثناءين
 *    المنصوصين فوقها: **الغائب المفوَّض** (23-6: «كلّ نوافذ القرار الواردة إليه
 *    **تُوجَّه للبديل مباشرةً**») و**المعلَّق عند −10** (23-0.2-4: «تنتقل
 *    مسؤوليّاته الإشرافيّة تلقائيًّا لأبلاينه المباشر»). وكان هذا الملفّ يقرأ
 *    `MeetingScope::directManager()` — سلسلةً خامًّا — **فيصعد الاعتراضُ إلى
 *    مكتب غائبٍ له بديل** ويقف هناك حتى تفوت مهلته، ثمّ يصعد بفوات المهلة لا
 *    بقرار: عقوبةُ تباطؤٍ على غائبٍ معذور، وهو عين ما وُجِد وضعُ «غائب» ليمنعه.
 *  - ⭐ **والكيان قفصٌ هنا كذلك:** المكتب يُحسَب داخل **كيان المعاملة المعترَض
 *    عليها** (`transactions.entity_id`) — «لا سلطة عابرة للكيانات إطلاقًا»
 *    (23-0.2-عضويّات-4). فمن له عضويّتان يُحاكَم اعتراضُه في سلسلة الكيان الذي
 *    وقعت فيه المعاملة لا في أيّ سلسلةٍ أخرى.
 */
class ObjectionService
{
    /** حالات المسار المستقلّ — لا نوع على محرّك التصعيد (23-6) */
    public const CASE_TYPE = 'objection';

    /** الحالات الخمس بترتيبها */
    public const STATUSES = ['open', 'in_review', 'escalated', 'accepted', 'rejected'];

    public function __construct(
        private readonly MeetingLedger $ledger,
        private readonly HandlerChain $chain,
    ) {}

    public function windowDays(): int
    {
        return (int) setting('rep.objection.window_days', 5);
    }

    public function slaHours(): int
    {
        return (int) setting('rep.objection.sla_hours', setting('workflow.escalation.window_hours', 24));
    }

    /** آخر موعد للاعتراض على معاملة */
    public function deadlineFor(Transaction $transaction): ?Carbon
    {
        return $transaction->objection_deadline_at
            ?? $transaction->created_at?->copy()->addDays($this->windowDays());
    }

    /** هل ما زال بابُ الاعتراض مفتوحًا؟ */
    public function withinWindow(Transaction $transaction): bool
    {
        $deadline = $this->deadlineFor($transaction);

        return $deadline !== null && now()->lessThanOrEqualTo($deadline);
    }

    /** المتبقّي من المهلة بالأيّام — «باقي 3 أيّام» */
    public function daysLeft(Transaction $transaction): ?int
    {
        $deadline = $this->deadlineFor($transaction);

        if (! $deadline || now()->greaterThan($deadline)) {
            return null;
        }

        return (int) ceil(now()->diffInHours($deadline, false) / 24);
    }

    public function existingFor(Transaction $transaction): ?Objection
    {
        return Objection::query()->where('transaction_id', $transaction->id)->first();
    }

    /**
     * فتح اعتراض. يُرجِع رسالةً واضحة بدل رمي استثناء — الرسالة = ماذا حدث وماذا تفعل.
     *
     * @return array{ok:bool,message:string,objection:?Objection}
     */
    public function file(Transaction $transaction, User $user, string $reason, ?string $attachmentPath = null): array
    {
        if ((int) $transaction->user_id !== (int) $user->id) {
            return $this->fail(setting('volunteer_rep.objection_service.file_1', 'المعاملة دي مش بتاعتك.'));
        }

        if (! $this->withinWindow($transaction)) {
            return $this->fail(setting('volunteer_rep.objection_service.file_2', 'انتهت مهلة الاعتراض على المعاملة دي.'));
        }

        if ($this->existingFor($transaction)) {
            return $this->fail(setting('volunteer_rep.objection_service.file_3', 'فيه اعتراض واحد بالفعل على المعاملة دي — تقدر تضيف تفاصيل من صفحة اعتراضاتي.'));
        }

        // ⭐ المكتب من `HandlerChain` داخل كيان المعاملة — فالغائب يُتخطّى لبديله
        $handler = $this->chain->firstHandlerFor($user, $this->entityOf($transaction));

        $objection = DB::transaction(fn () => Objection::create([
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'reason' => $reason,
            'attachment_path' => $attachmentPath,
            // ⟵ المسؤول المباشر عن المعترِض، لا مَن أضاف المعاملة (13.4-ط)
            'current_handler_id' => $handler?->id,
            'status' => 'open',
            'sla_due_at' => now()->addHours($this->slaHours()),
        ]));

        $this->ledger->notify(
            $handler,
            'objection',
            setting('volunteer_rep.objection_service.file_4', 'اعتراض جديد على معاملة'),
            strtr(setting('volunteer_rep.objection_service.file_5', ':p1 اعترض على معاملة بقيمة :p2'), [':p1' => (string) ($user->name), ':p2' => (string) ($transaction->amount)]),
            route('volunteer.escalations.objections', ['objection' => $objection->id]),
        );

        return ['ok' => true, 'message' => setting('volunteer_rep.objection_service.file_6', 'وصل اعتراضك لمسؤولك المباشر ✓'), 'objection' => $objection];
    }

    /**
     * ⭐ تصعيد الاعتراضات الفائتة **على مسارها المستقلّ** (13.4-ط · 23-6):
     * فات الـSLA ⟵ يرتفع للأبلاين الأعلى بحالة «مُصعَّد» ومهلةٍ جديدة، حتى
     * السقف فيبقى على مكتبه — **ولا تسوية آليّة هنا**: الاعتراض تصحيحُ معاملةٍ
     * واقعة، وقراره بشريّ لا خوارزميّ.
     *
     * @return array{escalated:int, at_top:int}
     */
    public function runOverdue(): array
    {
        $result = ['escalated' => 0, 'at_top' => 0];

        Objection::query()
            ->whereNotIn('status', ['accepted', 'rejected'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->with('user')
            ->get()
            ->each(function (Objection $objection) use (&$result) {
                $handler = $objection->current_handler_id
                    ? User::query()->find($objection->current_handler_id)
                    : null;

                $entityId = $this->entityOf($objection->transaction);

                $next = $handler
                    ? $this->chain->nextHandlerAfter($handler, $entityId)
                    : $this->chain->firstHandlerFor($objection->user, $entityId);

                if (! $next || (int) $next->id === (int) $objection->current_handler_id) {
                    // بلغ السقف: يبقى على مكتبه بمهلةٍ جديدة ويُنبَّه — ولا يُقفَل بلا قرار
                    $objection->forceFill(['sla_due_at' => now()->addHours($this->slaHours())])->save();
                    $this->ledger->notify(
                        $handler,
                        'objection',
                        setting('volunteer_rep.objection_service.run_overdue_1', 'اعتراض فات مهلته وعندك'),
                        setting('volunteer_rep.objection_service.run_overdue_2', 'الاعتراض ده وصل سقف السلسلة — محتاج قرارك أنت.'),
                        route('volunteer.escalations.objections', ['objection' => $objection->id]),
                    );
                    $result['at_top']++;

                    return;
                }

                $objection->forceFill([
                    'current_handler_id' => $next->id,
                    'status' => 'escalated',
                    'sla_due_at' => now()->addHours($this->slaHours()),
                ])->save();

                $this->ledger->notify(
                    $next,
                    'objection',
                    setting('volunteer_rep.objection_service.run_overdue_3', 'صعد إليك اعتراض على معاملة'),
                    setting('volunteer_rep.objection_service.run_overdue_4', 'فاتت مهلة المستوى الأدنى — الاعتراض بقى عندك.'),
                    route('volunteer.escalations.objections', ['objection' => $objection->id]),
                );

                $result['escalated']++;
            });

        return $result;
    }

    /** الاعتراض ساري ما لم يُغلَق بقبول أو رفض */
    public function isActive(Objection $objection): bool
    {
        return ! in_array($objection->status, ['accepted', 'rejected'], true);
    }

    /** متأخّر عن الـSLA؟ — يُبرَز في القائمة */
    public function isOverdue(Objection $objection): bool
    {
        return $this->isActive($objection)
            && $objection->sla_due_at !== null
            && now()->greaterThan($objection->sla_due_at);
    }

    /** «إضافة تفاصيل» — متاحة أيّ وقت ما دام الاعتراض ساريًا */
    public function addMessage(Objection $objection, User $user, string $body, ?string $attachmentPath = null): array
    {
        if (! $this->isActive($objection)) {
            return $this->fail(setting('volunteer_rep.objection_service.add_message_1', 'الاعتراض ده اتقفل — مفيش إضافات بعد القرار.'));
        }

        ObjectionMessage::create([
            'objection_id' => $objection->id,
            'user_id' => $user->id,
            'body' => $body,
            'attachment_path' => $attachmentPath,
        ]);

        $this->ledger->notify(
            $objection->current_handler,
            'objection',
            setting('volunteer_rep.objection_service.add_message_2', 'تفاصيل جديدة على اعتراض'),
            strtr(setting('volunteer_rep.objection_service.add_message_3', ':p1 أضاف تفاصيل لاعتراضه.'), [':p1' => (string) ($user->name)]),
            route('volunteer.escalations.objections', ['objection' => $objection->id]),
        );

        return ['ok' => true, 'message' => setting('volunteer_rep.objection_service.add_message_4', 'اتحفظ ✓ التفاصيل اتضافت للاعتراض.'), 'objection' => $objection];
    }

    /**
     * ⭐ **ردّ المسؤول** (24.4-8): نصّ + مرفق ⟵ يدخل سلسلة النقاش نفسها،
     * وينقل الحالة إلى «قيد المراجعة» ويجدّد مهلته — فالردّ التزامٌ بالنظر
     * في الاعتراض لا إغلاقٌ له، والإغلاق قبولٌ أو رفض لا غير.
     */
    public function reply(Objection $objection, User $handler, string $body, ?string $attachmentPath = null): array
    {
        if (! $this->isActive($objection)) {
            return $this->fail(setting('volunteer_rep.objection_service.reply_1', 'الاعتراض ده اتقفل — مفيش ردّ بعد القرار.'));
        }

        ObjectionMessage::create([
            'objection_id' => $objection->id,
            'user_id' => $handler->id,
            'body' => $body,
            'attachment_path' => $attachmentPath,
        ]);

        $objection->forceFill([
            'status' => 'in_review',
            'sla_due_at' => now()->addHours($this->slaHours()),
        ])->save();

        $this->ledger->notify(
            $objection->user,
            'objection',
            setting('volunteer_rep.objection_service.reply_2', 'وصلك ردّ على اعتراضك'),
            strtr(setting('volunteer_rep.objection_service.reply_3', ':p1 ردّ على اعتراضك — الحالة دلوقتي «قيد المراجعة».'), [':p1' => (string) ($handler->name)]),
            route('volunteer.objections', ['objection' => $objection->id]),
        );

        return ['ok' => true, 'message' => setting('volunteer_rep.objection_service.reply_4', 'اتسجّل ردّك ✓ والاعتراض بقى قيد المراجعة.'), 'objection' => $objection];
    }

    /**
     * ⭐ **تصعيد المسؤول بيده** لمن فوقه بسبب مكتوب (24.4-8) — لا ينتظر فوات
     * المهلة. وهو **على المسار المستقلّ وحده**: حالةٌ ومهلةٌ وصاحبُ مكتبٍ جديد
     * على `objections`، **ولا صفّ في `escalations`** (23-6).
     */
    public function escalate(Objection $objection, User $handler, string $reason): array
    {
        if (! $this->isActive($objection)) {
            return $this->fail(setting('volunteer_rep.objection_service.escalate_1', 'الاعتراض ده اتقفل — مفيش تصعيد بعد القرار.'));
        }

        $next = $this->chain->nextHandlerAfter($handler, $this->entityOf($objection->transaction));

        if (! $next || (int) $next->id === (int) $handler->id) {
            return $this->fail(setting('volunteer_rep.objection_service.escalate_2', 'أنت سقف السلسلة — الاعتراض ده قراره عندك ومش هيصعد لحدّ.'));
        }

        ObjectionMessage::create([
            'objection_id' => $objection->id,
            'user_id' => $handler->id,
            'body' => strtr(setting('volunteer_rep.objection_service.escalate_3', 'صعّدتُه لـ:p1 — السبب: :p2'), [':p1' => (string) ($next->name), ':p2' => (string) ($reason)]),
        ]);

        $objection->forceFill([
            'current_handler_id' => $next->id,
            'status' => 'escalated',
            'sla_due_at' => now()->addHours($this->slaHours()),
        ])->save();

        $this->ledger->notify(
            $next,
            'objection',
            setting('volunteer_rep.objection_service.escalate_4', 'صعد إليك اعتراض على معاملة'),
            strtr(setting('volunteer_rep.objection_service.escalate_5', ':p1 صعّد الاعتراض إليك — السبب: :p2'), [':p1' => (string) ($handler->name), ':p2' => (string) ($reason)]),
            route('volunteer.escalations.objections', ['objection' => $objection->id]),
        );

        $this->ledger->notify(
            $objection->user,
            'objection',
            setting('volunteer_rep.objection_service.escalate_6', 'اتصعّد اعتراضك'),
            strtr(setting('volunteer_rep.objection_service.escalate_7', 'الاعتراض بقى عند :p1 — وسلّم التصعيد بيوضّح المستوى الحاليّ.'), [':p1' => (string) ($next->name)]),
            route('volunteer.objections', ['objection' => $objection->id]),
        );

        return ['ok' => true, 'message' => strtr(setting('volunteer_rep.objection_service.escalate_8', 'اتصعّد الاعتراض لـ:p1 ✓'), [':p1' => (string) ($next->name)]), 'objection' => $objection];
    }

    /**
     * سلّم التصعيد المرئيّ: سلسلة **أصحاب القرار** من المعترِض لأعلى حتى السقف،
     * وعلامةٌ على المستوى الحاليّ — يراه الجميع حتى المتطوّع (13.4-ط).
     *
     * ⭐ **ويُبنى بنفس الدالّة التي تحرّك الاعتراض** (`HandlerChain`) لا بسلسلة
     * الأبلاين الخام. ولولا ذلك لَانفصل ما يراه الناس عمّا يقع فعلًا: الاعتراض
     * على مكتب **بديل الغائب** والسلّم يرسم الغائب نفسه — فلا يجد المعترِض
     * مكتبَه الحاليّ في السلّم أصلًا («المستوى الحاليّ» بلا علامة)، ويظنّ أنّ
     * اعتراضه ضاع. **المصدر الواحد للحركة هو المصدر الواحد للعرض.**
     *
     * @return Collection<int,array{user:User,level:int,is_current:bool,is_passed:bool}>
     */
    public function ladder(Objection $objection): Collection
    {
        $owner = $objection->user;

        if (! $owner) {
            return collect();
        }

        $entityId = $this->entityOf($objection->transaction);
        $currentId = (int) $objection->current_handler_id;

        $handlers = collect();
        $seen = [];
        $cursor = $owner;
        $guard = 0;

        while ($guard++ < 20) {
            $next = $this->chain->firstHandlerFor($cursor, $entityId);

            if (! $next || in_array((int) $next->id, $seen, true)) {
                break;
            }

            $seen[] = (int) $next->id;
            $handlers->push($next);

            if ($this->chain->isTop($next, $entityId)) {
                break;
            }

            $cursor = $next;
        }

        $passed = true;

        return $handlers->values()->map(function (User $user, int $index) use ($currentId, &$passed) {
            $isCurrent = (int) $user->id === $currentId;
            $row = [
                'user' => $user,
                'level' => $index + 1,
                'is_current' => $isCurrent,
                'is_passed' => $passed && ! $isCurrent,
            ];

            if ($isCurrent) {
                $passed = false;
            }

            return $row;
        });
    }

    /**
     * كيان المعاملة المعترَض عليها — **قفصُ السلسلة** التي يُحسَب فيها المكتب.
     * وبلا كيان (حركةٌ شخصيّة كخصم الخمول) تُقاس السلسلة على العضويّة الأساسيّة،
     * وهو ما يفعله `HandlerChain` حين لا يُمرَّر كيان.
     */
    private function entityOf(?Transaction $transaction): ?int
    {
        return $transaction?->entity_id ? (int) $transaction->entity_id : null;
    }

    /**
     * معاينة أثر التصحيح قبل تنفيذه: «Rep يرتفع من −3.5 إلى −3».
     *
     * @return array{from:float,to:float,delta:float}
     */
    public function correctionPreview(Objection $objection): array
    {
        $transaction = $objection->transaction;
        $user = $objection->user;

        $from = $user ? $this->ledger->balance($user, 'rep') : 0.0;
        $delta = $transaction ? -1 * (float) ($transaction->applied_amount ?? $transaction->amount) : 0.0;

        return ['from' => round($from, 2), 'to' => round($from + $delta, 2), 'delta' => round($delta, 2)];
    }

    /**
     * ⭐ قبول الاعتراض = **معاملة عكسيّة موثّقة**، والأصل يبقى كما هو بلا لمس.
     * (القرار نفسه فعلُ المسؤول في صفحة التصعيدات — هنا الآليّة وحدها.)
     */
    public function accept(Objection $objection, User $decider, ?string $note = null): array
    {
        if (! $this->isActive($objection)) {
            return $this->fail(setting('volunteer_rep.objection_service.accept_1', 'الاعتراض ده اتقفل بالفعل.'));
        }

        $transaction = $objection->transaction;

        if (! $transaction) {
            return $this->fail(setting('volunteer_rep.objection_service.accept_2', 'المعاملة الأصليّة مش موجودة.'));
        }

        $preview = $this->correctionPreview($objection);

        $correction = $this->ledger->reverse(
            $transaction,
            strtr(setting('volunteer_rep.objection_service.accept_3', 'معاملة تصحيحيّة بعد قبول اعتراض #:p1'), [':p1' => (string) ($objection->id)]),
            $decider->id,
        );

        /*
         | ⭐ لا قبولَ بلا **معاملةٍ عكسيّةٍ ظاهرة**: لو تعذّر كتبُها فالاعتراض
         | يبقى مفتوحًا على مكتب صاحبه. القبول الذي لا يخلّف صفًّا مقروءًا في
         | الكشف = تعديلٌ صامت للأصل — وهو الممنوع بعينه (13.4-ط).
         */
        if (! $correction) {
            return $this->fail(setting('volunteer_rep.objection_service.accept_4', 'تعذّر كتابة المعاملة التصحيحيّة — والاعتراض ما اتقفلش.'));
        }

        $objection->forceFill([
            'status' => 'accepted',
            'decision_note' => $note,
            'decided_by' => $decider->id,
            'correction_transaction_id' => $correction->id,
            'closed_at' => now(),
        ])->save();

        $this->ledger->notify(
            $objection->user,
            'objection',
            setting('volunteer_rep.objection_service.accept_5', 'اتقبل اعتراضك ✓'),
            strtr(setting('volunteer_rep.objection_service.accept_6', 'اتعمل تصحيح بمعاملة عكسيّة — درجتك من :p1 لـ:p2.'), [':p1' => (string) ($preview['from']), ':p2' => (string) ($preview['to'])]),
            route('volunteer.objections', ['objection' => $objection->id]),
        );

        return ['ok' => true, 'message' => setting('volunteer_rep.objection_service.accept_7', 'اتقبل الاعتراض واتسجّلت معاملة تصحيحيّة.'), 'objection' => $objection];
    }

    /** رفض الاعتراض بإغلاقه بسبب موثَّق */
    public function reject(Objection $objection, User $decider, string $note): array
    {
        if (! $this->isActive($objection)) {
            return $this->fail(setting('volunteer_rep.objection_service.reject_1', 'الاعتراض ده اتقفل بالفعل.'));
        }

        $objection->forceFill([
            'status' => 'rejected',
            'decision_note' => $note,
            'decided_by' => $decider->id,
            'closed_at' => now(),
        ])->save();

        $this->ledger->notify(
            $objection->user,
            'objection',
            setting('volunteer_rep.objection_service.reject_2', 'اتقفل اعتراضك'),
            $note,
            route('volunteer.objections', ['objection' => $objection->id]),
        );

        return ['ok' => true, 'message' => setting('volunteer_rep.objection_service.reject_3', 'اتقفل الاعتراض بسببه.'), 'objection' => $objection];
    }

    /** لون الحالة من القاموس المقفول (2.16) — ومعه رمزه دائمًا */
    public function statusState(string $status): string
    {
        return match ($status) {
            'accepted' => 'ok',
            'rejected' => 'danger',
            'escalated' => 'warn',
            'in_review' => 'warn',
            default => 'idle',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'open' => setting('volunteer_rep.objection_service.status_label_1', 'مفتوح'),
            'in_review' => setting('volunteer_rep.objection_service.status_label_2', 'قيد المراجعة'),
            'escalated' => setting('volunteer_rep.objection_service.status_label_3', 'مُصعَّد'),
            'accepted' => setting('volunteer_rep.objection_service.status_label_4', 'مقبول'),
            'rejected' => setting('volunteer_rep.objection_service.status_label_5', 'مرفوض'),
            default => $status,
        };
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'objection' => null];
    }
}
