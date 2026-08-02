<?php

namespace App\Services\AdminScreens;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\LessonQuestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * بنك الأسئلة والامتحانات (24.1-3).
 *
 * لماذا خدمة مستقلّة والأسئلة تُدار داخل الدرس أصلًا؟ لأنّ إدارة السؤال **داخل
 * درسه** تجيب عن سؤال «إيه أسئلة الدرس ده؟» ولا تجيب أبدًا عن «إيه اللي عندي
 * في البنك كلّه، وهل يكفي الامتحان النهائيّ؟». البنك يقرأ عرضيًّا عبر التدريبات
 * كلّها، ويعرف **مرّات استخدام** كلّ سؤال و**نسبة إجابته الصحيحة** — وهذه
 * أرقامٌ لا معنى لها داخل شاشة الدرس الواحد.
 */
class QuestionBank
{
    /** أنواع الأسئلة المعتمَدة — إعداد لا قائمة محروقة (2.13) */
    public function types(): array
    {
        return ScreenSettings::map('question_bank.types');
    }

    public function difficulties(): array
    {
        return ScreenSettings::map('question_bank.difficulties');
    }

    public function cap(): int
    {
        return (int) setting('question_bank.exam_question_cap', 20);
    }

    public function generalMinimum(): int
    {
        return (int) setting('question_bank.general_minimum', 20);
    }

    /**
     * الاستعلام المفلتَر — الجداول مضمومة مرّةً واحدة كي يظهر «التدريب ← الدرس»
     * في نفس الصفّ بلا استعلام لكلّ سطر.
     *
     * @param  array{q?:string,course?:string,lesson?:string,type?:string,difficulty?:string,general?:string,state?:string}  $filters
     */
    public function query(array $filters): Builder
    {
        return LessonQuestion::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_questions.lesson_id')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->join('courses', 'courses.id', '=', 'sections.course_id')
            ->select(
                'lesson_questions.*',
                'lessons.title_ar as lesson_title',
                'courses.name_ar as course_title',
                'courses.id as course_id',
            )
            ->when(($filters['q'] ?? '') !== '', fn ($q) => $q->where('lesson_questions.prompt', 'like', '%'.$filters['q'].'%'))
            ->when(($filters['course'] ?? '') !== '', fn ($q) => $q->where('courses.id', (int) $filters['course']))
            ->when(($filters['lesson'] ?? '') !== '', fn ($q) => $q->where('lessons.id', (int) $filters['lesson']))
            ->when(($filters['type'] ?? '') !== '', fn ($q) => $q->where('lesson_questions.type', $filters['type']))
            ->when(($filters['difficulty'] ?? '') !== '', fn ($q) => $q->where('lesson_questions.difficulty', $filters['difficulty']))
            ->when(($filters['general'] ?? '') === '1', fn ($q) => $q->where('lesson_questions.is_general', true))
            ->when(($filters['state'] ?? '') === 'active', fn ($q) => $q->where('lesson_questions.is_active', true))
            ->when(($filters['state'] ?? '') === 'paused', fn ($q) => $q->where('lesson_questions.is_active', false))
            ->orderByDesc('lesson_questions.id');
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate(max(5, (int) setting('question_bank.per_page', 20)))
            ->withQueryString();
    }

    /**
     * أربعة أرقام لا خمسة (2.15-أ-3): حجم البنك · العامّة · سقف الامتحان · المعطّلة.
     *
     * @return array{total:int,general:int,cap:int,paused:int,short:bool}
     */
    public function stats(): array
    {
        $total = (int) LessonQuestion::query()->count();
        $general = (int) LessonQuestion::query()->where('is_general', true)->where('is_active', true)->count();
        $minimum = $this->generalMinimum();

        return [
            'total' => $total,
            'general' => $general,
            'cap' => $this->cap(),
            'paused' => (int) LessonQuestion::query()->where('is_active', false)->count(),
            // التحذير نفسه إعداد يمكن إيقافه — والرقم يظهر في كلّ الأحوال (2.17-أ)
            'short' => (bool) setting('question_bank.low_general_warning', true) && $general < $minimum,
        ];
    }

