<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\WalletBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * شاشات المحفظة للمتدرّب (19 · 24.5-ب).
 * كلّ ما هنا يخصّ صاحب الحساب وحده، والجداول مفلترة على جانب التدريب فقط.
 */
class WalletController extends Controller
{
    /** أسماء مصادر الحركة بالعربيّة — تُعرَض في عمود «النوع» */
    public const SOURCE_LABELS = [
        'topup' => 'شحن رصيد',
        'purchase' => 'شراء',
        'referral' => 'عمولة دعوة',
        'academy' => 'تعلّم',
        'task' => 'مهمّة',
        'meeting' => 'اجتماع',
        'behavior' => 'سلوك',
        'leadership' => 'قيادة',
        'transfer' => 'حوالة',
        'exchange' => 'تحويل عملة',
        'admin' => 'إجراء إداريّ',
    ];

    /** 🖥️ رصيدي وشحن — الرصيد بعدّاد تصاعديّ وآخر 5 حركات */
    public function index(Request $request)
    {
        $user = $request->user();
        $mainCode = (string) setting('topup.credit_currency', 'coins');

        $main = Currency::query()->where('code', $mainCode)->first();

        // ثلاثة كروت ثانويّة بحدّ أقصى — والحدّ الأعلى أربعة في الشاشة (2.15-أ-3)
        $secondary = Currency::query()
            ->where('layer', 'training')
            ->where('is_active', true)
            ->where('code', '!=', $mainCode)
            ->orderBy('id')
            ->take(3)
            ->get();

        $balances = $this->balancesFor($request->user()->id);

        $recent = Transaction::query()
            ->where('user_id', $user->id)
            ->where('layer', 'training')
            ->with('currency')
            ->latest('id')
            ->take(5)
            ->get();

        return view('wallet.index', [
            'main' => $main,
            'mainBalance' => $balances[$main?->id] ?? 0.0,
            'secondary' => $secondary,
            'balances' => $balances,
            'recent' => $recent,
        ]);
    }

    /** 🖥️ التذاكر 🎟️ — الرصيد ومصادر الكسب ومواضع الصرف (7.1) */
    public function tickets(Request $request)
    {
        $currency = Currency::query()->where('code', 'tickets')->first();
        $balances = $this->balancesFor($request->user()->id);

        $recent = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->when($currency, fn ($q) => $q->where('currency_id', $currency->id))
            ->latest('id')
            ->take(5)
            ->get();

        return view('wallet.tickets', [
            'currency' => $currency,
            'balance' => $balances[$currency?->id] ?? 0.0,
            // مصادر الكسب ومواضع الصرف من الإعدادات — فتتغيّر من لوحة الأدمن بلا نشر (2.13)
            'earnSources' => (array) setting('wallet.tickets.earn_sources', [
                'إكمال درس قبل نصف الديدلاين',
                'إكمال ستريك 7 أيّام متواصلة',
                'الدعوات: تذكرة للداعي وتذكرة للمدعوّ',
                'الاختبار التمهيديّ ومفاجآت الرسائل الإيجابيّة',
            ]),
            'spendTargets' => (array) setting('wallet.tickets.spend_targets', [
                'دخول الامتحان النهائيّ للتدريب',
                'استخراج السيرة الذاتيّة',
                'تجميد الستريك ليومٍ فايت',
                'الألعاب وحروب التركيز',
            ]),
            'recent' => $recent,
        ]);
    }

    /** 🖥️ المعاملات والفواتير — جدول مفلتر على جانب التدريب فقط */
    public function transactions(Request $request)
    {
        $filters = $this->filters($request);

        $rows = $this->transactionsQuery($request)
            ->with('currency')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('wallet.transactions', [
            'rows' => $rows,
            'filters' => $filters,
            'currencies' => Currency::query()->where('layer', 'training')->where('is_active', true)->get(),
            'sources' => $this->availableSources($request->user()->id),
        ]);
    }

    /** [تصدير كشف CSV] — بنفس الفلاتر الظاهرة على الشاشة */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->transactionsQuery($request)->with('currency')->latest('id');
        $filename = 'wallet-statement-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // BOM حتى تفتح العربيّة سليمةً في إكسل
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['التاريخ', 'النوع', 'العملة', 'القيمة', 'المرجع', 'الرصيد بعدها']);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $row) {
                    fputcsv($out, [
                        $row->created_at?->format('Y-m-d H:i'),
                        self::SOURCE_LABELS[$row->source] ?? $row->source,
                        $row->currency?->name_ar,
                        (float) ($row->applied_amount ?? $row->amount),
                        $row->reason,
                        (float) $row->balance_after,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<int, float> */
    private function balancesFor(int $userId): array
    {
        return WalletBalance::query()
            ->where('user_id', $userId)
            ->pluck('balance', 'currency_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    private function filters(Request $request): array
    {
        // المدى الافتراضيّ آخر 30 يومًا (2.15-د)
        $days = (int) setting('ux.lists.default_range_days', 30);

        return [
            'currency' => $request->string('currency')->toString(),
            'source' => $request->string('source')->toString(),
            'from' => $request->filled('from')
                ? $request->date('from')
                : Carbon::now()->subDays($days)->startOfDay(),
            'to' => $request->filled('to') ? $request->date('to') : null,
            'q' => $request->string('q')->toString(),
            'all_time' => $request->boolean('all_time'),
        ];
    }

    private function transactionsQuery(Request $request)
    {
        $filters = $this->filters($request);

        return Transaction::query()
            ->where('user_id', $request->user()->id)
            // ⭐ جانب التدريب فقط — حركات التطوّع لها شاشتها في لوحة التطوّع (13.4-ن)
            ->where('layer', 'training')
            ->when($filters['currency'], fn ($q, $code) => $q->whereHas('currency', fn ($c) => $c->where('code', $code)))
            ->when($filters['source'], fn ($q, $source) => $q->where('source', $source))
            ->when(! $filters['all_time'] && $filters['from'], fn ($q) => $q->where('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->where('created_at', '<=', $filters['to']))
            ->when($filters['q'], fn ($q, $term) => $q->where('reason', 'like', '%'.$term.'%'));
    }

    /** @return array<string, string> */
    private function availableSources(int $userId): array
    {
        $used = Transaction::query()
            ->where('user_id', $userId)
            ->where('layer', 'training')
            ->distinct()
            ->pluck('source')
            ->all();

        $map = [];

        foreach ($used as $source) {
            $map[$source] = self::SOURCE_LABELS[$source] ?? $source;
        }

        return $map;
    }
}
