<?php

namespace App\Services\Volunteer\Retention;

use App\Models\BehaviorTransaction;
use App\Models\Escalation;
use App\Models\EscalationStep;
use App\Models\User;
use App\Services\Admin\Volunteer\BehaviorLedger;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\FlowNotifier;
use App\Services\Volunteer\Escalation\HandlerChain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * المخالفة الجسيمة على محرّك التصعيد (13.4-ن-هـ).
 *
 * نصّ الدستور: «الجسيمة (−1) **بموافقة مستوى أعلى — حالة على محرّك التصعيد
 * بنافذة 24 ساعة**». فالحالة ليست خانة `pending_approval` وزرًّا ينتظر إلى
 * الأبد: هي **صفٌّ في `escalations`** على مكتب المستوى الأعلى بنافذةٍ معلومة،
 * و**تسويتها الآليّة عند الفوات = رفض** — لأنّ الصمت لا يُنشئ عقوبة.
 *
 * ولماذا نافذة واحدة (`is_top_level`) لا سلّم صعود؟ لأنّ النصّ يذكر مستوًى
 * أعلى واحدًا ونافذة واحدة، فلا نخترع مستوياتٍ فوقه.
 */
class BehaviorEscalation
{
    public function __construct(private readonly HandlerChain $chain) {}

    /** نافذة القرار — إعداد لا رقم محروق (2.13) */
    public function windowHours(): float
    {
        return (float) setting('workflow.escalation.window_hours', 24);
    }

    /** فتح الحالة على أوّل مستوًى أعلى من المانح داخل عضويّته النشطة */
    public function open(BehaviorTransaction $record, User $granter): Escalation
    {
        $handler = $this->chain->firstHandlerFor($granter);
        $due = now()->addMinutes((int) round($this->windowHours() * 60));

        return DB::transaction(function () use ($record, $granter, $handler, $due) {
            $escalation = Escalation::create([
                'case_type' => CaseCatalog::BEHAVIOR_SEVERE,
                'subject_type' => $record->getMorphClass(),
                'subject_id' => $record->getKey(),
                'requested_by' => $granter->id,
                'current_handler_id' => $handler?->id,
                'level' => 1,
                'window_due_at' => $due,
                // نافذة واحدة ثمّ التسوية الآليّة — لا سلّم صعود فوق المستوى الأعلى
                'is_top_level' => true,
                'status' => 'open',
                'payload' => json_encode([
                    'behavior_transaction_id' => $record->id,
                    'value' => (float) $record->value,
                    'justification' => $record->justification,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            EscalationStep::create([
                'escalation_id' => $escalation->id,
                'handler_id' => $handler?->id,
                'level' => 1,
                'opened_at' => now(),
                'due_at' => $due,
            ]);

            $record->forceFill(['escalation_id' => $escalation->id])->save();

            FlowNotifier::send(
                $handler,
                'escalation',
                strtr(setting('volunteer_offboarding.behavior_escalation.open_1', 'يحتاج قرارك: :p1'), [':p1' => (string) (CaseCatalog::label(CaseCatalog::BEHAVIOR_SEVERE))]),
                setting('volunteer_offboarding.behavior_escalation.open_2', 'فوات النافذة = رفض آليّ، ولا تُطبَّق المخالفة على درجة الالتزام.'),
                route('volunteer.escalations'),
                $due,
                requiresAction: true,
                about: $escalation,
            );

            return $escalation;
        });
    }

    /** حالة المعاملة على المحرّك — أو `null` لو لم تُفتَح لها حالة */
    public function caseOf(BehaviorTransaction $record): ?Escalation
    {
        if (! $record->escalation_id) {
            return null;
        }

        return Escalation::query()->find($record->escalation_id);
    }

    /**
     * أثر القرار — ينادي عليه محرّك التصعيد نفسه، قرارًا صريحًا كان
     * أو تسويةً آليّة بفوات النافذة.
     */
    public function apply(?Model $subject, string $decision, ?User $decider): void
    {
        if (! $subject instanceof BehaviorTransaction) {
            return;
        }

        if ($decision === 'approved') {
            BehaviorLedger::applyPending($subject, $decider);

            return;
        }

        // ⭐ رفضًا صريحًا كان أو تسويةً آليّة: **لا تُطبَّق** على درجة الالتزام
        BehaviorLedger::rejectPending(
            $subject,
            $decider ? setting('volunteer_offboarding.behavior_escalation.apply_1', 'اتّخِذ قرار برفض المخالفة من المستوى الأعلى.')
                : strtr(setting('volunteer_offboarding.behavior_escalation.apply_2', 'فاتت نافذة الاعتماد (:p1 ساعة) — تسوية آليّة بالرفض.'), [':p1' => (string) ($this->windowHours())])
        );
    }
}
