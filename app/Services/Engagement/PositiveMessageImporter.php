<?php

namespace App\Services\Engagement;

use App\Models\PositiveMessage;
use Illuminate\Http\UploadedFile;

/**
 * استيراد الرسائل الإيجابيّة من CSV (2.6-ب) — على خطوتين لا خطوة واحدة:
 * `parse()` يقرأ الملفّ ويرجع **معاينة صفًّا بصفّ** بلا حفظٍ في القاعدة،
 * و`commit()` منفصلة تحفظ ما اعتمده الأدمن بعد رؤية المعاينة — «بوب-أب
 * استيراد CSV بمعاينة الصفوف قبل الاعتماد» نصًّا، لا استيرادًا فوريًّا
 * كنظيره في `QuestionImporter` (ذاك الاستيراد **فوريّ** بتقرير صفٍّ فشل
 * بعد الحفظ، وهذا **معاينة قبل** الحفظ أصلًا — طلبٌ أدقّ من الدستور).
 */
class PositiveMessageImporter
{
    /** أعمدة القالب المعتمَد — من الإعدادات لا من الكود (2.13) */
    public function columns(): array
    {
        return (array) setting('engagement.positive.csv_columns', [
            'context', 'body', 'emoji', 'language', 'sort_order', 'is_active',
        ]);
    }

    /**
     * @return array<int, array{row: int, valid: bool, data: array<string,mixed>, error: ?string}>
     */
    public function parse(UploadedFile $file, array $contexts): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [];
        }

        $header = null;
        $rowNumber = 0;
        $max = (int) setting('engagement.positive.csv_max_rows', 300);

        while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rowNumber++;

            if ($header === null) {
                $header = array_map(fn ($h) => trim(mb_strtolower((string) $h)), $line);

                continue;
            }

            if (count($rows) >= $max) {
                break;
            }

            $data = $this->associate($header, $line);
            $rows[] = $this->validateRow($rowNumber, $data, $contexts);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, array{row: int, valid: bool, data: array<string,mixed>, error: ?string}>  $rows
     */
    public function commit(array $rows, ?int $userId): int
    {
        $imported = 0;

        foreach ($rows as $row) {
            if (! ($row['valid'] ?? false)) {
                continue;
            }

            PositiveMessage::create([...$row['data'], 'created_by' => $userId]);
            $imported++;
        }

        return $imported;
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

    /**
     * @return array{row: int, valid: bool, data: array<string,mixed>, error: ?string}
     */
    private function validateRow(int $rowNumber, array $data, array $contexts): array
    {
        $context = trim((string) ($data['context'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $language = trim((string) ($data['language'] ?? '')) ?: 'ar';

        $error = match (true) {
            $body === '' => (string) setting('engagement.admin.import_row_empty_body', 'نصّ الرسالة فاضي.'),
            ! array_key_exists($context, $contexts) => (string) setting('engagement.admin.import_row_bad_context', 'السياق مش من القائمة المعتمَدة.'),
            ! in_array($language, ['ar', 'en'], true) => (string) setting('engagement.admin.import_row_bad_language', 'اللغة لازم ar أو en.'),
            default => null,
        };

        return [
            'row' => $rowNumber,
            'valid' => $error === null,
            'error' => $error,
            'data' => [
                'context' => $context,
                'body' => $body,
                'emoji' => trim((string) ($data['emoji'] ?? '')) ?: null,
                'language' => $language,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => in_array(mb_strtolower(trim((string) ($data['is_active'] ?? '1'))), ['1', 'true', 'yes'], true),
            ],
        ];
    }
}
