<?php

namespace App\Services\Volunteer\Escalation;

use App\Models\Arbitration;
use App\Models\Escalation;
use App\Models\EscalationStep;
use App\Models\Task;
use App\Models\TaskBlock;
use App\Models\TaskContribution;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Org\AbsenceService;
use App\Services\Volunteer\Retention\BehaviorEscalation;
use App\Services\Wallet\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * محرّك التصعيد — قلب دورة العمل (الدستور 23 — القسم 5).
 *
 * القاعدة الواحدة التي يقوم عليها كلّ شيء:
 *  «أيّ حالة محتاجة قرار ⟵ صاحب القرار الأوّل هو المراجِع، وعنده 24 ساعة.
 *   فاتت ⟵ ترتفع للأبلاين الأعلى ويأخذ المفوِّت أثر التباطؤ.
 *   حتى السقف بنافذة 48 ⟵ فاتت ⟵ التسوية الآليّة لنوع الحالة.»
 *
 * وحده «مسار عدم التسليم» يصعد **بلا خصم تباطؤ**، لأنّه ليس قرارًا متأخّرًا
 * على مماطل بل مهمّة يتيمة تدور على مالك جديد.
 */
class EscalationEngine
{
    public function __construct(private readonly HandlerChain $chain) {}

    // ------------------------------------------------------------------ فتح الحالة

