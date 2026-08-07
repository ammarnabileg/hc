<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LeadershipCriterion;
use App\Models\LeadershipEvaluation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * معايير «مؤشّر القيادة» (Leadership Pulse) — الدستور 13.4-ن-د · 24 (التاب 3).
 *
 * «تقييم أسبوعي من 10 يمنحه الداونلاين لأبلاينه المباشر على بنود يحدّدها
 * الأدمن من لوحة الإدارة — قابلة للتعديل (2.13)». وكان `leadership_criteria`
 * **جدولًا بلا شاشة**: `LeadershipService::criteria()` تقرأ منه، وشاشة
 * التقييم الأسبوعيّة مبنيّة — ولا مسارَ ولا فيو ينشئ معيارًا واحدًا، فالبنك
 * يبقى فارغًا أبدًا و`submit()` يرفض بـ«مفيش معايير مفعَّلة حاليًّا».
 *
 * ⭐ الحذف سوفت بخيارين — نفس نمط `ScorecardCriteriaController` (13.4-د):
 * أرشفة («من الجديد فقط» — القديم يبقى بدرجته موسومًا) أو حذف نهائيّ («من
 * الجديد والقديم»). ولماذا؟ لأنّ `criteria_scores` في `leadership_evaluations`
 * **مفتاحها `key` المعيار نفسه**، فحذفه نهائيًّا يفقد تسميته في أيّ عرض تاريخيّ.
 */
class LeadershipCriteriaController extends Controller
{
    public function index(): View
    {
        $criteria = LeadershipCriterion::query()->orderBy('sort_order')->orderBy('id')->get();

        // استخدام حيّ من الـJSON — لا استعلامًا يفترض محرّك قاعدة بيانات بعينه
        $usage = LeadershipEvaluation::query()
            ->whereNotNull('criteria_scores')
            ->pluck('criteria_scores')
            ->reduce(function (array $counts, $scores) {
                foreach (array_keys((array) $scores) as $key) {
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }

                return $counts;
            }, []);

        return view('admin.leadership-criteria.index', [
            'criteria' => $criteria,
            'usage' => $usage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:48', 'regex:/^[a-z0-9_]+$/', 'unique:leadership_criteria,key'],
            'label_ar' => ['required', 'string', 'max:190'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'key' => (string) setting('leadership_criteria.field.key', 'المفتاح'),
            'label_ar' => (string) setting('leadership_criteria.field.label', 'اسم المعيار'),
        ]);

        $criterion = LeadershipCriterion::create($data + ['is_archived' => false]);

        $this->audit($request, $criterion, 'leadership_criteria.created', [], $criterion->only(['key', 'label_ar', 'weight', 'sort_order']));

        return redirect()
            ->route('admin.volunteer.leadership-criteria.index')
            ->with('status', (string) setting('leadership_criteria.save_ok', 'اتحفظ ✓ — المعيار هيظهر في تقييم الأسبوع الجاي.'));
    }

    /** ⭐ `key` لا يُعدَّل بعد الإنشاء — تاريخ `criteria_scores` مفتاحه به */
    public function update(Request $request, LeadershipCriterion $criterion): RedirectResponse
    {
        $data = $request->validate([
            'label_ar' => ['required', 'string', 'max:190'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'label_ar' => (string) setting('leadership_criteria.field.label', 'اسم المعيار'),
        ]);

        $old = $criterion->only(['label_ar', 'weight', 'sort_order']);
        $criterion->update($data);

        $this->audit($request, $criterion, 'leadership_criteria.updated', $old, $data);

        return redirect()
            ->route('admin.volunteer.leadership-criteria.index')
            ->with('status', (string) setting('leadership_criteria.save_ok', 'اتحفظ ✓ — المعيار هيظهر في تقييم الأسبوع الجاي.'));
    }

    /**
     * حذف سوفت بخيارين — `mode` من الفورم يحدّد الأثر (13.4-د أسلوبها):
     *  - `new_only` (افتراضيّ): أرشفة — يختفي من تقييم الأسبوع الجديد فقط،
     *    والقديم يبقى بمتوسّطه كما هو (`criteria_scores` مفتاحها ثابت).
     *  - `new_and_old`: حذفٌ فعليّ — لا رجعة، وتسمية المعيار تُفقَد من أيّ
     *    عرضٍ تاريخيّ يحاول ترجمة `key`.
     */
    public function destroy(Request $request, LeadershipCriterion $criterion): RedirectResponse
    {
        $mode = $request->string('mode', 'new_only')->value();

        if ($mode === 'new_and_old') {
            $this->audit($request, $criterion, 'leadership_criteria.deleted_everywhere', $criterion->only(['key', 'label_ar']), []);
            $criterion->delete();

            return back()->with('status', (string) setting(
                'leadership_criteria.deleted_everywhere_ok',
                'اتحذف نهائيًّا — اختفى من كلّ التقييمات، القديمة والجديدة.',
            ));
        }

        $criterion->update(['is_archived' => true]);
        $this->audit($request, $criterion, 'leadership_criteria.archived', ['is_archived' => false], ['is_archived' => true]);

        return back()->with('status', (string) setting(
            'leadership_criteria.archived_ok',
            'اتأرشف — التقييمات القديمة تفضل تعرض درجته موسومًا «معيار مؤرشف».',
        ));
    }

    /** Undo — إعادة تفعيل معيار مؤرشف */
    public function restore(Request $request, LeadershipCriterion $criterion): RedirectResponse
    {
        $criterion->update(['is_archived' => false]);

        $this->audit($request, $criterion, 'leadership_criteria.restored', ['is_archived' => true], ['is_archived' => false]);

        return back()->with('status', (string) setting('leadership_criteria.restore_ok', 'رجع تاني ✓ — هيظهر في التقييم من جديد.'));
    }

    private function audit(Request $request, LeadershipCriterion $criterion, string $action, array $old, array $new): void
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
