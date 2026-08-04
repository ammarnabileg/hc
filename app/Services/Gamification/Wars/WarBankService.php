<?php

namespace App\Services\Gamification\Wars;

use App\Models\User;
use App\Models\WarQuestion;
use App\Services\Admin\Volunteer\AuditTrail;

/**
 * بنك أسئلة الحروب (12.10-ب · 24.2).
 *
 * ⭐ قفل معلَن: **التصحيح Server-side والإجابة لا تُرسَل للمتصفح** — ولذلك
 * الكشف عن الإجابة في الشاشة **مؤقّت ومسجَّل في Audit**.
 */
class WarBankService
{
    public const DIFFICULTIES = ['easy' => 'سهل', 'medium' => 'متوسّط', 'hard' => 'صعب'];

    public const SOURCES = ['arena' => 'ساحة', 'training' => 'تدريبات'];

    public const STATUSES = ['active' => 'مفعّل', 'draft' => 'مسودّة', 'archived' => 'مؤرشف'];

    /** حفظ سؤال (إضافة أو تعديل) — و«رقميّ» تُشتَقّ من الإجابة لا من إدخال يدويّ */
    public function save(?WarQuestion $question, array $data, ?User $actor = null): WarQuestion
    {
        $question ??= new WarQuestion;
        $old = $question->exists ? $question->only(['text', 'difficulty', 'status', 'source']) : [];

        $options = array_values(array_filter(
            array_map('trim', (array) ($data['options'] ?? [])),
            fn ($o) => $o !== '',
        ));

        $answer = isset($data['answer']) ? trim((string) $data['answer']) : null;

        $question->fill([
            'text' => $data['text'],
            'answer' => $answer !== '' ? $answer : null,
            'options' => $options ?: null,
            'is_numeric' => $answer !== null && $answer !== '' && is_numeric($answer),
            'tolerance' => ($data['tolerance'] ?? null) !== '' ? $data['tolerance'] ?? null : null,
            'unit' => $data['unit'] ?? null,
            'difficulty' => array_key_exists($data['difficulty'] ?? '', self::DIFFICULTIES) ? $data['difficulty'] : 'medium',
            'source' => array_key_exists($data['source'] ?? '', self::SOURCES) ? $data['source'] : 'arena',
            'status' => array_key_exists($data['status'] ?? '', self::STATUSES) ? $data['status'] : 'draft',
        ]);

        if (! $question->exists) {
            $question->created_by = $actor?->id;
        }

        $question->save();

        AuditTrail::log($actor, 'wars_bank.save', $question, $old, $question->only(['text', 'difficulty', 'status', 'source']));

        return $question;
    }

    /** إجراء جماعيّ: تفعيل/أرشفة/حذف دفعةً واحدة (24.2) */
    public function bulk(array $ids, string $action, ?User $actor = null): int
    {
        $ids = array_filter(array_map('intval', $ids));

        if ($ids === [] || ! in_array($action, ['activate', 'draft', 'archive', 'delete'], true)) {
            return 0;
        }

        if ($action === 'delete') {
            $count = WarQuestion::query()->whereIn('id', $ids)->delete();
        } else {
            $status = ['activate' => 'active', 'draft' => 'draft', 'archive' => 'archived'][$action];
            $count = WarQuestion::query()->whereIn('id', $ids)->update(['status' => $status]);
        }

        AuditTrail::log($actor, 'wars_bank.bulk', null, [], ['action' => $action, 'count' => $count]);

        return (int) $count;
    }

    /**
     * استيراد دفعة CSV — **معاينة الصفوف وتقرير الأخطاء قبل الاعتماد** (24.2).
     * الأعمدة: السؤال · الإجابة · الصعوبة · المصدر · الاختيارات (مفصولة بـ|).
     *
     * @return array{rows:list<array>,errors:list<string>}
     */
    public function parseCsv(string $contents): array
    {
        $rows = [];
        $errors = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];

        foreach ($lines as $number => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);

            if ($number === 0 && in_array(trim((string) ($cells[0] ?? '')), ['السؤال', 'question', 'text'], true)) {
                continue;
            }

            $text = trim((string) ($cells[0] ?? ''));
            $answer = trim((string) ($cells[1] ?? ''));

            if ($text === '') {
                $errors[] = strtr(setting('gamification_wars.war_bank_service.parse_csv_1', 'فشل الاستيراد في الصفّ رقم :p1 — نصّ السؤال فاضي.'), [':p1' => (string) (($number + 1))]);

                continue;
            }

            $rows[] = [
                'text' => $text,
                'answer' => $answer !== '' ? $answer : null,
                'difficulty' => array_key_exists(trim((string) ($cells[2] ?? '')), self::DIFFICULTIES) ? trim((string) $cells[2]) : 'medium',
                'source' => array_key_exists(trim((string) ($cells[3] ?? '')), self::SOURCES) ? trim((string) $cells[3]) : 'arena',
                'options' => array_values(array_filter(array_map('trim', explode('|', (string) ($cells[4] ?? ''))))),
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** الاعتماد بعد المعاينة — أو لا شيء إن كان في الملفّ خطأ (24.2) */
    public function import(string $contents, ?User $actor = null): array
    {
        $parsed = $this->parseCsv($contents);

        if ($parsed['errors'] !== []) {
            return ['imported' => 0, 'errors' => $parsed['errors']];
        }

        foreach ($parsed['rows'] as $row) {
            $this->save(null, $row + ['status' => 'draft'], $actor);
        }

        return ['imported' => count($parsed['rows']), 'errors' => []];
    }

    /** تصدير — للمخوَّلين وحدهم لأنّ الإجابات فيه (24.2) */
    public function toCsv(iterable $questions): string
    {
        $out = setting('gamification_wars.war_bank_service.to_csv_1', 'السؤال,الإجابة,الصعوبة,المصدر,الاختيارات,الحالة\\n');

        foreach ($questions as $q) {
            $out .= implode(',', array_map(
                fn ($v) => '"'.str_replace('"', '""', (string) $v).'"',
                [$q->text, $q->answer, $q->difficulty, $q->source, implode('|', (array) ($q->options ?? [])), $q->status],
            ))."\n";
        }

        return $out;
    }
}