    /**
     * فتح حالة على مكتب أوّل صاحب قرار.
     *
     * @param  array<string, mixed>  $payload  تفاصيل الطلب (تاريخ مقترَح · رابط · سبب…)
     */
    public function open(string $caseType, Model $subject, ?User $requestedBy, array $payload = [], ?User $handler = null): Escalation
    {
        abort_unless(CaseCatalog::exists($caseType), 422, 'نوع حالة غير معروف: '.$caseType);

        $entityId = $this->entityIdOf($subject);
        $handler ??= $this->chain->firstHandlerFor($requestedBy, $entityId);
        $isTop = $this->chain->isTop($handler, $entityId);

        return DB::transaction(function () use ($caseType, $subject, $requestedBy, $payload, $handler, $isTop) {
            $due = $this->windowEnd($isTop);

            $escalation = Escalation::create([
                'case_type' => $caseType,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'requested_by' => $requestedBy?->id,
                'current_handler_id' => $handler?->id,
                'level' => 1,
                'window_due_at' => $due,
                'is_top_level' => $isTop,
                'status' => 'open',
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            $this->openStep($escalation, $handler, 1, $due);
            $this->notifyHandler($escalation, $handler);

            return $escalation;
        });
    }

    // ------------------------------------------------------------------ القرار

    /**
     * قرار صريح من صاحب النافذة الحاليّ.
     *
     * @param  array<string, mixed>  $extra  حمولة القرار (قيمة Rep يدويّة · تاريخ جديد…)
     */
    public function decide(Escalation $escalation, ?User $decider, string $decision, ?string $note = null, array $extra = []): Escalation
    {
        abort_if($escalation->status !== 'open', 422, 'الحالة محسومة بالفعل — ولا تُعاد.');

        return DB::transaction(function () use ($escalation, $decider, $decision, $note, $extra) {
            $this->applyEffect($escalation, $decision, $decider, $extra);

            $escalation->forceFill([
                'status' => 'decided',
                'decision' => $decision,
                'decision_note' => $note,
                'decided_at' => now(),
            ])->save();

            EscalationStep::query()
                ->where('escalation_id', $escalation->id)
                ->whereNull('closed_at')
                ->update(['closed_at' => now(), 'outcome' => 'decided', 'updated_at' => now()]);

            FlowNotifier::send(
                $this->requesterOf($escalation),
                'escalation',
                'اتّخِذ قرار في: '.CaseCatalog::label($escalation->case_type),
                $note,
                route('volunteer.escalations'),
                about: $escalation,
            );

            return $escalation->refresh();
        });
    }

    // ------------------------------------------------------------------ التصعيد

    /**
     * فاتت النافذة ⟵ ترتفع للأبلاين، ويُسجَّل أثر التباطؤ على المفوِّت
     * (إلّا في مسار عدم التسليم).
     */
    public function escalate(Escalation $escalation): Escalation
    {
        return DB::transaction(function () use ($escalation) {
            $missed = $escalation->current_handler_id
                ? User::query()->find($escalation->current_handler_id)
                : null;

            if ($missed && ! CaseCatalog::skipsSlowdown($escalation->case_type) && ! $escalation->slowdown_penalty_applied) {
                // الغائب المعذور لا يقع عليه أثر تباطؤ إطلاقًا (23-6 — وضع «غائب»)
                if (! app(AbsenceService::class)->isAbsent($missed)) {
                    CaseCatalog::suspendsSlowdown($escalation->case_type)
                        // بلاغ الرابط: قيمة معلَّقة تُعتمَد أو تُشال بعد التحقّق (23-5)
                        ? $this->suspendSlowdown($escalation, $missed, rep_rule('task.slowdown'))
                        : FlowLedger::rep(
                            $missed,
                            rep_rule('task.slowdown'),
                            'escalation.slowdown',
                            $escalation,
                            'فوات نافذة القرار في: '.CaseCatalog::label($escalation->case_type),
                        );
                }

                $escalation->slowdown_penalty_applied = true;
            }

            EscalationStep::query()
                ->where('escalation_id', $escalation->id)
                ->whereNull('closed_at')
                ->update(['closed_at' => now(), 'outcome' => 'timed_out', 'updated_at' => now()]);

            $entityId = $this->entityIdOf($this->subjectOf($escalation));
            $next = $this->chain->nextHandlerAfter($missed, $entityId);
            $isTop = $next === null ? true : $this->chain->isTop($next, $entityId);

            // انقطعت السلسلة؟ يبقى الأمر على مكتب صاحبه لكن بنافذة السقف ثمّ التسوية
            $handler = $next ?? $missed;
            $due = $this->windowEnd($isTop);

            $escalation->forceFill([
                'current_handler_id' => $handler?->id,
                'level' => $escalation->level + 1,
                'is_top_level' => $isTop,
                'window_due_at' => $due,
                /*
                 | ⭐ مستوًى جديد = نافذة جديدة = صاحبٌ جديد: و«اللي فوّتها ياخد أثر
                 | التباطؤ» بلا تخصيص (23-5). فالعَلَم يُصفَّر مع كلّ صعود، ولا يبقى
                 | مرفوعًا إلّا حين تنقطع السلسلة فيظلّ الأمر على مكتب صاحبه نفسه.
                 */
                'slowdown_penalty_applied' => $handler && $missed && (int) $handler->id === (int) $missed->id
                    ? $escalation->slowdown_penalty_applied
                    : false,
            ])->save();

            $this->openStep($escalation, $handler, (int) $escalation->level, $due);
            $this->notifyHandler($escalation, $handler, escalated: true);

            return $escalation->refresh();
        });
    }

    // ------------------------------------------------------------------ التسوية الآليّة

    /** فوات نافذة السقف ⟵ التسوية المنصوصة لنوع الحالة، بلا تدخّل بشريّ */
    public function autoSettle(Escalation $escalation): Escalation
    {
        $settlement = CaseCatalog::settlement($escalation->case_type);

        abort_if($settlement === null, 422, 'لا تسوية آليّة لهذا النوع.');

        return DB::transaction(function () use ($escalation, $settlement) {
            $this->applyEffect($escalation, $settlement, null, ['auto' => true]);

            $escalation->forceFill([
                'status' => 'auto_settled',
                'decision' => $settlement,
                'decision_note' => 'تسوية آليّة بفوات نافذة السقف: '.CaseCatalog::settlementLabel($escalation->case_type),
                'auto_settled' => true,
                'decided_at' => now(),
            ])->save();

            EscalationStep::query()
                ->where('escalation_id', $escalation->id)
                ->whereNull('closed_at')
                ->update(['closed_at' => now(), 'outcome' => 'timed_out', 'updated_at' => now()]);

            foreach (array_filter([$this->requesterOf($escalation), $this->handlerOf($escalation)]) as $user) {
                FlowNotifier::send(
                    $user,
                    'escalation',
                    'تسوية آليّة: '.CaseCatalog::label($escalation->case_type),
                    CaseCatalog::settlementLabel($escalation->case_type),
                    route('volunteer.escalations'),
                    about: $escalation,
                );
            }

            return $escalation->refresh();
        });
    }

    // ------------------------------------------------------------------ التشغيل الدوريّ

    /**
     * معالجة كلّ النوافذ الفائتة دفعةً — يستدعيها أمر `escalations:run`.
     *
     * ⭐ **حالةٌ واحدة تالفة لا تُسقِط الدورة كلّها.** المحرّك يعمل كلّ خمس دقائق
     * على المنصّة بأسرها: التسويات التسع · الاعتماد التلقائيّ للمساهمين · خصم
     * نقاط التفتيش · الديدلاينات الداخليّة. فصفٌّ بنوعٍ لا يعرفه `CaseCatalog`
     * — أو أيّ استثناء آخر — يُعزَل ويُسجَّل ويُنبَّه عليه، **ويكمل الباقي**.
     *
     * @return array{escalated:int, settled:int, failed:int}
     */
    public function run(): array
    {
        $result = ['escalated' => 0, 'settled' => 0, 'failed' => 0];

        Escalation::query()
            ->where('status', 'open')
            ->where('window_due_at', '<=', now())
            ->orderBy('window_due_at')
            ->get()
            ->each(function (Escalation $escalation) use (&$result) {
                try {
                    if (! CaseCatalog::exists($escalation->case_type)) {
                        $this->quarantine($escalation, 'نوع حالة غير معروف: '.$escalation->case_type);
                        $result['failed']++;

                        return;
                    }

                    if ($escalation->is_top_level) {
                        $this->autoSettle($escalation);
                        $result['settled']++;

                        return;
                    }

                    $this->escalate($escalation);
                    $result['escalated']++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->quarantine($escalation, 'تعذّرت المعالجة: '.$exception->getMessage());
                    $result['failed']++;
                }
            });

        return $result;
    }

    /**
     * عزل صفٍّ تالف: يخرج من الطابور بحالة `failed` بسببٍ مكتوب، ويُنبَّه عليه
     * صاحبه ومَن طلبه — فلا يبقى عالقًا يعيد إسقاط الدورة كلّ خمس دقائق،
     * ولا يختفي بصمت.
     */
    private function quarantine(Escalation $escalation, string $reason): void
    {
        try {
            Log::error('محرّك التصعيد: عزل حالة تالفة', [
                'escalation_id' => $escalation->id,
                'case_type' => $escalation->case_type,
                'reason' => $reason,
            ]);

            $escalation->forceFill([
                'status' => 'failed',
                'decision_note' => $reason,
            ])->save();

            EscalationStep::query()
                ->where('escalation_id', $escalation->id)
                ->whereNull('closed_at')
                ->update(['closed_at' => now(), 'outcome' => 'failed', 'updated_at' => now()]);

            foreach (array_filter([$this->handlerOf($escalation), $this->requesterOf($escalation)]) as $user) {
                FlowNotifier::send(
                    $user,
                    'escalation',
                    'حالة اتوقفت وعايزة مراجعة يدويّة',
                    $reason.' — كلّم الأدمن عشان يراجعها.',
                    route('volunteer.escalations'),
                    about: $escalation,
                );
            }
        } catch (Throwable $exception) {
            // العزل نفسه لا يجوز أن يُسقِط الدورة — يكفي أن يُسجَّل
            report($exception);
        }
    }

    // ------------------------------------------------------------------ استعلامات الشاشة

    /** طابور «يحتاج قرارك» — داخل عضويّة صاحبه فقط، مرتّبًا بالإلحاح */
    public function deskOf(User $user): Builder
    {
        return Escalation::query()
            ->where('status', 'open')
            ->where('current_handler_id', $user->id)
            ->orderBy('window_due_at');
    }

    /** ثلاثة عدّادات ملوّنة: داخل النافذة · اقتربت · فاتت (2.16) */
    public function counters(User $user): array
    {
        $rows = $this->deskOf($user)->get(['window_due_at']);

        return [
            'ok' => $rows->filter(fn ($e) => $this->windowState($e->window_due_at) === 'ok')->count(),
            'warn' => $rows->filter(fn ($e) => $this->windowState($e->window_due_at) === 'warn')->count(),
            'danger' => $rows->filter(fn ($e) => $this->windowState($e->window_due_at) === 'danger')->count(),
        ];
    }

    /** حالة العدّاد بلونها ورمزها — فات = خطر · قرب = انتبه · متّسع = سليم */
    public function windowState(mixed $dueAt): string
    {
        if (! $dueAt) {
            return 'idle';
        }

        $due = CarbonImmutable::parse($dueAt);

        if ($due->isPast()) {
            return 'danger';
        }

        $soon = (float) setting('workflow.escalation.soon_hours', 6);

        return now()->diffInHours($due, absolute: true) <= $soon ? 'warn' : 'ok';
    }

    /** @return Collection<int, Escalation> */
    public function openFor(Model $subject, ?string $caseType = null): Collection
    {
        return Escalation::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->when($caseType, fn ($q) => $q->where('case_type', $caseType))
            ->where('status', 'open')
            ->get();
    }

    public function payload(Escalation $escalation): array
    {
        $payload = $escalation->payload;

        if (is_array($payload)) {
            return $payload;
        }

        return json_decode((string) $payload, true) ?: [];
    }

    public function subjectOf(Escalation $escalation): ?Model
    {
        $class = Model::getActualClassNameForMorph($escalation->subject_type);

        return class_exists($class) ? $class::query()->find($escalation->subject_id) : null;
    }

    public function handlerOf(Escalation $escalation): ?User
    {
        return $escalation->current_handler_id ? User::query()->find($escalation->current_handler_id) : null;
    }

    public function requesterOf(Escalation $escalation): ?User
    {
        return $escalation->requested_by ? User::query()->find($escalation->requested_by) : null;
    }

    /** نافذة هذا المستوى: 24 ساعة، و48 عند السقف — كلاهما إعداد (2.13) */
    public function windowEnd(bool $isTop): CarbonImmutable
    {
        $hours = $isTop
            ? (float) setting('workflow.escalation.top_window_hours', 48)
            : (float) setting('workflow.escalation.window_hours', 24);

        return CarbonImmutable::parse(now())->addMinutes((int) round($hours * 60));
    }

    // ------------------------------------------------------------------ آثار القرارات

    /**
     * أثر كلّ قرار على سجلّه الأصليّ — المكان الوحيد الذي تتغيّر فيه الحالة،
     * فلا يتفرّق المنطق بين تسعة كنترولرات.
     */
    private function applyEffect(Escalation $escalation, string $decision, ?User $decider, array $extra): void
    {
        $subject = $this->subjectOf($escalation);
        $payload = $this->payload($escalation);

        match ($escalation->case_type) {
            CaseCatalog::EXTENSION => $this->applyExtension($subject, $decision, $payload),
            CaseCatalog::BLOCKED => $this->applyBlocked($subject, $decision, $decider),
            CaseCatalog::APOLOGY => $this->applyApology($subject, $decision, $escalation),
            CaseCatalog::NO_DELIVERY => $this->applyNoDelivery($subject, $decision, $decider),
            CaseCatalog::ARBITRATION => $this->applyArbitration($subject, $decision, $decider, $extra, $escalation),
            CaseCatalog::CONTRIBUTOR_WITHDRAW => $this->applyWithdraw($subject, $decision, $escalation),
            CaseCatalog::BROKEN_LINK => $this->applyBrokenLink($escalation, $decision),
            CaseCatalog::REPEATED_RETURN => $this->applyRepeatedReturn($subject, $decision, $extra, $escalation),
            CaseCatalog::SUBTASK_BATCH => $this->applySubtaskBatch($subject, $decision, $extra),
            // أثر المخالفة الجسيمة يعيش في مجاله — والمحرّك ينادي عليه (13.4-ن-هـ)
            CaseCatalog::BEHAVIOR_SEVERE => app(BehaviorEscalation::class)->apply($subject, $decision, $decider),
            default => null,
        };
    }

    /** 1) التمديد: الموافقة تنقل الديدلاين للتاريخ المقترَح والقديم يبقى في السجلّ */
    private function applyExtension(?Model $subject, string $decision, array $payload): void
    {
        if (! $subject instanceof Task || $decision !== 'approved') {
            return;
        }

        if (! empty($payload['new_deadline'])) {
            $subject->deadline_at = CarbonImmutable::parse($payload['new_deadline']);
        }

        $subject->extension_count = (int) $subject->extension_count + 1;
        $subject->save();
    }

    /** 2) التعثّر: الموافقة توقف الديدلاين، والرفض يعيد المهمّة قيد التنفيذ */
    private function applyBlocked(?Model $subject, string $decision, ?User $decider): void
    {
        $block = $subject instanceof TaskBlock ? $subject : null;
        $task = $block ? Task::query()->find($block->task_id) : ($subject instanceof Task ? $subject : null);

        if ($block) {
            $block->forceFill([
                'status' => $decision === 'approved' ? 'approved' : 'rejected',
                'approved_by' => $decider?->id,
                'resume_at' => $decision === 'approved' && $block->days
                    ? now()->addDays((int) $block->days)
                    : $block->resume_at,
            ])->save();
        }

        $task?->forceFill(['status' => $decision === 'approved' ? 'blocked' : 'in_progress'])->save();
    }

    /**
     * 3) الاعتذار: مقبول ⟵ −0.5 بدلًا من −0.75 · مرفوض ⟵ سلّم عدم التسليم كاملًا.
     * (13.4-ن-أ — ولا خصم مزدوج: قيمة واحدة لواقعة واحدة.)
     */
    private function applyApology(?Model $subject, string $decision, Escalation $escalation): void
    {
        if (! $subject instanceof Task) {
            return;
        }

        $owner = $subject->owner_id ? User::query()->find($subject->owner_id) : null;

        if (! $owner) {
            return;
        }

        $value = $decision === 'accepted'
            ? rep_rule('task.apology_accepted')
            : rep_rule('task.no_delivery');

        FlowLedger::rep($owner, $value, 'task.apology', $escalation, $decision === 'accepted' ? 'اعتذار مقبول' : 'اعتذار مرفوض — عدم تسليم');

        $subject->forceFill(['status' => 'no_delivery'])->save();
    }

    /** 4) مسار عدم التسليم: المهمّة اليتيمة تدور على مالك جديد — أو تُغلَق */
    private function applyNoDelivery(?Model $subject, string $decision, ?User $decider): void
    {
        if (! $subject instanceof Task) {
            return;
        }

        match ($decision) {
            // ينفّذها بنفسه: صار هو المالك ويراجعها أبلاينه هو
            'self_execute' => $subject->forceFill([
                'owner_id' => $decider?->id ?? $subject->owner_id,
                'reviewer_id' => $decider ? $this->chain->firstHandlerFor($decider, $subject->entity_id)?->id : $subject->reviewer_id,
                'status' => 'in_progress',
            ])->save(),
            // يفكّكها ويوزّعها: تعود قيد التنفيذ على مكتبه ليصنع منها صب-تاسكات
            'redistribute' => $subject->forceFill([
                'owner_id' => $decider?->id ?? $subject->owner_id,
                'status' => 'in_progress',
            ])->save(),
            // تُغلَق: تُستبعَد من مقام النِّسَب وتُوسَم «مُغلَقة» صراحةً (23 — القسم 5)
            'closed' => $this->closeTask($subject),
            default => null,
        };
    }

    /** 5) التحكيم: القرار نهائيّ — والتسوية الآليّة 50% للطرفين */
    private function applyArbitration(?Model $subject, string $decision, ?User $decider, array $extra, Escalation $escalation): void
    {
        if (! $subject instanceof Arbitration) {
            return;
        }

        app(ArbitrationService::class)->applyDecision(
            $subject,
            $decider,
            $decision,
            $extra + ['justification' => $escalation->decision_note],
        );
    }

    /** 6) سحب المساهم: بلا أثر على درجة أيّ طرف — والمعلَّق يعود لصاحبه */
    private function applyWithdraw(?Model $subject, string $decision, Escalation $escalation): void
    {
        if (! $subject instanceof TaskContribution || $decision !== 'approved') {
            return;
        }

        $owner = User::query()->find($subject->invited_by);
        $held = (float) $subject->held_amount;

        if ($owner && $held > 0 && $subject->vxp_source === 'owner_balance') {
            FlowLedger::creditVxp($owner, $held, 'contribution.hold_released', $escalation, 'تحرير الرصيد المعلَّق بعد سحب المساهمة', $owner->id);
        }

        $subject->forceFill(['status' => 'withdrawn', 'held_amount' => 0])->save();
    }

    /**
     * 7) بلاغ الرابط: خصم التباطؤ فيه **قيمة معلَّقة قابلة للاسترجاع** (23-5).
     *  - «الرابط يعمل» ⟵ المعلَّق **يتشال عن الكلّ**، وما وقع فعلًا (صفوف قديمة)
     *    يُردّ بمعاملة عكسيّة موثّقة — لا تعديل للأصل.
     *  - «معطّل فعلًا» ⟵ المعلَّق **يُعتمَد في سجلّ معاملاتهم**، ومعه مَن أضاف التسجيل.
     */
    private function applyBrokenLink(Escalation $escalation, string $decision): void
    {
        $payload = $this->payload($escalation);
        $suspended = array_values((array) ($payload['suspended_slowdown'] ?? []));

        foreach ($suspended as $row) {
            $user = User::query()->find($row['user_id'] ?? null);

            if (! $user) {
                continue;
            }

            $decision === 'link_broken'
                ? FlowLedger::rep(
                    $user,
                    (float) ($row['value'] ?? rep_rule('task.slowdown')),
                    'escalation.slowdown',
                    $escalation,
                    'اعتماد أثر التباطؤ المعلَّق بعد تأكيد بلاغ الرابط',
                )
                : FlowNotifier::send(
                    $user,
                    'escalation',
                    'اترجّع لك الخصم المعلَّق ✓',
                    'الرابط اتأكّد إنّه شغّال — فمفيش أثر تباطؤ عليك.',
                    route('volunteer.escalations'),
                    about: $escalation,
                );
        }

        $payload['suspended_slowdown'] = [];
        $escalation->payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $escalation->save();

        if ($decision !== 'link_broken') {
            $this->refundSlowdown($escalation);

            return;
        }

        $responsible = ! empty($payload['responsible_id']) ? User::query()->find($payload['responsible_id']) : null;

        if ($responsible) {
            FlowLedger::rep($responsible, rep_rule('task.slowdown'), 'link.broken_confirmed', $escalation, 'تأكيد بلاغ رابط معطّل');
        }
    }

    /** ردّ ما وقع فعلًا من خصم تباطؤ على هذه الحالة — بمعاملة عكسيّة لا بحذف */
    private function refundSlowdown(Escalation $escalation): void
    {
        if (! FlowLedger::available()) {
            return;
        }

        // ما رُدّ مرّةً لا يُردّ ثانية — والأصل يبقى في السجلّ كما هو
        $alreadyReversed = Transaction::query()
            ->whereNotNull('corrects_transaction_id')
            ->pluck('corrects_transaction_id')
            ->all();

        Transaction::query()
            ->where('reference_type', $escalation->getMorphClass())
            ->where('reference_id', $escalation->getKey())
            ->where('source', 'escalation.slowdown')
            ->where('is_correction', false)
            ->whereNotIn('id', $alreadyReversed ?: [0])
            ->get()
            ->each(fn (Transaction $transaction) => app(LedgerService::class)->reverse(
                $transaction,
                'ردّ أثر التباطؤ المعلَّق — الرابط شغّال (23-5)',
            ));
    }

    /** أثر تباطؤ معلَّق: يُسجَّل في حمولة الحالة ولا يمسّ الدفتر حتى التحقّق */
    private function suspendSlowdown(Escalation $escalation, User $missed, float $value): void
    {
        $payload = $this->payload($escalation);
        $payload['suspended_slowdown'][] = ['user_id' => $missed->id, 'value' => $value];

        $escalation->payload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        FlowNotifier::send(
            $missed,
            'escalation',
            'أثر تباطؤ معلَّق على بلاغ رابط',
            'القيمة معلَّقة لحدّ ما حد يتحقّق من الرابط — لو شغّال هتترفع عنك.',
            route('volunteer.escalations'),
            about: $escalation,
        );
    }

    /** 8) الإرجاع المتكرّر: إرجاع/اعتماد/إنهاء بقيمة Rep يدويّة بمبرّر */
    private function applyRepeatedReturn(?Model $subject, string $decision, array $extra, Escalation $escalation): void
    {
        if (! $subject instanceof Task) {
            return;
        }

        if ($decision === 'returned') {
            $subject->forceFill(['status' => 'returned'])->save();

            return;
        }

        // «اعتماد بلا Rep» هو نصّ التسوية الآليّة — اعتماد بلا أيّ حركة درجة
        $subject->forceFill(['status' => 'approved', 'approved_at' => now()])->save();

        if ($decision !== 'finished') {
            return;
        }

        $value = (float) ($extra['rep_value'] ?? 0);
        $min = (float) setting('workflow.repeated_return.rep_min', -0.5);
        $max = (float) setting('workflow.repeated_return.rep_max', 0.25);
        $value = max($min, min($max, $value));

        $owner = $subject->owner_id ? User::query()->find($subject->owner_id) : null;

        if ($owner && $value != 0.0) {
            FlowLedger::rep($owner, $value, 'task.repeated_return', $escalation, $extra['justification'] ?? 'قرار الإرجاع المتكرّر');
        }
    }

    /** 9) دفعة الصب-تاسكات: التسوية الآليّة اعتماد الدفعة كاملة */
    private function applySubtaskBatch(?Model $subject, string $decision, array $extra): void
    {
        if (! $subject instanceof Task) {
            return;
        }

        $children = Task::query()->where('parent_task_id', $subject->id);

        match ($decision) {
            'approved' => $children->update(['batch_status' => 'approved', 'updated_at' => now()]),
            'item_deleted' => Task::query()
                ->whereIn('id', array_map('intval', (array) ($extra['deleted_ids'] ?? [])))
                ->update(['batch_status' => 'draft', 'updated_at' => now()]),
            default => null,
        };

        if ($decision === 'approved') {
            $subject->forceFill(['batch_status' => 'approved'])->save();
        }
    }

    // ------------------------------------------------------------------ أدوات

    private function closeTask(Task $task): void
    {
        $task->forceFill(['status' => 'closed'])->save();

        // التبعيّة المكسورة تتحرّر آليًّا فلا تبقى معلّقة للأبد (23 — 3.4)
        Task::query()
            ->where('blocked_by_task_id', $task->id)
            ->update(['blocked_by_task_id' => null, 'status' => 'in_progress', 'updated_at' => now()]);
    }

    private function openStep(Escalation $escalation, ?User $handler, int $level, mixed $due): void
    {
        EscalationStep::create([
            'escalation_id' => $escalation->id,
            'handler_id' => $handler?->id,
            'level' => $level,
            'opened_at' => now(),
            'due_at' => $due,
        ]);
    }

    private function notifyHandler(Escalation $escalation, ?User $handler, bool $escalated = false): void
    {
        FlowNotifier::send(
            $handler,
            'escalation',
            ($escalated ? 'صعدت إليك: ' : 'يحتاج قرارك: ').CaseCatalog::label($escalation->case_type),
            'فوات نافذتك يرفع الحالة لأبلاينك وعليك أثر التباطؤ.',
            route('volunteer.escalations'),
            $escalation->window_due_at,
            requiresAction: true,
            about: $escalation,
        );
    }

    private function entityIdOf(?Model $subject): ?int
    {
        if (! $subject) {
            return null;
        }

        if ($subject instanceof Task) {
            return $subject->entity_id;
        }

        if ($subject instanceof TaskContribution || $subject instanceof Arbitration) {
            return Task::query()->whereKey($subject->task_id)->value('entity_id');
        }

        if ($subject instanceof TaskBlock) {
            return Task::query()->whereKey($subject->task_id)->value('entity_id');
        }

        return null;
    }
}
