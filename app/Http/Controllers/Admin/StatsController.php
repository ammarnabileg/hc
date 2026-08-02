<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\System\StatsService;
use App\Services\Admin\System\SvgChart;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * الإحصائيّات (12.8 · 24.3-خامسًا) — تقارير للعرض فقط.
 *
 * ⭐ الرسوم كلّها **SVG بأيدينا** — ممنوع أيّ مكتبة خارجيّة.
 * ⭐ والتاب الماليّ لا يظهر ولا يُحسَب لغير مالك المنصّة (عزل الحسّاس).
 */
class StatsController extends Controller
{
    public function __construct(
        private readonly StatsService $stats,
        private readonly SvgChart $chart,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tabs = $this->stats->tabsFor($user);
        $tab = $request->string('tab')->toString();

        if (! array_key_exists($tab, $tabs)) {
            $tab = (string) (array_key_first($tabs) ?: 'users');
        }

        $period = $this->stats->period(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->boolean('compare'),
        );

        return view('admin.stats.index', [
            'tabs' => $tabs,
            'tab' => $tab,
            'period' => $period,
            'data' => $this->stats->data($tab, $period),
            'chart' => $this->chart,
        ]);
    }

    /** تصدير مرن: CSV بالفترة والتاب المختارين وحدّ صفوف من الإعدادات */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $tabs = $this->stats->tabsFor($user);
        $tab = $request->string('tab')->toString();

        abort_unless(array_key_exists($tab, $tabs), 403, 'التاب ده مش متاح ليك.');

        $period = $this->stats->period(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->boolean('compare'),
        );

        $rows = $this->stats->exportRows($tab, $period);
        $filename = 'stats-'.$tab.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // BOM حتى تفتح العربيّة سليمةً في إكسل بلا خطوة إضافيّة من الأدمن
            fwrite($handle, "\xEF\xBB\xBF");

            if ($rows !== []) {
                fputcsv($handle, array_keys((array) $rows[0]));

                foreach ($rows as $row) {
                    fputcsv($handle, array_values((array) $row));
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
