<?php

namespace App\Services\Gamification\Wars;

use App\Models\Challenge;
use App\Models\WarQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قمع الأسئلة الموحّد (15.0 · 15.2-8).
 *
 * **70% من بنك أسئلة الحروب + 30% من أسئلة التدريبات العامّة** — عشوائيًّا،
 * «والعشوائيّة هي العدل». والسحب يحدث **مرّة واحدة لكلّ مواجهة** فتكون
 * الأسئلة **نفسها للطرفين**.
 *
 * استثناء حرب التقدير (15.6): الأسئلة التي **إجاباتها أرقام بالكامل** فقط.
 */
class WarQuestionFunnel
{
    public function __construct(private readonly WarRules $rules) {}

    /**
     * سحب جولة كاملة.
     *
     * @return list<array{kind:string,text:string,options:array,answer:mixed,tolerance:float|null,unit:string|null,ref:string}>
     */
    public function draw(Challenge $challenge, ?int $count = null): array
    {
        $type = $this->rules->typeOf($challenge);
        $numericOnly = $type === 'estimation';
        $count = $count ?? $this->rules->questionCount($type);

        $arenaShare = (int) round($count * $this->rules->arenaRatio($challenge) / 100);
        $arenaShare = max(0, min($count, $arenaShare));

        $arena = $this->fromBank('arena', $numericOnly, $arenaShare);
        $training = $this->fromTraining($numericOnly, $count - $arena->count());

        // العجز في أحد القمعين يُسَدّ من الآخر حتى لا تنقص الجولة عن عددها
        $items = $arena->concat($training);

        if ($items->count() < $count) {
            $items = $items->concat(
                $this->fromBank('arena', $numericOnly, $count - $items->count(), $items->pluck('ref')->all()),
            );
        }

        if ($items->count() < $count) {
            $items = $items->concat(
                $this->fromBank('training', $numericOnly, $count - $items->count(), $items->pluck('ref')->all()),
            );
        }

        return $items->shuffle()->take($count)->values()->all();
    }

    /** هل البنك يكفي لتشغيل هذه الحرب؟ (24.2 — «البنك فارغ ⟵ الحروب لن تعمل») */
    public function isBankReady(Challenge $challenge): bool
    {
        return $this->activeCount($this->rules->typeOf($challenge) === 'estimation')
            >= $this->rules->minActiveQuestions();
    }

    public function activeCount(bool $numericOnly = false): int
    {
        return WarQuestion::query()
            ->where('status', 'active')
            ->when($numericOnly, fn ($q) => $q->where('is_numeric', true))
            ->count();
    }

    /** بنود بلا إجابات — ما يُرسَل للمتصفح وحده (15.2-3) */
    public function publicItems(array $items): array
    {
        $public = [];

        foreach (array_values($items) as $i => $item) {
            $public[] = [
                'i' => $i,
                'kind' => $item['kind'] ?? 'mcq',
                'text' => $item['text'] ?? '',
                'options' => array_values($item['options'] ?? []),
                'unit' => $item['unit'] ?? null,
            ];
        }

        return $public;
    }

    // ------------------------------------------------------------------ داخليّ

    /** @param list<string> $exclude */
    private function fromBank(string $source, bool $numericOnly, int $limit, array $exclude = []): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $rows = WarQuestion::query()
            ->where('status', 'active')
            ->where('source', $source)
            ->when($numericOnly, fn ($q) => $q->where('is_numeric', true))
            ->inRandomOrder()
            ->limit($limit + count($exclude))
            ->get();

        return $rows
            ->map(fn (WarQuestion $q) => $this->normalizeBankRow($q))
            ->reject(fn (array $item) => in_array($item['ref'], $exclude, true))
            ->take($limit)
            ->values();
    }

    /**
     * أسئلة التدريبات العامّة (30%).
     * تُقرأ من أسئلة الدروس المعلَّمة «عامّة» — ومن قسم `training` في البنك،
     * فلو لم يوجد جدول الدروس بعد لا تتعطّل الحرب.
     */
    private function fromTraining(bool $numericOnly, int $limit): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $bank = $this->fromBank('training', $numericOnly, $limit);

        if ($bank->count() >= $limit || ! Schema::hasTable('lesson_questions')) {
            return $bank;
        }

        $need = $limit - $bank->count();

        $rows = DB::table('lesson_questions')
            ->where('is_general', true)
            ->whereNotNull('correct_answer')
            ->inRandomOrder()
            // نسحب أكثر من المطلوب لأنّ ترشيح «الرقميّة بالكامل» يتمّ في PHP
            ->limit($numericOnly ? $need * 5 + 20 : $need)
            ->get(['id', 'prompt', 'options', 'correct_answer'])
            ->when(
                $numericOnly,
                fn (Collection $rows) => $rows->filter(fn ($row) => is_numeric(trim((string) $row->correct_answer))),
            )
            ->take($need);

        return $bank->concat($rows->map(function ($row) {
            $options = json_decode((string) $row->options, true) ?: [];
            $numeric = is_numeric(trim((string) $row->correct_answer));

            return [
                'kind' => $numeric && ! $options ? 'number' : 'mcq',
                'text' => (string) $row->prompt,
                'options' => array_values($options),
                'answer' => trim((string) $row->correct_answer),
                'tolerance' => null,
                'unit' => null,
                'ref' => 'lesson:'.$row->id,
            ];
        }))->values();
    }

    private function normalizeBankRow(WarQuestion $q): array
    {
        return [
            'kind' => $q->is_numeric && ! $q->options ? 'number' : 'mcq',
            'text' => (string) $q->text,
            'options' => array_values((array) ($q->options ?? [])),
            'answer' => (string) $q->answer,
            'tolerance' => $q->tolerance !== null ? (float) $q->tolerance : null,
            'unit' => $q->unit,
            'ref' => 'war:'.$q->id,
        ];
    }
}
