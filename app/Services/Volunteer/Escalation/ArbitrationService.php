<?php

namespace App\Services\Volunteer\Escalation;

use App\Models\Arbitration;
use App\Models\ArbitrationMessage;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * التحكيم (الدستور 23 — القسم 5).
 *
 * ثلاث قواعد لا تُخترَق:
 *  1) **قرار المحكّم نهائيّ ولا يُعاد** — بمبرّر إجباريّ في كلّ الحالات،
 *     و«حفظ القضيّة» لا يمسّ Rep أحدًا.
 *  2) **التواصل بلا كشف الرقم (Masking)** — التزامًا بقاعدة موافقة الإظهار،
 *     ويُكشَف فقط لمن بينه وبين الطرف علاقة أبلاين/داونلاين.
 *  3) **تنازع المصالح يتخطّى المستوى تلقائيًّا** ويُعرَض سبب التخطّي.
 */
class ArbitrationService
{
    public function __construct(private readonly HandlerChain $chain) {}

    // ------------------------------------------------------------------ فتح القضيّة

    /** فتح التحكيم حقّ الطرفين معًا: المالك والمدعوّ */
    public function open(Task $task, ?TaskContribution $contribution, User $opener, array $data): Arbitration
    {
        return DB::transaction(function () use ($task, $contribution, $opener, $data) {
            $arbitration = Arbitration::create([
                'task_id' => $task->id,
                'task_contribution_id' => $contribution?->id,
                'opened_by' => $opener->id,
                'claim' => $data['claim'],
                'attachment_path' => $data['attachment_path'] ?? null,
                'status' => 'open',
                'window_due_at' => now()->addHours((int) setting('workflow.arbitration.window_hours', 24)),
            ]);

            $this->assignArbiter($arbitration, $task);

            // القضيّة نفسها حالة على محرّك التصعيد (الحالة 5) بنافذتها وتسويتها
            app(EscalationEngine::class)->open(
                CaseCatalog::ARBITRATION,
                $arbitration,
                $opener,
                ['task_id' => $task->id, 'contribution_id' => $contribution?->id],
                $arbitration->arbiter_id ? User::query()->find($arbitration->arbiter_id) : null,
            );

            return $arbitration->refresh();
        });
    }

    /**
     * تعيين المحكّم: الأصل أبلاين مالك التاسك — فإن كان بينه وبين أحد الطرفين
     * علاقة أبلاين/داونلاين **يُتخطّى مستواه تلقائيًّا** ويُسجَّل سبب التخطّي.
     */
    public function assignArbiter(Arbitration $arbitration, ?Task $task = null): ?User
    {
        $task ??= Task::query()->find($arbitration->task_id);
        $owner = $task?->owner_id ? User::query()->find($task->owner_id) : null;

        $candidate = $this->chain->firstHandlerFor($owner, $task?->entity_id);
        $contributor = $this->contributorOf($arbitration);
        $skipped = false;
        $guard = 0;

        while ($candidate && $this->hasConflict($candidate, $arbitration, $owner, $contributor) && $guard++ < 10) {
            $skipped = true;
            $candidate = $this->chain->nextHandlerAfter($candidate, $task?->entity_id);
        }

        $arbitration->forceFill([
            'arbiter_id' => $candidate?->id,
            'conflict_of_interest_skipped' => $skipped,
        ])->save();

        return $candidate;
    }

    /** سبب التخطّي يُعرَض للمستخدم صراحةً — لا تخطّي صامت */
    public function skipReason(Arbitration $arbitration): ?string
    {
        return $arbitration->conflict_of_interest_skipped
            ? 'اتخطّى مستوى أقرب لأنّ بينه وبين أحد الطرفين علاقة أبلاين/داونلاين — تنازع مصالح.'
            : null;
    }

    // ------------------------------------------------------------------ الرسائل والتواصل

    /** @return Collection<int, User> */
    public function parties(Arbitration $arbitration, ?Task $task = null): Collection
    {
        $task ??= Task::query()->find($arbitration->task_id);
        $contribution = $arbitration->task_contribution_id
            ? TaskContribution::query()->find($arbitration->task_contribution_id)
            : null;

        return collect([
            $task?->owner_id ? User::query()->find($task->owner_id) : null,
            $contribution?->contributor_id ? User::query()->find($contribution->contributor_id) : null,
            User::query()->find($arbitration->opened_by),
        ])->filter()->unique('id')->values();
    }

