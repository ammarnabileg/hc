<?php

namespace App\Services\Admin\Content;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\LessonQuestion;
use App\Models\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * بناء محتوى التدريب (12.4-ج): سيكشنز قابلة للسحب، وداخلها دروس،
 * والدرس إمّا **فيديو يوتيوب** (ID يُستخرَج تلقائيًّا + كود HTML تابع + مرفقات)
 * أو **نصّ** منسّق — ولكلّ درس تبويب أسئلة.
 */
class LessonBuilder
{
    public function __construct(private readonly ContentAudit $audit) {}

    /** شجرة المحتوى: سيكشنز ⟵ دروس ⟵ عدد الأسئلة. */
    public function tree(Course $course): Collection
    {
        $sections = Section::query()->where('course_id', $course->id)->orderBy('sort_order')->orderBy('id')->get();

        $lessons = Lesson::query()
            ->whereIn('section_id', $sections->pluck('id'))
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $questionCounts = LessonQuestion::query()
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->selectRaw('lesson_id, count(*) as total, sum(case when is_general = 1 then 1 else 0 end) as general')
            ->groupBy('lesson_id')
            ->get()
            ->keyBy('lesson_id');

        return $sections->each(function (Section $section) use ($lessons, $questionCounts) {
            $section->setAttribute('lessons', $lessons->where('section_id', $section->id)->values()
                ->each(function (Lesson $lesson) use ($questionCounts) {
                    $lesson->setAttribute('questions_total', (int) ($questionCounts[$lesson->id]->total ?? 0));
                    $lesson->setAttribute('questions_general', (int) ($questionCounts[$lesson->id]->general ?? 0));
                }));
        });
    }

    public function addSection(Course $course, string $titleAr, ?string $titleEn = null): Section
    {
        $section = Section::create([
            'course_id' => $course->id,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'sort_order' => ((int) Section::query()->where('course_id', $course->id)->max('sort_order')) + 1,
        ]);

        $this->audit->record($course, 'section.created', [], ['title' => $titleAr]);

        return $section;
    }

