<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\System\StatsService;
use App\Services\Admin\System\SvgChart;
use App\Services\Export\TabularExport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

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

    /**
     * ⭐ **تصدير مرن: `تصدير CSV/Excel/PDF`** — بالنصّ لا بالتسمية (24.3-خامسًا · 12.8).
     *
     * كان الزرّ واحدًا يقول «تصدير CSV»، والنصّ الحاكم يوجب الصيغ الثلاث:
     * «**الهيدر:** «الإحصائيّات» + فلتر فترة عامّ … + `**تصدير CSV/Excel/PDF**`».
     * والصيغتان الأخريان تُبنيان **داخل المشروع بلا مكتبة خارجيّة** عبر
     * `App\Services\Export\TabularExport` (XLSX من `XlsxWriter` · PDF من
     * `AtsPdfWriter`) — فلا يُكتَب على الزرّ ما لا يقع.
     */
    public function export(Request $request, TabularExport $export): Response
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

        $label = (string) ($tabs[$tab]['label'] ?? $tab);

        $file = $export->build(
            rows: $this->stats->exportRows($tab, $period),
            format: $request->string('format')->toString(),
            title: (string) setting('stats.export.title_prefix', 'الإحصائيّات').' — '.$label,
            subtitle: $period['from']->format('Y/m/d').' — '.$period['to']->format('Y/m/d'),
            slug: 'stats-'.$tab,
        );

        return response($file['content'], 200, array_filter([
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="'.$file['name'].'"',
            // الصيغة التي خرجت فعلًا — وسببُ اختلافها عن المطلوب إن اختلفت (2.17-ب)
            'X-Export-Format' => $file['format'],
            'X-Export-Note' => $file['note'] !== '' ? rawurlencode($file['note']) : null,
        ]));
    }
}