    /**
     * رقم الطرف كما يراه هذا المستخدم.
     *
     * @return array{value:string, masked:bool, hint:string}
     */
    public function contactFor(?User $viewer, ?User $party): array
    {
        $phone = (string) ($party?->phone ?? '');

        if ($phone === '') {
            return ['value' => '—', 'masked' => true, 'hint' => 'لا رقم مسجَّل'];
        }

        if ($viewer && $party && $this->chain->relatedByLine($viewer, $party)) {
            return ['value' => $phone, 'masked' => false, 'hint' => 'ظاهر لأنّ بينكما علاقة أبلاين/داونلاين'];
        }

        return [
            'value' => $this->mask($phone),
            'masked' => true,
            'hint' => 'التواصل بلا كشف الرقم — الرسائل تمرّ من داخل المنصّة',
        ];
    }

    /** إخفاء الوسط وإبقاء طرفيه ليتعرّف صاحبه على رقمه بلا كشفه للغير */
    public function mask(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $keep = (int) setting('workflow.arbitration.mask_visible_digits', 2);

        if (mb_strlen($digits) <= $keep * 2) {
            return str_repeat('•', max(mb_strlen($digits), 4));
        }

        return mb_substr($digits, 0, $keep)
            .str_repeat('•', mb_strlen($digits) - ($keep * 2))
            .mb_substr($digits, -$keep);
    }

    public function message(Arbitration $arbitration, User $sender, string $body, ?string $attachment = null): ArbitrationMessage
    {
        if ($arbitration->status !== 'open') {
            throw ValidationException::withMessages([
                'body' => 'الرسائل مقفولة على هذه القضيّة — القرار على مكتب المحكّم.',
            ]);
        }

        return ArbitrationMessage::create([
            'arbitration_id' => $arbitration->id,
            'user_id' => $sender->id,
            'body' => $body,
            'attachment_path' => $attachment,
        ]);
    }

    /** قفل الرسائل — للمحكّم الذي على مكتبه القضيّة الآن وحده */
    public function lockMessages(Arbitration $arbitration, User $arbiter): void
    {
        abort_unless((int) $arbitration->arbiter_id === (int) $arbiter->id, 403, 'قفل الرسائل للمحكّم الحاليّ وحده.');

        $arbitration->forceFill(['status' => 'messages_locked'])->save();
    }

    // ------------------------------------------------------------------ القرار

    /**
     * معاينة أثر القرار قبل تأكيده — «القرار نهائيّ ولا يُعاد».
     *
     * @return array{owner:float, contributor:float, summary:string}
     */
    public function preview(Arbitration $arbitration, string $type, float $ownerAmount = 0, float $contributorAmount = 0): array
    {
        [$owner, $contributor] = $this->amountsFor($arbitration, $type, $ownerAmount, $contributorAmount);

        $summary = match ($type) {
            'shelved' => 'حفظ القضيّة — لا شيء يتمّ، ولا يمسّ Rep أحدًا.',
            'split' => 'قيمة وسط: '.$owner.' VXP للمالك · '.$contributor.' VXP للمساهم.',
            'deduct' => 'خصم: '.$owner.' VXP للمالك · '.$contributor.' VXP للمساهم.',
            default => 'منح: '.$owner.' VXP للمالك · '.$contributor.' VXP للمساهم.',
        };

        return ['owner' => $owner, 'contributor' => $contributor, 'summary' => $summary];
    }