    /**
     * مرّات الاستخدام لكلّ سؤال في هذه الصفحة — استعلام واحد لا استعلام لكلّ صفّ.
     *
     * @param  Collection<int,LessonQuestion>|iterable  $questions
     * @return array<int,int>
     */
    public function usageCounts(iterable $questions): array
    {
        $ids = collect($questions)->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        return ExamQuestion::query()
            ->whereIn('source_question_id', $ids)
            ->groupBy('source_question_id')
            ->selectRaw('source_question_id, count(*) as uses')
            ->pluck('uses', 'source_question_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /**
     * نسبة الإجابة الصحيحة لكلّ سؤال — والسؤال بلا محاولات يعود بـnull
     * لأنّ «0%» كذبٌ على الأدمن (2.9-7: الأرقام صادقة أو لا تُعرَض).
     *
     * @return array<int,float|null>
     */
    public function correctRates(iterable $questions): array
    {
        $ids = collect($questions)->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $rows = DB::table('lesson_question_answers')
            ->whereIn('lesson_question_id', $ids)
            ->groupBy('lesson_question_id')
            ->selectRaw('lesson_question_id, count(*) as total, sum(case when is_correct then 1 else 0 end) as correct')
            ->get();

        $rates = [];

        foreach ($rows as $row) {
            $rates[(int) $row->lesson_question_id] = (int) $row->total > 0
                ? round((int) $row->correct / (int) $row->total * 100, 1)
                : null;
        }

        return $rates;
    }

    /** التدريبات لقائمة الفلتر — بالاسم لا بالرقم */
    public function courses(): Collection
    {
        return Course::query()->orderBy('name_ar')->get(['id', 'name_ar']);
    }

    /** دروس تدريب بعينه (للفلتر المتقدّم وقائمة «نقل لدرس آخر») */
    public function lessons(?int $courseId = null): Collection
    {
        return DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->join('courses', 'courses.id', '=', 'sections.course_id')
            ->when($courseId, fn ($q) => $q->where('courses.id', $courseId))
            ->orderBy('courses.name_ar')
            ->orderBy('sections.sort_order')
            ->orderBy('lessons.sort_order')
            ->limit((int) setting('question_bank.lessons_picker_limit', 500))
            ->get(['lessons.id', 'lessons.title_ar', 'courses.name_ar as course_name']);
    }

    /** الامتحانات المتاحة لإعادة استخدام السؤال فيها */
    public function exams(): Collection
    {
        return Exam::query()->where('is_active', true)->orderBy('title_ar')->get(['id', 'title_ar']);
    }

    /**
     * ⭐ إعادة استخدام السؤال في امتحان — قلب البنك.
     * ننسخ نصّه وخياراته إلى `exam_questions` ونحتفظ برابط الأصل، فالسؤال
     * الواحد يعيش في أكثر من امتحان بلا ازدواج، وتعديل الأصل لا يكسر امتحانًا
     * جرى أداؤه من قبل.
     *
     * @return array{attached:int,skipped:int}
     */
    public function reuse(LessonQuestion $question, array $examIds): array
    {
        $attached = 0;
        $skipped = 0;

        /*
         | ⭐ الامتحان النهائيّ يُبنى من الأسئلة المعلَّمة **«عام» وحدها** (4 · 4.2):
         | «الأسئلة العامّة فقط هي المؤهّلة للدخول في الامتحان النهائيّ، لأنّ فيه
         | أسئلة درسٍ لا تصلح له». وبلا هذا الفحص كانت خاصّيّة «سؤال عام» زينةً
         | يتخطّاها هذا الباب، فيدخل سؤالُ درسٍ امتحانًا تُصدَر بنجاحه شهادة.
         */
        if (! $question->is_general) {
            return ['attached' => 0, 'skipped' => count(array_unique(array_map('intval', $examIds)))];
        }

        foreach (array_unique(array_map('intval', $examIds)) as $examId) {
            $exam = Exam::query()->find($examId);

            if (! $exam) {
                $skipped++;

                continue;
            }

            $exists = ExamQuestion::query()
                ->where('exam_id', $exam->id)
                ->where('source_question_id', $question->id)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            ExamQuestion::create([
                'exam_id' => $exam->id,
                'source_question_id' => $question->id,
                'type' => $question->type,
                'prompt' => $question->prompt,
                'options' => $question->options,
                'correct_answer' => $question->correct_answer,
                'weight' => 1,
                'sort_order' => (int) ExamQuestion::query()->where('exam_id', $exam->id)->max('sort_order') + 1,
            ]);

            $attached++;
        }

        return ['attached' => $attached, 'skipped' => $skipped];
    }

    /**
     * معاينة الامتحان النهائيّ كما سيُبنى: الأسئلة العامّة النشطة حتّى السقف.
     *
     * @return Collection<int,LessonQuestion>
     */
    public function examPreview(): Collection
    {
        $query = LessonQuestion::query()
            ->where('is_general', true)
            ->where('is_active', true)
            ->limit($this->cap());

        return setting('question_bank.shuffle_questions', true)
            ? $query->inRandomOrder()->get()
            : $query->orderBy('id')->get();
    }

    /**
     * استيراد CSV بتقرير صفّ-بصفّ: الصفّ السليم يمرّ والفاسد يُبلَّغ عنه
     * بسطره ورقمه — فلا يضيع الملفّ كلّه بسبب خليّة واحدة (24.1-3 الحالات).
     *
     * @return array{imported:int,errors:array<int,string>}
     */
    public function import(string $csv, ?int $fallbackLessonId = null): array
    {
        $types = $this->types();
        $difficulties = $this->difficulties();
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $header = null;
        $imported = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);

            if ($header === null) {
                $header = array_map(fn ($cell) => trim((string) $cell), $cells);

                continue;
            }

            $row = [];

            foreach ($header as $position => $name) {
                $row[$name] = isset($cells[$position]) ? trim((string) $cells[$position]) : '';
            }

            $lessonId = (int) ($row['lesson_id'] ?? 0) ?: $fallbackLessonId;
            $prompt = (string) ($row['prompt'] ?? '');
            $type = (string) ($row['type'] ?? 'choice');

            if (! $lessonId || ! DB::table('lessons')->where('id', $lessonId)->exists()) {
                $errors[] = 'السطر '.($index + 1).': الدرس غير موجود — اكتب رقم درس صحيح أو اختر درسًا افتراضيًّا.';

                continue;
            }

            if ($prompt === '') {
                $errors[] = 'السطر '.($index + 1).': نصّ السؤال فاضي — السؤال بلا نصّ لا يُعرَض.';

                continue;
            }

            if (! array_key_exists($type, $types)) {
                $errors[] = 'السطر '.($index + 1).': نوع غير معروف «'.$type.'» — استعمل: '.implode(' · ', array_keys($types)).'.';

                continue;
            }

            $difficulty = (string) ($row['difficulty'] ?? 'medium');

            LessonQuestion::create([
                'lesson_id' => $lessonId,
                'type' => $type,
                'prompt' => $prompt,
                'placeholder' => ($row['placeholder'] ?? '') ?: null,
                'options' => ($row['options'] ?? '') !== '' ? array_map('trim', explode('|', (string) $row['options'])) : null,
                'correct_answer' => ($row['correct_answer'] ?? '') ?: null,
                'is_general' => in_array((string) ($row['is_general'] ?? '0'), ['1', 'true', 'نعم'], true),
                'is_active' => true,
                'difficulty' => array_key_exists($difficulty, $difficulties) ? $difficulty : 'medium',
            ]);

            $imported++;
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * صفوف التصدير — نفس أعمدة قالب الاستيراد فيدور الملفّ ويعود بلا تحويل يدويّ.
     *
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        return $this->query($filters)->limit((int) setting('question_bank.export.max_rows', 50000))->get()->map(fn (LessonQuestion $question) => [
            'id' => $question->id,
            'lesson_id' => $question->lesson_id,
            'type' => $question->type,
            'prompt' => $question->prompt,
            'placeholder' => $question->placeholder,
            'options' => is_array($question->options) ? implode('|', $question->options) : '',
            'correct_answer' => $question->correct_answer,
            'is_general' => $question->is_general ? 1 : 0,
            'difficulty' => $question->difficulty,
            'is_active' => $question->is_active ? 1 : 0,
        ])->all();
    }
}
