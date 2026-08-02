<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ترحيل الإتاحة إلى مخزنها المعتمَد (الدستور 5).
 *
 * لماذا؟ كان للإتاحة مخزنان: عمود `courses.availability` (JSON يكتبه فورم
 * التدريب) وجدول `course_availability_periods` مع `daily_open_at/daily_close_at`
 * (المخطّط المعتمَد الذي لم يكن يقرؤه أحد). مخزنان لحقيقة واحدة يعني قيمتين
 * متعارضتين، فنُرحّل القديم إلى المعتمَد مرّةً واحدة ونجعل القراءة والكتابة عليه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_availability_periods') || ! Schema::hasColumn('courses', 'availability')) {
            return;
        }

        $courses = DB::table('courses')
            ->whereNotNull('availability')
            ->get(['id', 'availability', 'daily_open_at', 'daily_close_at']);

        foreach ($courses as $course) {
            $raw = json_decode((string) $course->availability, true);

            if (! is_array($raw)) {
                continue;
            }

            $this->periods((int) $course->id, $raw['windows'] ?? []);
            $this->dailyWindow($course, $raw);
        }
    }

    /** فترات الإتاحة المتعدّدة — والفترة بلا طرفين لا معنى لها فتُهمَل */
    private function periods(int $courseId, mixed $windows): void
    {
        foreach ((array) $windows as $window) {
            $from = $window['from'] ?? null;
            $to = $window['to'] ?? null;

            if (! $from || ! $to) {
                continue;
            }

            $exists = DB::table('course_availability_periods')
                ->where('course_id', $courseId)
                ->whereDate('starts_on', $from)
                ->whereDate('ends_on', $to)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('course_availability_periods')->insert([
                'course_id' => $courseId,
                'starts_on' => $from,
                'ends_on' => $to,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** أوقات التشغيل اليوميّة — لا تُدهَس قيمة موجودة في الأعمدة المعتمَدة */
    private function dailyWindow(object $course, array $raw): void
    {
        $open = $raw['daily_from'] ?? null;
        $close = $raw['daily_to'] ?? null;

        if (! $open || ! $close || $course->daily_open_at || $course->daily_close_at) {
            return;
        }

        DB::table('courses')->where('id', $course->id)->update([
            'daily_open_at' => $this->time($open),
            'daily_close_at' => $this->time($close),
        ]);
    }

    private function time(string $value): string
    {
        [$h, $m] = array_pad(explode(':', $value), 2, '00');

        return str_pad((string) (int) $h, 2, '0', STR_PAD_LEFT).':'.str_pad((string) (int) $m, 2, '0', STR_PAD_LEFT).':00';
    }
};
