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

        /*
         | ⭐ حزامٌ وحمّالة مع حارس المسار: الباب يقبل **أيّ** مفتاح تقارير،
         | فمَن لا تابَّ له لا يصل أصلًا. ولو وصل يومًا (تغيُّر مصفوفة أو
         | استثناءٌ فرديّ) فلا يُفتَح له تابٌّ افتراضيّ لا يملكه — يُردّ.
         */
        abort_if($tabs === [], 403, (string) setting('stats.forbidden.message', 'ليس لديك صلاحيّة الوصول لهذه الصفحة.'));

        $tab = $request->string('tab')->toString();

        if (! array_key_exists($tab, $tabs)) {
            $tab = (string) array_key_first($tabs);
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
            /*
             | ⭐ **قائمة أعمدة بوب-أب [تصدير]** (24.3-خامسًا) — من `StatsService`
             | نفسها لا من قائمةٍ ثانية في القالب: قائمةٌ ثانية تنسى عمودًا جديدًا
             | فيظهر في الملفّ ولا يظهر في الاختيار (أو العكس).
             */
            'exportColumns' => $this->stats->exportColumns($tab),
            /*
             | أزرار «تصدير CSV/Excel/PDF» — اللافتات إعدادٌ لا نصٌّ محروق (2.13)،
             | ولو غاب الصفّ **لا تختفي الأزرار بصمت**: تظهر بمفاتيح الصيغ نفسها،
             | فيرى الأدمن أنّ لافتةً نقصت بدل أن تختفي قدرةٌ منصوصة (2.17-ب).
             */
            'exportFormats' => setting('stats.export.formats')
                ?: array_combine(TabularExport::FORMATS, TabularExport::FORMATS),
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

        abort_unless(array_key_exists($tab, $tabs), 403, (string) setting('stats.admin.export_denied', 'التاب ده مش متاح ليك.'));

        $period = $this->stats->period(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->boolean('compare'),
        );

        $label = (string) ($tabs[$tab]['label'] ?? $tab);

        /*
         | ⭐ **«[تصدير] الصيغة + الأعمدة المختارة + الفترة + Toggle ضمّ المقارنة»**
         | (24.3-خامسًا). الاختيار يصل `columns[]` من البوب-أب، ويصفّيه
         | `StatsService` بقائمته هو — فلا يدخل الملفَّ عمودٌ لم يُعرَض للاختيار،
         | ولا يخرج ملفٌّ بلا أعمدة إن وصل الرابط بلا اختيارٍ أصلًا (الكلّ).
         */
        $columns = $request->has('columns')
            ? array_map('strval', array_filter((array) $request->input('columns'), 'is_scalar'))
            : null;

        $file = $export->build(
            rows: $this->stats->exportRows($tab, $period, $columns),
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
