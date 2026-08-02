<?php

namespace App\Services\Learning;

use App\Models\Lesson;
use App\Models\LessonVideoView;
use App\Models\User;

/**
 * ⭐ تتبّع مشاهدة الفيديو (4.1).
 *
 * الدستور يعرّف **«إنهاء الدرس» = مشاهدة الفيديو + اجتياز اختباره**، «الاتنين
 * مطلوبين لاحتساب الإكمال والـXP». وكان الشقّ الأوّل بلا تنفيذ أصلًا: لا عمود
 * ولا نقطة نهاية، و`POST /complete` ينجح بلا فتح الفيديو — فيؤخَذ XP الدرس
 * كاملًا بلا تعلّم، وهو ما يجعل ما بعده (الامتحان والشهادة) مبنيًّا على فراغ.
 *
 * والقرار في الخادم حصرًا: المتصفّح **يبلّغ** بموضعه في المشغّل، والخادم وحده
 * يقرّر متى صارت المشاهدة كافية — بالنسبة التي يضبطها الأدمن (2.13)، وبسقفٍ
 * لا تتجاوزه القفزة الواحدة كي لا يُعلَن الفيديو مشاهَدًا بنداءٍ واحد ملفَّق.
 */
class VideoWatchService
{
    /** الدرس النصّيّ لا فيديو له — فشرط المشاهدة لا ينطبق عليه (3) */
    public function requiresWatch(Lesson $lesson): bool
    {
        return $lesson->type === 'video' && (bool) setting('learning.video.require_watch', true);
    }

    /** هل استوفى المتدرّب شرط المشاهدة لهذا الدرس؟ */
    public function hasWatched(User $user, Lesson $lesson): bool
    {
        if (! $this->requiresWatch($lesson)) {
            return true;
        }

        return LessonVideoView::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->whereNotNull('completed_at')
            ->exists();
    }

    /**
     * تسجيل موضع المشاهدة المبلَّغ عنه.
     *
     * @param  int  $position  أقصى ثانية بلغها المشغّل
     * @param  int  $duration  طول الفيديو بالثواني كما يعرفه المشغّل
     * @return array{watched:bool,percent:int,seconds:int}
     */
    public function track(User $user, Lesson $lesson, int $position, int $duration): array
    {
        $view = LessonVideoView::query()->firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['watched_seconds' => 0, 'duration_seconds' => 0],
        );

        $duration = max(0, min($duration, (int) setting('learning.video.max_duration_seconds', 43200)));
        $duration = $duration ?: (int) $view->duration_seconds ?: $this->declaredDuration($lesson);

        /*
         | القفزة الواحدة محدودة: التقرير يصل كلّ بضع ثوانٍ، فمن يرسل «أنا في
         | الدقيقة 40» من أوّل نداء لا يتقدّم إلّا بمقدار خطوةٍ واحدة. الشرط
         | يُستوفى بالبقاء لا بالادّعاء.
         */
        $step = max(1, (int) setting('learning.video.max_step_seconds', 60));
        $ceiling = (int) $view->watched_seconds + $step;
        $watched = max((int) $view->watched_seconds, min(max(0, $position), $ceiling));

        if ($duration > 0) {
            $watched = min($watched, $duration);
        }

        $percent = $duration > 0 ? (int) round($watched / $duration * 100) : 0;
        $required = max(1, min(100, (int) setting('learning.video.required_percent', 90)));

        $view->forceFill([
            'watched_seconds' => $watched,
            'duration_seconds' => $duration,
            'completed_at' => $view->completed_at ?? ($percent >= $required ? now() : null),
        ])->save();

        return [
            'watched' => $view->completed_at !== null,
            'percent' => min(100, $percent),
            'seconds' => $watched,
        ];
    }

    /** طول الفيديو كما أعلنه الأدمن — ارتدادٌ حين لا يبلّغ المشغّل بطولٍ */
    private function declaredDuration(Lesson $lesson): int
    {
        return (int) $lesson->duration_minutes * 60;
    }
}
