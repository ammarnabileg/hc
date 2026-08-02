<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Ops\OpsAudit;
use App\Services\Admin\Ops\UpdateManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * التحديثات والترحيل (12.7-هـ): **نقرة آمنة + Dry-run + استرجاع**.
 *
 * ⛔ القاعدة التي لا تُخترق: **ممنوع أيّ تنفيذ بلا تأكيد**. والتأكيد هنا مزدوج
 *    (عبارة مكتوبة بيد الأدمن + إقرار صريح) لأنّ زرًّا واحدًا بين قاعدة بيانات
 *    سليمة وأخرى مكسورة ليس حاجزًا كافيًا.
 */
class UpdatesController extends Controller
{
    public function __construct(
        private readonly UpdateManager $updates,
        private readonly OpsAudit $audit,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.ops.updates', $this->shared($request, 'overview') + [
            'dryRun' => $request->session()->get('ops.dry_run'),
            'logs' => $this->audit->feed(OpsAudit::UPDATE_ACTIONS, (int) setting('updates.audit_per_page', 10)),
            'history' => null,
        ]);
    }

    /** سجلّ الإصدارات في تاب مستقلّ — تحميل كسول لا يُبنى إلّا عند فتحه (2.15-ب) */
    public function history(Request $request): View
    {
        return view('admin.ops.updates', $this->shared($request, 'history') + [
            'dryRun' => null,
            'logs' => null,
            'history' => $this->updates->history((int) setting('updates.history_per_page', 20)),
        ]);
    }

    /**
     * ما تشترك فيه التابّان — والفحوص القبليّة وجدول البصمات جزءٌ من الشاشة
     * لا زرٌّ مخفيّ: المالك لازم يشوف **قبل** أن يضغط (12.7-هـ).
     */
    private function shared(Request $request, string $tab): array
    {
        $pending = $this->updates->pending();
        $checks = $this->updates->preflightChecks();

        return [
            'tab' => $tab,
            'version' => $this->updates->currentVersion(),
            'pending' => $pending,
            'applied' => $this->updates->appliedCount(),
            'lastBatch' => $this->updates->lastBatch(),
            'hasFreshDryRun' => $this->updates->hasFreshDryRun($request->user(), $pending),
            'confirmPhrase' => (string) setting('updates.confirm_phrase', 'تنفيذ'),
            'rollbackPhrase' => (string) setting('updates.rollback_confirm_phrase', 'استرجاع'),
            'restorePhrase' => (string) setting('updates.restore_confirm_phrase', 'استعادة'),
            'checks' => $checks,
            'preflightOk' => $this->updates->preflightPasses($checks),
            'lock' => $this->updates->lockState(),
            'ledger' => $this->updates->ledgerRows((int) setting('updates.ledger_per_page', 15)),
            'runs' => $this->updates->runs((int) setting('updates.runs_per_page', 5)),
            'lastFailure' => $this->updates->lastFailure(),
        ];
    }

    /** [فحص المايجريشنز المعلّقة] — يعرضها بالاسم، ولا ينفّذ حرفًا */
    public function check(Request $request): RedirectResponse
    {
        $pending = $this->updates->pending();

        return back()->with('status', $pending === []
            ? 'إنت على أحدث إصدار ✓ — مافيش هجرات معلّقة.'
            : 'في '.count($pending).' هجرة معلّقة — أسماؤها ظاهرة تحت.');
    }

    /** [Dry-run] — يعرض ما سيُنفَّذ بالضبط **بلا تنفيذ**، وهو تصريح الدخول للتنفيذ */
    public function dryRun(Request $request): RedirectResponse
    {
        $result = $this->updates->dryRun($request->user());

        return back()
            ->with('ops.dry_run', $result)
            ->with('status', 'Dry-run خلص — ده بالظبط اللي هيتنفّذ، ولسه مافيش حاجة اتغيّرت.');
    }

    /**
     * [تنفيذ] — بتأكيد مزدوج، وبخطّ 2.11 كامل خلفه: قفل · فحوص قبليّة · صيانة ·
     * نسخة متحقَّق منها · هجرة هجرة بتحقّق · بذور · رفع إصدار · خروج من الصيانة.
     * والفشل لا يرمي استثناءً بل **يرجع بتقرير** — لأنّ المالك يحتاج أن يقرأ
     * ماذا حدث وماذا يفعل، لا أن يرى شاشة خطأ (2.17).
     */
    public function migrate(Request $request): RedirectResponse
    {
        $this->requireConfirmation($request, (string) setting('updates.confirm_phrase', 'تنفيذ'));

        $pending = $this->updates->pending();

        if ($pending === []) {
            return back()->with('status', 'مافيش هجرات معلّقة — مالناش شغل هنا.');
        }

        if (! $this->updates->hasFreshDryRun($request->user(), $pending)) {
            throw ValidationException::withMessages([
                'confirm' => 'شغّل Dry-run الأوّل — التنفيذ بلا معاينة ممنوع من الإعدادات.',
            ]);
        }

        $result = $this->updates->run($request->user());

        if (! $result['ok']) {
            return back()
                ->with('ops.failure', $result['report'])
                ->with('status', $result['message']);
        }

        return back()->with('status', $result['message']);
    }

    /** [الفحوص القبليّة] — تشغيلها بنفسها لا يلمس شيئًا، وهي شرط بدء التحديث (2.11-ب) */
    public function preflight(Request $request): RedirectResponse
    {
        $checks = $this->updates->preflightChecks();

        return back()->with('status', $this->updates->preflightPasses($checks)
            ? 'كلّ الفحوص القبليّة عدّت ✓ — تقدر تكمّل.'
            : 'في فحص قبليّ ما عدّاش — راجع القائمة تحت قبل ما تحدّث.');
    }

    /**
     * [استعادة من نسخة احتياطيّة] — زرّ ما بعد الفشل (12.7-هـ).
     * بعبارة تأكيد مكتوبة كذلك، فالاستعادة تكتب فوق البيانات الحاليّة.
     */
    public function restore(Request $request, int $backup): RedirectResponse
    {
        $this->requireConfirmation($request, (string) setting('updates.restore_confirm_phrase', 'استعادة'));

        $result = $this->updates->restoreFromBackup($backup, $request->user());

        if (! $result['ok']) {
            throw ValidationException::withMessages(['confirm' => $result['message']]);
        }

        return back()->with('status', $result['message']);
    }

    /** [استرجاع] آخر دفعة — والتحذير بما سيُفقَد معروض في الشاشة قبل الضغط */
    public function rollback(Request $request): RedirectResponse
    {
        if (! setting('updates.rollback_enabled', true)) {
            throw ValidationException::withMessages(['confirm' => 'الاسترجاع متوقّف من الإعدادات.']);
        }

        $this->requireConfirmation($request, (string) setting('updates.rollback_confirm_phrase', 'استرجاع'));

        if ($this->updates->lastBatch() === []) {
            return back()->with('status', 'مافيش دفعة نسترجعها.');
        }

        $result = $this->updates->rollback($request->user());

        return back()->with('status', 'اترجعت '.count($result['rolled']).' هجرة — راجع الجدول واتأكّد.');
    }

    public function recordVersion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:32', 'regex:/^v?\d+\.\d+\.\d+$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->updates->recordVersion($data['version'], $data['notes'] ?? null, $request->user());

        if (! $result['saved']) {
            throw ValidationException::withMessages(['version' => $result['message']]);
        }

        return back()->with('status', $result['message']);
    }

    /** تصدير سجلّ الإصدارات — بلا مكتبة، سطر CSV بسطر */
    public function exportHistory(): StreamedResponse
    {
        $rows = $this->updates->allHistory();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['الإصدار', 'السابق', 'النوع', 'عدد الهجرات', 'مَن نفّذ', 'التاريخ', 'ملاحظات']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->version,
                    $row->previous_version,
                    $row->event,
                    $row->migrations_count,
                    $row->performer_name ?? '—',
                    $row->performed_at,
                    $row->notes,
                ]);
            }

            fclose($handle);
        }, 'version-history-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * ⛔ التأكيد المزدوج: عبارة يكتبها الأدمن بيده + إقرار صريح.
     * ولا يُنفَّذ شيء قبل أن يمرّ الطلب من هنا سالمًا.
     */
    private function requireConfirmation(Request $request, string $phrase): void
    {
        $request->validate([
            'confirm' => ['required', 'string'],
            'understood' => ['accepted'],
        ], [
            'confirm.required' => 'اكتب كلمة التأكيد الأوّل.',
            'understood.accepted' => 'لازم تقرّ إنّك فاهم الأثر قبل التنفيذ.',
        ]);

        if (trim((string) $request->input('confirm')) !== $phrase) {
            throw ValidationException::withMessages([
                'confirm' => "كلمة التأكيد غلط — اكتب «{$phrase}» بالظبط.",
            ]);
        }
    }
}
