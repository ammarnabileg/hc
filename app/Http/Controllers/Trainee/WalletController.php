<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Models\WalletWithdrawal;
use App\Services\Gamification\TicketsAccount;
use App\Services\Wallet\ExchangeRates;
use App\Services\Wallet\ExchangeService;
use App\Services\Wallet\TransferService;
use App\Services\Wallet\WithdrawService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * شاشات المحفظة للمتدرّب (19 · 24.5-ب).
 * كلّ ما هنا يخصّ صاحب الحساب وحده، والجداول مفلترة على جانب التدريب فقط.
 */
class WalletController extends Controller
{
    /**
     * أسماء مصادر الحركة بالعربيّة — تُعرَض في عمود «النوع».
     *
     * ⚠️ ميثودٌ لا `const`: الثابت لا يقبل استدعاء دالّة، فنصُّه يبقى **محروقًا**
     * مهما فعلنا (2.13-أ). والمفتاح هنا `wallet.source.<الكود>` — الكود مفتاح
     * داخليّ لا يُترجَم، والاسمُ وحده هو ما يقرؤه المستخدم فهو وحده الذي يُنقَل.
     *
     * @return array<string, string>
     */
    public static function sourceLabels(): array
    {
        return [
            'topup' => (string) setting('wallet.source.topup', 'شحن رصيد'),
            'purchase' => (string) setting('wallet.source.purchase', 'شراء'),
            'referral' => (string) setting('wallet.source.referral', 'عمولة دعوة'),
            'academy' => (string) setting('wallet.source.academy', 'تعلّم'),
            // XP نادي الخامسة صار يمرّ بالدفتر الموحّد (7.2 · 7.3) — فيلزمه اسمٌ عربيّ
            'streak' => (string) setting('wallet.source.streak', 'نادي الخامسة'),
            // حركات الحروب صار مصدرها مفتاحًا بعدما كانت جملةً في خانة المصدر (24.2)
            'challenge' => (string) setting('wallet.source.challenge', 'الحروب'),
            'task' => (string) setting('wallet.source.task', 'مهمّة'),
            'meeting' => (string) setting('wallet.source.meeting', 'اجتماع'),
            'behavior' => (string) setting('wallet.source.behavior', 'سلوك'),
            'leadership' => (string) setting('wallet.source.leadership', 'قيادة'),
            'transfer' => (string) setting('wallet.source.transfer', 'حوالة'),
            'exchange' => (string) setting('wallet.source.exchange', 'تحويل عملة'),
            'withdraw' => (string) setting('wallet.source.withdraw', 'سحب أرباح'),
            'admin' => (string) setting('wallet.source.admin', 'إجراء إداريّ'),
        ];
    }

    /** الكروت الثانويّة الثلاثة كما ينصّ 19.2 بالحرف: التذاكر / XP / الساعات */
    public const SECONDARY_CURRENCIES = ['tickets', 'xp', 'hours'];

    /**
     * ⭐ عمود **«من ← إلى»** كما ينصّ 19.2 بالحرف.
     *
     * لماذا يُشتقّ ولا يُخزَّن؟ لأنّ الجدول الموحّد يسجّل **طرفًا واحدًا** لكلّ حركة
     * (صاحب المحفظة) وإشارةَ المبلغ؛ فالطرف الآخر يُقرَأ من مصدر الحركة ومرجعها.
     * وكان العمود المعروض اسمه «المرجع» بينما يعرض **السبب** — عنوانٌ مضلِّل.
     *
     * @return array{from:string,to:string}
     */
    public static function flowOf(Transaction $row): array
    {
        $mine = (string) setting('wallet.flow.self_label', 'محفظتي');
        $counterpart = (string) (
            self::sourceLabels()[$row->source] ?? setting('wallet.flow.platform_label', 'المنصّة')
        );

        // الحوالة تحمل الطرف الآخر في نصّ سببها («حوالة إلى U…» / «حوالة من U…») — 19.3
        if ($row->source === 'transfer' && $row->reason) {
            $counterpart = trim(str_replace(
                (array) setting('wallet.flow.transfer_prefixes', ['حوالة إلى', 'حوالة من']),
                '',
                (string) $row->reason,
            )) ?: $counterpart;
        }

        return (float) ($row->applied_amount ?? $row->amount) < 0
            ? ['from' => $mine, 'to' => $counterpart]
            : ['from' => $counterpart, 'to' => $mine];
    }

    /** عمود **«ملاحظات»** (19.2): ما يحتاج المستخدم معرفته عن الحركة بلا فتح البانل */
    public static function notesOf(Transaction $row): string
    {
        $notes = [];

        if ($row->exceeded_daily_cap) {
            $notes[] = (string) setting('wallet.notes.capped', 'تعدّت الحدّ اليوميّ — اتطبّق منها المسموح.');
        }

        if ($row->is_correction) {
            $notes[] = (string) setting('wallet.notes.correction', 'حركة تصحيح موثّقة.');
        }

        if ($note = ($row->meta['note'] ?? null)) {
            $notes[] = (string) $note;
        }

        return implode(' · ', $notes);
    }

