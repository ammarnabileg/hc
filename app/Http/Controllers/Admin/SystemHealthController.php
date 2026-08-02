<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Ops\BackupManager;
use App\Services\Admin\Ops\OpsAudit;
use App\Services\Admin\Ops\OpsSettings;
use App\Services\Admin\Ops\SystemHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * النسخ الاحتياطيّ وصحّة النظام (12.7-و).
 *
 * سؤال الشاشة الواحد: **هل النظام سليم، وهل معايا نسخة أقدر أرجع لها؟**
 * وكلّ مؤشّر هنا **بلون ورمز** لأنّ اللون وحده لا يحمل المعنى (2.16-ب).
 */
class SystemHealthController extends Controller
{
    public function __construct(
        private readonly SystemHealth $health,
        private readonly BackupManager $backups,
        private readonly OpsAudit $audit,
        private readonly OpsSettings $settings,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString() ?: 'health';
        $tab = in_array($tab, ['health', 'backups', 'schedule'], true) ? $tab : 'health';

        $report = $this->health->report();

        return view('admin.ops.system', [
            'tab' => $tab,
            'report' => $report,
            'overall' => $this->health->overallState($report),
            'alerts' => $this->health->alerts($report),
            'lastCheck' => $this->health->lastCheckAt(),
            // تحميل كسول للتابات (2.15-ب): جدول النسخ لا يُبنى إلّا في تابه
            'backups' => $tab === 'backups'
                ? $this->backups->list(
                    (int) setting('backups.admin.per_page', 15),
                    $request->string('kind')->toString() ?: null,
                    $request->string('status')->toString() ?: null,
                )
                : null,
            'kinds' => $this->backups->kinds(),
            'frequencies' => $this->backups->frequencies(),
            'schedule' => $this->backups->schedule(),
            'logs' => $tab === 'schedule'
                ? $this->audit->feed(OpsAudit::SYSTEM_ACTIONS, (int) setting('backups.audit_per_page', 10))
                : null,
        ]);
    }

    /** [تشغيل فحص صحّة] — ويطلق التنبيهات الاستباقيّة عند تجاوز العتبات */
    public function runCheck(Request $request): RedirectResponse
    {
        $result = $this->health->runCheck($request->user());

        return back()->with('status', $result['alerts'] === []
            ? 'الفحص خلص — كلّ حاجة تمام ✓'
            : 'الفحص خلص — في '.count($result['alerts']).' تنبيه محتاج نظرة، و'.$result['notified'].' إشعار اتبعت.');
    }

    /** تصدير تقرير الصحّة وتنبيهاته (صلاحيّة `system_health.export`) */
    public function exportHealth(): StreamedResponse
    {
        $report = $this->health->report();

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['المؤشّر', 'القيمة', 'الحالة', 'الشرح']);

            foreach ($report as $row) {
                fputcsv($handle, [
                    $row['label'],
                    $row['value'],
                    state_color($row['state'])['label'].' '.state_color($row['state'])['icon'],
                    $row['hint'],
                ]);
            }

            fclose($handle);
        }, 'system-health-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** [نسخة احتياطيّة الآن] — قاعدة البيانات + ملفّات التخزين في ملفّ واحد */
    public function createBackup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'max:16'],
        ]);

        $result = $this->backups->create($data['kind'], $request->user());

        return back()->with('status', $result['message']);
    }

    public function download(Request $request, int $backup): BinaryFileResponse|RedirectResponse
    {
        $row = $this->backups->find($backup);

        if (! $row || ! is_file($this->backups->pathOf($row))) {
            return back()->withErrors(['backup' => 'الملفّ ده مش موجود على القرص — يمكن اتمسح من الخادم.']);
        }

        $this->backups->markDownloaded($row, $request->user());

        return response()->download($this->backups->pathOf($row), $row->filename);
    }

    public function destroy(Request $request, int $backup): RedirectResponse
    {
        return $this->backups->delete($backup, $request->user())
            ? back()->with('status', 'النسخة اتمسحت.')
            : back()->withErrors(['backup' => 'النسخة دي مش موجودة أصلًا.']);
    }

    /** جدولة النسخ الدوريّة: الدوريّة · الساعة · النوع · عدد النسخ المحفوظة */
    public function saveSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'frequency' => ['required', 'string', 'max:16'],
            'time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'kind' => ['required', 'string', 'max:16'],
            'keep' => ['required', 'integer', 'min:1', 'max:'.(int) setting('backups.keep_max', 90)],
        ]);

        $actor = $request->user();
        $before = $this->backups->schedule();

        $this->settings->save('backups.schedule.enabled', $request->boolean('enabled'), $actor);
        $this->settings->save('backups.schedule.frequency', $data['frequency'], $actor);
        $this->settings->save('backups.daily_time', $data['time'], $actor);
        $this->settings->save('backups.default_kind', $data['kind'], $actor);
        $this->settings->save('backups.keep_count', $data['keep'], $actor);

        $this->audit->record($actor, 'ops.backups.schedule_updated', [
            'frequency' => $data['frequency'],
            'time' => $data['time'],
            'keep' => $data['keep'],
        ], 'settings', 0, ['frequency' => $before['frequency'], 'keep' => $before['keep']]);

        // الحذف الفوريّ للزائد عن العدد الجديد — فالإعداد يسري لحظيًّا لا في الدورة القادمة
        $this->backups->prune();

        return back()->with('status', 'الجدولة اتحفظت ✓');
    }
}
