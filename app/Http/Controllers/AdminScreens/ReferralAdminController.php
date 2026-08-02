<?php

namespace App\Http\Controllers\AdminScreens;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use App\Services\AdminScreens\ReferralAdmin;
use App\Services\AdminScreens\ScreenSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * لوحة إدارة الريفيرال والسفراء (24.2).
 *
 * ثلاثة تابات بتحميل كسول: لا يُحسَب إلّا التاب المفتوح (2.15-ب).
 * 🔒 والعمولة رقمٌ ماليّ لا يخرج للواجهة ولا للتصدير إلّا لمالك المنصّة (12.7).
 */
class ReferralAdminController extends Controller
{
    public function __construct(private readonly ReferralAdmin $referrals) {}

    public const TABS = [
        'invites' => 'المدعوّون',
        'ambassadors' => 'السفراء',
        'tiers' => 'العتبات والألقاب',
    ];

    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString();
        $tab = array_key_exists($tab, self::TABS) ? $tab : 'invites';

        $filters = $this->filters($request);
        $user = $request->user();
        $canSeeMoney = $this->canSeeMoney($user);

        return view('admin.referral-admin.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'filters' => $filters,
            'stats' => $this->referrals->stats($filters),
            'statuses' => ReferralAdmin::STATUSES,
            'payouts' => ReferralAdmin::PAYOUTS,
            'canSeeMoney' => $canSeeMoney,
            'service' => $this->referrals,
            // تحميل كسول: كلّ تاب يحسب بياناته وحده
            'invites' => $tab === 'invites' ? $this->referrals->invites($filters, $user) : null,
            'ambassadors' => $tab === 'ambassadors' ? $this->referrals->ambassadors($filters['q']) : null,
            'tiers' => $tab === 'tiers' ? $this->referrals->tiers() : [],
            'settings' => ScreenSettings::rows(ScreenSettings::SCREEN_REFERRAL, $user),
        ]);
    }

    /** لوحة جانبيّة: تدقيق شبكة داعٍ بعينه — لا صفحة جديدة (2.15-أ-8) */
    public function audit(Request $request, User $user): View
    {
        return view('admin.referral-admin.audit', [
            'referrer' => $user,
            'audit' => $this->referrals->audit($user),
            'statuses' => ReferralAdmin::STATUSES,
            'service' => $this->referrals,
        ]);
    }

    public function payout(Request $request, Referral $referral): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['note' => 'السبب']);

        $result = $this->referrals->payout($referral, (string) ($data['note'] ?? ''), $request->user());

        return $result['ok']
            ? back()->with('status', $result['message'])
            : back()->with('problem', $result['message']);
    }

    public function hold(Request $request, Referral $referral): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:500'],
        ], [], ['note' => 'سبب التعليق']);

        $this->referrals->hold($referral, $data['note'], $request->user());

        return back()->with('status', (string) setting('referral_admin.hold_text', 'اتعلّقت المكافأة لحدّ ما تراجعها.'));
    }

    /** حفظ سلّم الألقاب كاملًا — الترتيب يُفرَض تصاعديًّا على الخادم */
    public function saveTiers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.key' => ['required', 'string', 'max:32'],
            'tiers.*.label' => ['required', 'string', 'max:64'],
            'tiers.*.threshold' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [], ['tiers' => 'العتبات']);

        $count = $this->referrals->saveTiers($data['tiers'], $request->user());

        return back()->with('status', 'اتحفظ سلّم الألقاب ('.$count.' لقب) ✓');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->referrals->exportRows($this->filters($request), $this->canSeeMoney($request->user()));

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            if ($rows !== []) {
                fputcsv($handle, array_keys($rows[0]));

                foreach ($rows as $row) {
                    fputcsv($handle, array_values($row));
                }
            }

            fclose($handle);
        }, 'referrals-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        ScreenSettings::putMany(ScreenSettings::SCREEN_REFERRAL, $data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    public function resetSettings(Request $request): RedirectResponse
    {
        $count = ScreenSettings::resetScreen(ScreenSettings::SCREEN_REFERRAL, $request->user());

        return back()->with('status', 'رجعت '.$count.' قيمة للافتراضيّ ✓');
    }

    /** 🔒 الأرقام الماليّة للمجموعة المحميّة وحدها (12.7 · 24.2 «بلا صلاحيّة») */
    private function canSeeMoney(?User $user): bool
    {
        return (bool) $user?->isPlatformOwner()
            && (bool) setting('referral_admin.commission_visible', true);
    }

    /** @return array<string,string> */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'payout' => $request->string('payout')->toString(),
            'flagged' => $request->string('flagged')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];
    }
}