    public function __construct(
        private readonly TransferService $transfers,
        private readonly ExchangeService $exchanges,
        private readonly WithdrawService $withdrawals,
        private readonly ExchangeRates $rates,
        // ⭐ التذاكر: مصدرٌ واحد لكلّ رقمٍ يُعرَض — رصيدًا ومكتسبًا ومصروفًا (7.1)
        private readonly TicketsAccount $tickets,
    ) {}

    /** 🖥️ رصيدي وشحن — الرصيد بعدّاد تصاعديّ وآخر 5 حركات */
    public function index(Request $request)
    {
        $user = $request->user();
        $mainCode = (string) setting('topup.credit_currency', 'coins');

        $main = Currency::query()->where('code', $mainCode)->first();

        /*
         | ثلاثة كروت ثانويّة بالضبط: التذاكر / XP / **الساعات** (19.2).
         | وترتيبها ثابت كنصّ الدستور لا بترتيب الـid، فلا يتبدّل بإضافة عملةٍ جديدة.
         */
        $secondary = Currency::query()
            ->whereIn('code', self::SECONDARY_CURRENCIES)
            ->where('is_active', true)
            ->get()
            // 🔒 Toggle الإظهار في المحفظة لعملة Hours وحدها (24 القسم 12 · finance.hours.*)
            ->reject(fn ($c) => $c->code === 'hours' && ! setting('finance.hours.show_in_wallet', true))
            ->sortBy(fn ($c) => array_search($c->code, self::SECONDARY_CURRENCIES, true))
            ->values();

        $balances = $this->balancesFor($request->user()->id);

        $recent = Transaction::query()
            ->where('user_id', $user->id)
            ->where('layer', 'training')
            ->with('currency')
            ->latest('id')
            ->take((int) setting('wallet.recent_rows', 5))
            ->get();

        return view('wallet.index', array_merge([
            'main' => $main,
            'mainBalance' => $balances[$main?->id] ?? 0.0,
            'secondary' => $secondary,
            'balances' => $balances,
            'recent' => $recent,
        ], $this->operationsData($request)));
    }

    /**
     * 🖥️ تاب «المسحوبات» (19.2): «متاح للسحب» + زرّ سحب + جدول المسحوبات
     * ومنه عمود **صورة الفاتورة**.
     */
    public function withdrawals(Request $request)
    {
        $user = $request->user();
        $perPage = max((int) setting('wallet.transactions.per_page', 20), 1);

        $total = $this->withdrawalsQuery($user)->count();
        $rows = $this->withdrawalsQuery($user)->skip(0)->take($perPage)->get();

        return view('wallet.withdrawals', array_merge([
            'rows' => $rows,
            'methods' => WithdrawService::methods(),
            'hasMore' => $rows->count() < $total,
            'nextOffset' => $perPage,
            'pageSize' => $perPage,
        ], $this->operationsData($request)));
    }