    /**
     * تنفيذ القرار — يُستدعى من محرّك التصعيد وحده (قرار المحكّم أو التسوية
     * الآليّة 50%) ليبقى مسار القرار واحدًا لا مسارين.
     */
    public function applyDecision(Arbitration $arbitration, ?User $arbiter, string $type, array $extra = []): void
    {
        if ($arbitration->status === 'decided') {
            return;
        }

        $justification = trim((string) ($extra['justification'] ?? ''));

        // مبرّر إجباريّ في كلّ الحالات — إلّا التسوية الآليّة فمبرّرها النصّ نفسه
        if ($justification === '') {
            $justification = ! empty($extra['auto'])
                ? 'تسوية آليّة بفوات نافذة السقف: 50% للطرفين.'
                : throw ValidationException::withMessages(['decision_justification' => 'المبرّر إجباريّ في كلّ الحالات.']);
        }

        [$ownerAmount, $contributorAmount] = $this->amountsFor(
            $arbitration,
            $type,
            (float) ($extra['owner_amount'] ?? 0),
            (float) ($extra['contributor_amount'] ?? 0),
        );

        DB::transaction(function () use ($arbitration, $arbiter, $type, $justification, $ownerAmount, $contributorAmount) {
            $arbitration->forceFill([
                'status' => 'decided',
                'decision_type' => $type,
                'owner_amount' => $ownerAmount,
                'contributor_amount' => $contributorAmount,
                'decision_justification' => $justification,
                'arbiter_id' => $arbiter?->id ?? $arbitration->arbiter_id,
                'decided_at' => now(),
            ])->save();

            // «حفظ القضيّة» لا شيء يتمّ ولا يمسّ Rep — فلا حركة أصلًا
            if ($type === 'shelved') {
                return;
            }

            $task = Task::query()->find($arbitration->task_id);
            $contribution = $arbitration->task_contribution_id
                ? TaskContribution::query()->find($arbitration->task_contribution_id)
                : null;

            $this->settle($task?->owner_id ? User::query()->find($task->owner_id) : null, $ownerAmount, $arbitration, $arbiter);
            $this->settle($contribution?->contributor_id ? User::query()->find($contribution->contributor_id) : null, $contributorAmount, $arbitration, $arbiter);
        });

        foreach ($this->parties($arbitration) as $party) {
            FlowNotifier::send(
                $party,
                'arbitration',
                'صدر قرار التحكيم — نهائيّ ولا يُعاد',
                $justification,
                route('volunteer.arbitrations'),
                about: $arbitration,
            );
        }
    }

    /** التسوية الآليّة 50% للطرفين عند فوات نافذة السقف */
    public function settlementShare(Arbitration $arbitration): float
    {
        $percent = (float) setting('workflow.arbitration.settlement_percent', 50);
        $pool = (float) ($arbitration->task_contribution_id
            ? TaskContribution::query()->whereKey($arbitration->task_contribution_id)->value('vxp_value')
            : Task::query()->whereKey($arbitration->task_id)->value('vxp_value'));

        return round($pool * $percent / 100, 2);
    }

    /** فوات نافذة المحكّم = −0.1 Rep (13.4-ن-أ · 23 القسم 5) */
    public function applyArbiterSlowdown(Arbitration $arbitration, ?User $arbiter): void
    {
        if (! $arbiter) {
            return;
        }

        FlowLedger::rep($arbiter, rep_rule('task.slowdown'), 'arbitration.slowdown', $arbitration, 'فوات نافذة التحكيم');
    }

    // ------------------------------------------------------------------ داخليّ

    private function amountsFor(Arbitration $arbitration, string $type, float $owner, float $contributor): array
    {
        return match ($type) {
            'shelved' => [0.0, 0.0],
            'split' => [$this->settlementShare($arbitration), $this->settlementShare($arbitration)],
            'deduct' => [-abs($owner), -abs($contributor)],
            default => [abs($owner), abs($contributor)],
        };
    }

    private function settle(?User $user, float $amount, Arbitration $arbitration, ?User $arbiter): void
    {
        if (! $user || $amount == 0.0) {
            return;
        }

        // خصم VXP لا يقع إلّا بقرار محكّم موثَّق — ولا خصم آليّ إطلاقًا (23 القسم 5)
        $amount > 0
            ? FlowLedger::creditVxp($user, $amount, 'arbitration.award', $arbitration, 'قرار تحكيم', $arbiter?->id)
            : FlowLedger::debitVxp($user, abs($amount), 'arbitration.deduct', $arbitration, 'قرار تحكيم', $arbiter?->id ?? $user->id);
    }

    public function contributorOf(Arbitration $arbitration): ?User
    {
        $contributorId = $arbitration->task_contribution_id
            ? TaskContribution::query()->whereKey($arbitration->task_contribution_id)->value('contributor_id')
            : null;

        return $contributorId ? User::query()->find($contributorId) : null;
    }

    /**
     * تنازع المصالح: المحكّم لا يكون طرفًا («ليس نفسه»)، ولا يكون بينه وبين
     * **المساهم** علاقة أبلاين/داونلاين — أمّا كونه أبلاين المالك فهو أصل
     * التعيين لا تنازعًا (المراجِع أبلاين المالك بنصّ القسم 4).
     */
    private function hasConflict(User $candidate, Arbitration $arbitration, ?User $owner, ?User $contributor): bool
    {
        $partyIds = array_filter([
            $owner?->id,
            $contributor?->id,
            $arbitration->opened_by,
        ]);

        if (in_array((int) $candidate->id, array_map('intval', $partyIds), true)) {
            return true;
        }

        return $contributor !== null && $this->chain->relatedByLine($candidate, $contributor);
    }
}
