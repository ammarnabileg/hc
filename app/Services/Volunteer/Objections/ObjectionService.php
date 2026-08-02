<?php

namespace App\Services\Volunteer\Objections;

use App\Models\Objection;
use App\Models\ObjectionMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Meetings\MeetingLedger;
use App\Services\Volunteer\Meetings\MeetingScope;
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
 */
class ObjectionService
{
    /** حالات المسار المستقلّ — لا نوع على محرّك التصعيد (23-6) */
    public const CASE_TYPE = 'objection';

    /** الحالات الخمس بترتيبها */
    public const STATUSES = ['open', 'in_review', 'escalated', 'accepted', 'rejected'];

    public function __construct(
        private readonly MeetingScope $scope,
        private readonly MeetingLedger $ledger,
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
            return $this->fail('المعاملة دي مش بتاعتك.');
        }

        if (! $this->withinWindow($transaction)) {
            return $this->fail('انتهت مهلة الاعتراض على المعاملة دي.');
        }

        if ($this->existingFor($transaction)) {
            return $this->fail('فيه اعتراض واحد بالفعل على المعاملة دي — تقدر تضيف تفاصيل من صفحة اعتراضاتي.');
        }

        $handler = $this->scope->directManager($user);

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
            'اعتراض جديد على معاملة',
            $user->name.' اعترض على معاملة بقيمة '.$transaction->amount,
            route('volunteer.objections', ['objection' => $objection->id]),
        );

        return ['ok' => true, 'message' => 'وصل اعتراضك لمسؤولك المباشر ✓', 'objection' => $objection];
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

                $next = $handler ? $this->scope->directManager($handler) : $this->scope->directManager($objection->user);

                if (! $next || (int) $next->id === (int) $objection->current_handler_id) {
                    // بلغ السقف: يبقى على مكتبه بمهلةٍ جديدة ويُنبَّه — ولا يُقفَل بلا قرار
                    $objection->forceFill(['sla_due_at' => now()->addHours($this->slaHours())])->save();
                    $this->ledger->notify(
                        $handler,
                        'objection',
                        'اعتراض فات مهلته وعندك',
                        'الاعتراض ده وصل سقف السلسلة — محتاج قرارك أنت.',
                        route('volunteer.objections', ['objection' => $objection->id]),
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
                    'صعد إليك اعتراض على معاملة',
                    'فاتت مهلة المستوى الأدنى — الاعتراض بقى عندك.',
                    route('volunteer.objections', ['objection' => $objection->id]),
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
            return $this->fail('الاعتراض ده اتقفل — مفيش إضافات بعد القرار.');
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
            'تفاصيل جديدة على اعتراض',
            $user->name.' أضاف تفاصيل لاعتراضه.',
            route('volunteer.objections', ['objection' => $objection->id]),
        );

        return ['ok' => true, 'message' => 'اتحفظ ✓ التفاصيل اتضافت للاعتراض.', 'objection' => $objection];
    }

    /**
     * سلّم التصعيد المرئيّ: سلسلة الأبلاين من المعترِض لأعلى حتى السقف،
     * وعلامةٌ على المستوى الحاليّ — يراه الجميع حتى المتطوّع (13.4-ط).
     *
     * @return Collection<int,array{user:User,level:int,is_current:bool,is_passed:bool}>
     */
    public function ladder(Objection $objection): Collection
    {
        $owner = $objection->user;

        if (! $owner) {
            return collect();
        }

        $currentId = (int) $objection->current_handler_id;
        $passed = true;

        return $this->scope->uplineUsers($owner)->values()->map(function (User $user, int $index) use ($currentId, &$passed) {
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
            return $this->fail('الاعتراض ده اتقفل بالفعل.');
        }

        $transaction = $objection->transaction;

        if (! $transaction) {
            return $this->fail('المعاملة الأصليّة مش موجودة.');
        }

        $preview = $this->correctionPreview($objection);

        $correction = $this->ledger->reverse(
            $transaction,
            'معاملة تصحيحيّة بعد قبول اعتراض #'.$objection->id,
            $decider->id,
        );

        $objection->forceFill([
            'status' => 'accepted',
            'decision_note' => $note,
            'correction_transaction_id' => $correction?->id,
            'closed_at' => now(),
        ])->save();

        $this->ledger->notify(
            $objection->user,
            'objection',
            'اتقبل اعتراضك ✓',
            'اتعمل تصحيح بمعاملة عكسيّة — درجتك من '.$preview['from'].' لـ'.$preview['to'].'.',
            route('volunteer.objections', ['objection' => $objection->id]),
        );

        return ['ok' => true, 'message' => 'اتقبل الاعتراض واتسجّلت معاملة تصحيحيّة.', 'objection' => $objection];
    }

    /** رفض الاعتراض بإغلاقه بسبب موثَّق */
    public function reject(Objection $objection, User $decider, string $note): array
    {
        if (! $this->isActive($objection)) {
            return $this->fail('الاعتراض ده اتقفل بالفعل.');
        }

        $objection->forceFill([
            'status' => 'rejected',
            'decision_note' => $note,
            'closed_at' => now(),
        ])->save();

        $this->ledger->notify(
            $objection->user,
            'objection',
            'اتقفل اعتراضك',
            $note,
            route('volunteer.objections', ['objection' => $objection->id]),
        );

        return ['ok' => true, 'message' => 'اتقفل الاعتراض بسببه.', 'objection' => $objection];
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
            'open' => 'مفتوح',
            'in_review' => 'قيد المراجعة',
            'escalated' => 'مُصعَّد',
            'accepted' => 'مقبول',
            'rejected' => 'مرفوض',
            default => $status,
        };
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'objection' => null];
    }
}