    /** ⭐ تمرير تدريجيّ (13.1 · قرار §25 — ⛔ ممنوع ترقيم الصفحات): شريحة Fragment وحدها */
    public function withdrawalsMore(Request $request)
    {
        $user = $request->user();
        $perPage = max((int) setting('wallet.transactions.per_page', 20), 1);
        $offset = max((int) $request->integer('offset'), 0);

        $rows = $this->withdrawalsQuery($user)->skip($offset)->take($perPage)->get();

        return view('wallet.partials.withdrawals-rows-fragment', ['rows' => $rows]);
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
            ->take((int) setting('wallet.recent_rows', 5))
            ->get();

        /*
         | ⭐ **رصيد التذاكر من المصدر الواحد** (7.1 · 10.0-أ) — نفسه الذي يبني
         | كارت الـKPI ورادار الإنجازات. و24.5 يطلب هنا «كارت الرصيد + **بارات
         | مكتسب/مصروف**»، فالثلاثة من ميزانٍ واحد منغلق لا من ثلاثة استعلامات.
         */
        $sheet = $this->tickets->snapshot($request->user());

        return view('wallet.tickets', [
            'currency' => $currency,
            'balance' => $sheet['balance'],
            'sheet' => $sheet,
            'flow' => $this->tickets->flow($request->user(), (int) setting('ux.lists.default_range_days', 30)),
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
                'حروب التركيز',
            ]),
            'recent' => $recent,
        ]);
    }

    /** 🖥️ المعاملات والفواتير — جدول مفلتر على جانب التدريب فقط */
    public function transactions(Request $request)
    {
        $filters = $this->filters($request);
        $perPage = max((int) setting('wallet.transactions.per_page', 20), 1);

        $total = $this->transactionsQuery($request)->count();
        $rows = $this->transactionsQuery($request)->with('currency')->latest('id')->skip(0)->take($perPage)->get();

        return view('wallet.transactions', [
            'rows' => $rows,
            'filters' => $filters,
            'currencies' => Currency::query()->where('layer', 'training')->where('is_active', true)->get(),
            'sources' => $this->availableSources($request->user()->id),
            'hasMore' => $rows->count() < $total,
            'nextOffset' => $perPage,
            'pageSize' => $perPage,
        ]);
    }

    /** ⭐ تمرير تدريجيّ (13.1 · قرار §25 — ⛔ ممنوع ترقيم الصفحات): شريحة Fragment وحدها */
    public function transactionsMore(Request $request)
    {
        $perPage = max((int) setting('wallet.transactions.per_page', 20), 1);
        $offset = max((int) $request->integer('offset'), 0);

        $rows = $this->transactionsQuery($request)->with('currency')->latest('id')->skip($offset)->take($perPage)->get();

        return view('wallet.partials.transactions-rows-fragment', ['rows' => $rows]);
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
            // نفس أعمدة الشاشة بنصّ 19.2 — والكشف المصدَّر لا يخالف ما رآه صاحبه
            fputcsv($out, ['#', (string) setting('wallet.screen.export_msg', 'العملة'), (string) setting('wallet.screen.export_msg_2', 'الكمية'), (string) setting('wallet.screen.export_msg_3', 'من ← إلى'), (string) setting('wallet.screen.export_msg_4', 'السبب'), (string) setting('wallet.screen.export_msg_5', 'ملاحظات'), (string) setting('wallet.screen.export_msg_6', 'التاريخ'), (string) setting('wallet.screen.export_msg_7', 'الرصيد بعدها')]);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $row) {
                    $flow = self::flowOf($row);

                    fputcsv($out, [
                        $row->id,
                        $row->currency?->name_ar,
                        (float) ($row->applied_amount ?? $row->amount),
                        $flow['from'].' ← '.$flow['to'],
                        $row->reason,
                        self::notesOf($row),
                        $row->created_at?->format('Y-m-d H:i'),
                        (float) $row->balance_after,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * البيانات المشتركة بين تابَي المحفظة: كروت الأرباح والعمليّات الثلاث وأسعار الصرف.
     *
     * ⭐ كلّ النِّسب والحدود هنا **من الخادم**، وتُعرَض للمستخدم قبل الفتح فيعرف
     * تكلفة العمليّة قبل ما يبدأها — والملخّص النهائيّ يُطلَب من الخادم كذلك.
     */
    private function operationsData(Request $request): array
    {
        $user = $request->user();

        // المحظور يُخفى ولا يُعطَّل (2.15-أ-7) — والأرباح والسحب 🔒 لمالك المنصّة
        $canEarnings = $user->can('earnings.view');
        $canWithdraw = $user->can('withdraw.create');

        return [
            'canTransfer' => $user->can('transfer.create'),
            'canWithdraw' => $canWithdraw,
            'canEarnings' => $canEarnings,
            'earnings' => $canEarnings ? $this->withdrawals->earnings($user) : null,
            'pendingWithdrawal' => $canWithdraw ? $this->withdrawals->pendingFor($user) : null,
            'withdrawLimits' => [
                'fee_percent' => $this->withdrawals->feePercent(),
                'min_fee' => $this->withdrawals->minFee(),
                'min_amount' => $this->withdrawals->minAmount(),
            ],
            'transferCurrencies' => Currency::query()
                ->whereIn('code', TransferService::CURRENCIES)
                ->get()
                ->map(fn ($c) => [
                    'code' => $c->code,
                    'name' => $c->name_ar,
                    'fee' => $this->transfers->feePercent($c->code),
                ])
                ->values()
                ->all(),
            'transferMin' => $this->transfers->minAmount(),
            'exchangePaths' => collect($this->exchanges->paths())
                ->map(fn ($p) => [
                    'from' => $p['from'],
                    'to' => $p['to'],
                    'from_name' => $this->currencyName($p['from']),
                    'to_name' => $this->currencyName($p['to']),
                ])
                ->all(),
            'exchangeFee' => $this->exchanges->feePercent(),
            'rateTable' => $this->rates->table(),
        ];
    }

    private function currencyName(string $code): string
    {
        return (string) (Currency::query()->where('code', $code)->value('name_ar') ?? $code);
    }

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

    private function withdrawalsQuery(User $user)
    {
        return WalletWithdrawal::query()
            ->where('user_id', $user->id)
            ->latest('id');
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
            $map[$source] = self::sourceLabels()[$source] ?? $source;
        }

        return $map;
    }
}
