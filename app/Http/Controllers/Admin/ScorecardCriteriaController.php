<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InterviewCriterion;
use App\Models\InterviewScorecard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * معايير المقابلة (Scorecard Criteria) — الدستور 13.4-د.
 *
 * «معايير يضيفها الأدمن (بلا حدود) يجاوب عليها المشرف بدرجة /10، مع أوزان
 * اختياريّة». وحذف المعيار **سوفت بخيارين**: «من الجديد فقط» (القديم يبقى
 * بدرجته موسومًا «معيار مؤرشف» — `ScorecardEngine::rows()`) أو «من الجديد
 * والقديم» (يختفي تمامًا)، **مع Undo (إعادة تفعيل) وسجلّ تدقيق**.
 */
class ScorecardCriteriaController extends Controller
{
    public function index(): View
    {
        $criteria = InterviewCriterion::query()->orderBy('sort_order')->orderBy('id')->get();

        // استخدام حيّ من الـJSON — لا استعلامًا يفترض محرّك قاعدة بيانات بعينه
        $usage = InterviewScorecard::query()
            ->whereNotNull('criteria_scores')
            ->pluck('criteria_scores')
            ->reduce(function (array $counts, $scores) {
                foreach (array_keys((array) $scores) as $id) {
                    $counts[(int) $id] = ($counts[(int) $id] ?? 0) + 1;
                }

                return $counts;
            }, []);

        return view('admin.scorecard-criteria.index', [
            'criteria' => $criteria,
            'usage' => $usage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $criterion = InterviewCriterion::create($data + ['is_archived' => false]);

        $this->audit($request, $criterion, 'scorecard_criteria.created', [], $criterion->only(['label_ar', 'weight', 'sort_order']));

        return redirect()
            ->route('admin.volunteer.scorecard-criteria.index')
            ->with('status', (string) setting('scorecard_criteria.save_ok', 'اتحفظ ✓ — المعيار هيظهر في نتيجة المقابلة القادمة.'));
    }

    public function update(Request $request, InterviewCriterion $criterion): RedirectResponse
    {
        $data = $this->validated($request);
        $old = $criterion->only(['label_ar', 'weight', 'sort_order']);

        $criterion->update($data);

        $this->audit($request, $criterion, 'scorecard_criteria.updated', $old, $data);

        return redirect()
            ->route('admin.volunteer.scorecard-criteria.index')
            ->with('status', (string) setting('scorecard_criteria.save_ok', 'اتحفظ ✓ — المعيار هيظهر في نتيجة المقابلة القادمة.'));
    }

    /**
     * حذف سوفت بخيارين — `mode` من الفورم يحدّد الأثر (13.4-د):
     *  - `new_only` (افتراضيّ): أرشفة — يختفي من الإدخال الجديد فقط، والقديم
     *    يبقى بدرجته موسومًا «معيار مؤرشف» (`ScorecardEngine::allCriteria()`).
     *  - `new_and_old`: حذفٌ فعليّ — يختفي تمامًا حتى من النتائج القديمة،
     *    لأنّ `ScorecardEngine::rows()` تُغذَّى من `allCriteria()` وحدها.
     */
    public function destroy(Request $request, InterviewCriterion $criterion): RedirectResponse
    {
        $mode = $request->string('mode', 'new_only')->value();

        if ($mode === 'new_and_old') {
            $this->audit($request, $criterion, 'scorecard_criteria.deleted_everywhere', $criterion->only(['label_ar']), []);
            $criterion->delete();

            return back()->with('status', (string) setting(
                'scorecard_criteria.deleted_everywhere_ok',
                'اتحذف نهائيًّا — اختفى من كلّ النتائج، القديمة والجديدة.',
            ));
        }

        $criterion->update(['is_archived' => true]);
        $this->audit($request, $criterion, 'scorecard_criteria.archived', ['is_archived' => false], ['is_archived' => true]);

        return back()->with('status', (string) setting(
            'scorecard_criteria.archived_ok',
            'اتأرشف — النتائج القديمة تفضل تعرض درجته موسومًا «معيار مؤرشف».',
        ));
    }

    /** Undo — إعادة تفعيل معيار مؤرشف (13.4-د) */
    public function restore(Request $request, InterviewCriterion $criterion): RedirectResponse
    {
        $criterion->update(['is_archived' => false]);

        $this->audit($request, $criterion, 'scorecard_criteria.restored', ['is_archived' => true], ['is_archived' => false]);

        return back()->with('status', (string) setting('scorecard_criteria.restore_ok', 'رجع تاني ✓ — هيظهر للإدخال من جديد.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label_ar' => ['required', 'string', 'max:190'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'label_ar' => (string) setting('scorecard_criteria.field.label', 'اسم المعيار'),
        ]);
    }

    private function audit(Request $request, InterviewCriterion $criterion, string $action, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $criterion->getMorphClass(),
            'auditable_id' => $criterion->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
