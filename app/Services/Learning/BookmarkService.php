<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * حفظ درس (Bookmark) — اقتراح العرض المعتمَد 3.4-34.
 *
 * لماذا خدمة لا سطران في الكنترولر؟ لأنّ الحفظ حالة تُقرأ في ثلاث شاشات
 * (صفحة التدريب · صفحة الدرس · قائمة المحفوظات لاحقًا)، ولو تفرّق حسابها
 * لاختلف معنى «محفوظ» بين شاشة وأخرى — وهي نفس علّة توحيد التقدّم والإتاحة.
 */
class BookmarkService
{
    /** تبديل الحالة — والقيد الفريد يجعل النداء آمنًا للتكرار */
    public function toggle(User $user, Lesson $lesson): bool
    {
        $deleted = DB::table('lesson_bookmarks')
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->delete();

        if ($deleted > 0) {
            return false;
        }

        DB::table('lesson_bookmarks')->insertOrIgnore([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    public function has(User $user, Lesson $lesson): bool
    {
        return DB::table('lesson_bookmarks')
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->exists();
    }

    /**
     * معرّفات دروس التدريب المحفوظة — استعلامٌ واحد لكلّ الخريطة لا لكلّ صفّ.
     *
     * @return array<int, int>
     */
    public function idsFor(User $user, Course $course): array
    {
        return DB::table('lesson_bookmarks')
            ->join('lessons', 'lessons.id', '=', 'lesson_bookmarks.lesson_id')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('lesson_bookmarks.user_id', $user->id)
            ->where('sections.course_id', $course->id)
            ->pluck('lesson_bookmarks.lesson_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
