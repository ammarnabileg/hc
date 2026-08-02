<?php

namespace App\Services\Admin\Content;

use App\Models\Lesson;
use Illuminate\Http\UploadedFile;

/**
 * استيراد أسئلة الدرس من CSV (12.4-هـ · 24.1).
 *
 * الفشل الجزئيّ لا يُسقِط الملفّ كلّه: يُستورَد الصفّ السليم ويُرجَع تقرير
 * **صفًّا بصفّ** بما فشل ولماذا — لأنّ رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب).
 */
class QuestionImporter
{
    public function __construct(private readonly LessonBuilder $lessons) {}

    /** أعمدة القالب المعتمَد — من الإعدادات لا من الكود (2.13). */
    public function columns(): array
    {
        return (array) setting('lessons.questions.csv_columns', [
            'type', 'prompt', 'placeholder', 'options', 'correct_answer', 'is_general',
        ]);
    }

    /**
     * @return array{imported: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(Lesson $lesson, UploadedFile $file): array
    {
        $imported = 0;
        $errors = [];

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return ['imported' => 0, 'errors' => [['row' => 0, 'message' => 'تعذّرت قراءة الملفّ — جرّب رفعه مرّة أخرى.']]];
        }

        $header = null;
        $rowNumber = 0;
        $max = (int) setting('lessons.questions.csv_max_rows', 500);

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rowNumber++;

            if ($header === null) {
                $header = array_map(fn ($h) => trim(mb_strtolower((string) $h)), $row);

                continue;
            }

            if ($imported >= $max) {
                $errors[] = ['row' => $rowNumber, 'message' => 'تجاوزنا الحدّ المسموح ('.$max.' سؤالًا) — قسّم الملفّ.'];
                break;
            }

            $data = $this->associate($header, $row);

            if (trim((string) ($data['prompt'] ?? '')) === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'نصّ السؤال فاضي — اكتبه في عمود prompt.'];

                continue;
            }

            $this->lessons->saveQuestion($lesson, null, [
                'type' => $data['type'] ?? 'otp',
                'prompt' => $data['prompt'],
                'placeholder' => $data['placeholder'] ?? null,
                'options' => array_filter(array_map('trim', explode('|', (string) ($data['options'] ?? '')))),
                'correct_answer' => $data['correct_answer'] ?? null,
                'is_general' => in_array(trim((string) ($data['is_general'] ?? '')), ['1', 'true', 'نعم', 'yes'], true),
            ]);

            $imported++;
        }

        fclose($handle);

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string|null>  $row
     * @return array<string, string|null>
     */
    private function associate(array $header, array $row): array
    {
        $data = [];

        foreach ($header as $index => $name) {
            $data[$name] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        return $data;
    }
}