    /** @param  array<int, int>  $orderedIds */
    public function reorderSections(Course $course, array $orderedIds): void
    {
        DB::transaction(function () use ($course, $orderedIds) {
            foreach (array_values($orderedIds) as $index => $id) {
                Section::query()->where('course_id', $course->id)->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });
    }

    /** @param  array<string, mixed>  $data */
    public function saveLesson(Section $section, ?Lesson $lesson, array $data): Lesson
    {
        $type = in_array($data['type'] ?? 'video', ['video', 'document'], true) ? (string) ($data['type'] ?? 'video') : 'video';

        $payload = [
            'section_id' => $section->id,
            'title_ar' => $data['title_ar'],
            'title_en' => $data['title_en'] ?? null,
            'type' => $type,
            'video_provider' => $type === 'video' ? 'youtube' : null,
            // ID اليوتيوب يُستخرَج تلقائيًّا من الرابط فيُبنى الثامبنيل بلا خطوة إضافيّة (12.4-ج)
            'video_id' => $type === 'video' ? $this->youtubeId((string) ($data['video_url'] ?? '')) : null,
            'embed_html' => $type === 'video' ? ($data['embed_html'] ?? null) : null,
            'content' => $data['content'] ?? null,
            'duration_minutes' => ($data['duration_minutes'] ?? null) !== '' ? (int) ($data['duration_minutes'] ?? 0) ?: null : null,
            'is_free_preview' => (bool) ($data['is_free_preview'] ?? false),
        ];

        if ($lesson) {
            $lesson->update($payload);
        } else {
            $payload['sort_order'] = ((int) Lesson::query()->where('section_id', $section->id)->max('sort_order')) + 1;
            $lesson = Lesson::create($payload);
        }

        if (array_key_exists('attachment_ids', $data)) {
            $this->syncAttachments($lesson, array_map('intval', (array) $data['attachment_ids']));
        }

        $this->audit->record($lesson, 'lesson.saved', [], ['title' => $payload['title_ar']]);

        return $lesson->refresh();
    }

    /** نقل الدرس بين السيكشنز — سحب-إفلات في الواجهة (12.4-هـ). */
    public function move(Lesson $lesson, Section $target, ?int $position = null): Lesson
    {
        $lesson->update([
            'section_id' => $target->id,
            'sort_order' => $position ?? (((int) Lesson::query()->where('section_id', $target->id)->max('sort_order')) + 1),
        ]);

        $this->audit->record($lesson, 'lesson.moved', [], ['section_id' => $target->id]);

        return $lesson;
    }

    public function duplicateLesson(Lesson $lesson): Lesson
    {
        return DB::transaction(function () use ($lesson) {
            $copy = $lesson->replicate(['created_at', 'updated_at']);
            $copy->title_ar = $lesson->title_ar.(string) setting('lessons.duplicate.suffix', ' — نسخة');
            $copy->sort_order = ((int) Lesson::query()->where('section_id', $lesson->section_id)->max('sort_order')) + 1;
            $copy->save();

            foreach (LessonQuestion::query()->where('lesson_id', $lesson->id)->get() as $question) {
                $newQuestion = $question->replicate(['created_at', 'updated_at']);
                $newQuestion->lesson_id = $copy->id;
                $newQuestion->save();
            }

            foreach (LessonAttachment::query()->where('lesson_id', $lesson->id)->get() as $attachment) {
                LessonAttachment::create([
                    'lesson_id' => $copy->id,
                    'media_item_id' => $attachment->media_item_id,
                    'sort_order' => $attachment->sort_order,
                ]);
            }

            return $copy;
        });
    }

    /**
     * سؤال الدرس (12.4-ج): النوع + **نصّ Placeholder داخل الحقل** + الإجابة الصحيحة
     * + تبديل **«سؤال عامّ»** الذي يُدخِله بنك الامتحان النهائيّ.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveQuestion(Lesson $lesson, ?LessonQuestion $question, array $data): LessonQuestion
    {
        $type = in_array($data['type'] ?? 'otp', ['otp', 'choice', 'text'], true) ? (string) ($data['type'] ?? 'otp') : 'otp';

        $payload = [
            'lesson_id' => $lesson->id,
            'type' => $type,
            'prompt' => $data['prompt'],
            // Placeholder داخل الحقل — نصّه من الإعدادات لكلّ نوع حين لا يكتبه الأدمن (2.13)
            'placeholder' => ($data['placeholder'] ?? null) ?: $this->defaultPlaceholder($type),
            'options' => $type === 'choice' ? $this->cleanOptions($data['options'] ?? []) : null,
            'correct_answer' => $data['correct_answer'] ?? null,
            'is_general' => (bool) ($data['is_general'] ?? false),
            'xp_reward' => (int) ($data['xp_reward'] ?? setting('lessons.questions.default_xp', 0)),
        ];

        if ($question) {
            $question->update($payload);

            return $question;
        }

        $payload['sort_order'] = ((int) LessonQuestion::query()->where('lesson_id', $lesson->id)->max('sort_order')) + 1;

        return LessonQuestion::create($payload);
    }

    public function defaultPlaceholder(string $type): string
    {
        return (string) match ($type) {
            'choice' => setting('lessons.questions.placeholder_choice', 'اختر الإجابة الصحيحة'),
            'text' => setting('lessons.questions.placeholder_text', 'اكتب إجابتك هنا…'),
            default => setting('lessons.questions.placeholder_otp', 'اكتب الرقم'),
        };
    }

    /** استخراج ID اليوتيوب من أيّ صيغة رابط شائعة. */
    public function youtubeId(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('~(?:youtu\.be/|v/|embed/|watch\?v=|&v=|shorts/)([A-Za-z0-9_\-]{6,})~', $url, $m)) {
            return $m[1];
        }

        return preg_match('~^[A-Za-z0-9_\-]{6,}$~', $url) ? $url : null;
    }

    /** @param  array<int, int>  $mediaIds */
    private function syncAttachments(Lesson $lesson, array $mediaIds): void
    {
        $mediaIds = array_values(array_filter(array_unique($mediaIds)));

        LessonAttachment::query()
            ->where('lesson_id', $lesson->id)
            ->when($mediaIds !== [], fn ($q) => $q->whereNotIn('media_item_id', $mediaIds))
            ->delete();

        foreach ($mediaIds as $index => $mediaId) {
            LessonAttachment::firstOrCreate(
                ['lesson_id' => $lesson->id, 'media_item_id' => $mediaId],
                ['sort_order' => $index + 1],
            );
        }
    }

    /** @return array<int, string> */
    private function cleanOptions(mixed $options): array
    {
        $list = is_string($options) ? preg_split('/\r?\n/', $options) : (array) $options;

        return collect($list)->map(fn ($o) => trim((string) $o))->filter()->values()->all();
    }
}
