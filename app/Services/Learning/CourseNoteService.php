<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\CourseNote;
use App\Models\User;

/**
 * ملاحظات التدريب (الدستور 3.2): **Text Area واحد مشترك لكلّ دروس التدريب** —
 * يكتب من أيّ درس فيجد ما كتبه في الدرس التالي، **ويُحفَظ تلقائيًّا** بـ«اتحفظ ✓» (2.17-ب).
 */
class CourseNoteService
{
    public function bodyFor(User $user, Course $course): string
    {
        return (string) (CourseNote::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->value('body') ?? '');
    }

    public function maxLength(): int
    {
        return max(1, (int) setting('learning.notes.max_length', 20000));
    }

    /** حفظ (أو إنشاء) الملاحظة الموحّدة — سجلّ واحد لكلّ (مستخدم، تدريب) */
    public function save(User $user, Course $course, string $body): CourseNote
    {
        return CourseNote::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['body' => $body],
        );
    }

    /** المسح يُفرّغ المساحة ولا يحذف السجلّ — فالمساحة نفسها ثابتة للتدريب */
    public function clear(User $user, Course $course): void
    {
        CourseNote::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->update(['body' => '']);
    }

    /** نصّ التصدير: عنوان التدريب وتاريخ التنزيل ثمّ الملاحظات كما كتبها */
    public function exportText(User $user, Course $course): string
    {
        return implode("\n", [
            $course->name_ar,
            setting('learning.notes.export_heading', 'ملاحظاتي على التدريب'),
            setting('learning.notes.export_date_label', 'تاريخ التنزيل').': '.now()->format('Y-m-d'),
            str_repeat('-', 40),
            '',
            $this->bodyFor($user, $course),
            '',
        ]);
    }

    /** اسم ملفّ آمن — بلا مسافات ولا محارف تكسر رأس التنزيل */
    public function exportFileName(Course $course): string
    {
        $slug = preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $course->slug) ?: 'course';

        return setting('learning.notes.export_file_prefix', 'notes').'-'.$slug.'.txt';
    }
}
