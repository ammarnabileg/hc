<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Trainee\Concerns\ChecksLessonAccess;
use App\Models\Course;
use App\Services\Learning\CourseNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ملاحظات التدريب (الدستور 3.2): مساحة واحدة لكلّ دروس التدريب، بحفظٍ تلقائيّ
 * و«اتحفظ ✓» (2.17-ب). والتصدير مذكور في مصفوفة الصلاحيّات (`course_notes.export`).
 */
class CourseNoteController extends Controller
{
    use ChecksLessonAccess;

    public function __construct(private readonly CourseNoteService $notes) {}

    /** حفظ تلقائيّ: يردّ JSON للواجهة، ويعمل كفورم عاديّ لو الجافاسكربت مقفول */
    public function save(Request $request, Course $course): JsonResponse|RedirectResponse
    {
        abort_unless((bool) setting('learning.notes.enabled', true), 404);

        $user = $request->user();
        $this->enrollmentOrFail($user->id, $course->id);

        $validator = validator($request->all(), [
            'body' => ['nullable', 'string', 'max:'.$this->notes->maxLength()],
        ], [
            'body.max' => setting('learning.notes.too_long_error'),
        ]);

        if ($validator->fails()) {
            $message = (string) $validator->errors()->first('body');

            return $request->expectsJson()
                ? response()->json(['saved' => false, 'message' => $message], 422)
                : back()->withErrors($validator);
        }

        $this->notes->save($user, $course, (string) $request->input('body', ''));

        return $request->expectsJson()
            ? response()->json(['saved' => true, 'message' => setting('learning.notes.saved')])
            : back()->with('status', setting('learning.notes.saved'));
    }

    public function clear(Request $request, Course $course): RedirectResponse
    {
        abort_unless((bool) setting('learning.notes.enabled', true), 404);

        $user = $request->user();
        $this->enrollmentOrFail($user->id, $course->id);

        $this->notes->clear($user, $course);

        return back()->with('status', setting('learning.notes.cleared_message'));
    }

    /** تنزيل نسخة نصّيّة من الملاحظات — ملكيّة المتدرّب لبياناته (3.2) */
    public function export(Request $request, Course $course): StreamedResponse
    {
        $user = $request->user();
        $this->enrollmentOrFail($user->id, $course->id);

        $text = $this->notes->exportText($user, $course);

        return response()->streamDownload(
            fn () => print ($text),
            $this->notes->exportFileName($course),
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
