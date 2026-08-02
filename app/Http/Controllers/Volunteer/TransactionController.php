<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Objection;
use App\Models\Transaction;
use App\Services\Volunteer\Meetings\MeetingLedger;
use App\Services\Volunteer\Meetings\MeetingScope;
use App\Services\Volunteer\Objections\ObjectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * معاملاتي — Rep / VXP (الدستور 13.4-ط · 13.4-ن · 24.4).
 *
 * كشف موحّد بكلّ حركة على درجتي ونقاطي بسببها ومرجعها،
 * **مفلترًا على جانب التطوّع وحده** — الجدول واحد والعرض مفلتر (13.4-ط).
 */
class TransactionController extends Controller
{
    /** الطبقة التي لا تُعرَض هنا غيرها */
    private const LAYER = MeetingLedger::LAYER;

    public function __construct(
        private readonly ObjectionService $objections,
        private readonly MeetingLedger $ledger,
        private readonly MeetingScope $scope,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'type' => $request->string('type')->toString(),      // rep · vxp
            'source' => $request->string('source')->toString(),  // task · meeting · academy · leadership · behavior · arbitration
            'days' => (int) $request->integer('days', (int) setting('ux.lists.default_range_days', 30)),
            'entity' => (int) $request->integer('entity'),
            'objectable' => $request->boolean('objectable'),
            'q' => trim($request->string('q')->toString()),
        ];

        $currencies = Currency::query()->whereIn('code', ['rep', 'vxp'])->get()->keyBy('code');

        $rows = Transaction::query()
            ->with('currency', 'created_by')
            ->where('user_id', $user->id)
            // ⭐ الجدول الموحّد مفلتر على جانب التطوّع فقط
            ->where('layer', self::LAYER)
            ->where('created_at', '>=', now()->subDays(max(1, $filters['days'])))
            ->when($filters['type'] !== '' && isset($currencies[$filters['type']]),
                fn ($q) => $q->where('currency_id', $currencies[$filters['type']]->id))
            ->when($filters['source'] !== '', fn ($q) => $q->where('source', $filters['source']))
            ->when($filters['entity'] > 0, fn ($q) => $q->where('entity_id', $filters['entity']))
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reason', 'like', '%'.$filters['q'].'%')
                ->orWhere('reference_id', $filters['q'])))
            ->orderByDesc('created_at')
            ->limit((int) setting('volunteer.transactions.max_rows', 200))
            ->get();

        $objections = $this->objectionsFor($rows);

        if ($filters['objectable']) {
            $rows = $rows->filter(fn (Transaction $t) => $this->objections->withinWindow($t) && ! isset($objections[$t->id]))->values();
        }

        return view('volunteer.transactions.index', [
            'rows' => $rows,
            'objections' => $objections,
            'filters' => $filters,
            'sources' => $this->sources(),
            'entities' => Entity::query()->whereIn('id', $this->scope->entityIdsWithAncestors($user))->orderBy('name_ar')->get(),
            'rep' => round($this->ledger->balance($user, 'rep'), 2),
            'vxp' => round($this->ledger->balance($user, 'vxp'), 2),
            'repMin' => (float) ($currencies['rep']->min_value ?? 0),
            'repMax' => (float) ($currencies['rep']->max_value ?? 0),
            'service' => $this->objections,
            'windowDays' => $this->objections->windowDays(),
        ]);
    }

    /** تصدير كشف — للمتطوّع عن نفسه (13.4-ط) */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $days = (int) $request->integer('days', (int) setting('ux.lists.default_range_days', 30));

        $rows = Transaction::query()
            ->with('currency')
            ->where('user_id', $user->id)
            ->where('layer', self::LAYER)
            ->where('created_at', '>=', now()->subDays(max(1, $days)))
            ->orderByDesc('created_at')
            ->get();

        $filename = 'transactions-'.$user->code.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            // BOM ليفتح الملفّ عربيًّا سليمًا في إكسل
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['التاريخ', 'النوع', 'القيمة', 'المطبَّق', 'السبب', 'المصدر', 'المرجع', 'تخطّت الحدّ اليوميّ', 'مصحِّحة']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->created_at?->format('Y-m-d H:i'),
                    $row->currency?->name_ar,
                    $row->amount,
                    $row->applied_amount,
                    $row->reason,
                    $row->source,
                    $row->reference_id,
                    $row->exceeded_daily_cap ? 'نعم' : 'لا',
                    $row->is_correction ? 'نعم' : 'لا',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return Collection<int,Objection> مفهرسة بمعرّف المعاملة */
    private function objectionsFor(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        return Objection::query()
            ->whereIn('transaction_id', $rows->pluck('id'))
            ->get()
            ->keyBy('transaction_id');
    }

    /** مصادر المعاملات كما في فلتر الشاشة (24.4) */
    private function sources(): array
    {
        return [
            'task' => 'مهامّ',
            'meeting' => 'اجتماعات',
            'academy' => 'أكاديمية',
            'leadership' => 'مؤشّر القيادة',
            'behavior' => 'سلوك',
            'arbitration' => 'قرار محكّم',
        ];
    }
}
