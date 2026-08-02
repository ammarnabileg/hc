<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\FocusWar;
use App\Services\Gamification\WalletGateway;
use App\Services\Gamification\Wars\Exceptions\WarRuleException;
use App\Services\Gamification\Wars\FocusWarService;
use App\Services\Gamification\Wars\WarRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * حرب التركيز (15.3) — عمل عميق بمكافأة **غير اقتصاديّة**.
 *
 * لماذا شاشة مستقلّة عن باقي الحروب: لأنّها ليست مواجهة ولا فيها فائز وخاسر،
 * بل التزامٌ فرديّ/جماعيّ مكافأته دقائق تركيز وشارة — والدليل الاجتماعيّ
 * (أكوام الأفاتار) هو محرّكها لا التذاكر.
 */
class FocusWarController extends Controller
{
    public function __construct(
        private readonly FocusWarService $focus,
        private readonly WarRules $rules,
        private readonly WalletGateway $wallet,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // العدّاد يكمل حتى لو قُفلت الشاشة (15.3-ب) — فنُسوّي المستحقّ عند العرض
        $this->focus->settleDue($user);

        $challenge = $this->challenge();

        return view('challenges.focus.index', [
            'challenge' => $challenge,
            'durations' => $this->rules->focusDurations($challenge),
            'createCost' => $this->rules->focusCreateCost($challenge),
            'joinCost' => $this->rules->focusJoinCost($challenge),
            'maxActive' => $this->rules->maxActiveFocus($challenge),
            'activeOwned' => $this->focus->activeOwnedCount($user),
            'board' => $this->focus->activeBoard($user),
            'focusMinutes' => $this->focus->focusMinutes($user),
            'honesty' => $this->focus->honestyMessage(),
            'ticketsBalance' => $this->wallet->balance($user, 'tickets'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'intention' => ['nullable', 'string', 'max:240'],
            'is_group' => ['nullable', 'boolean'],
        ]);

        try {
            $this->focus->create(
                $request->user(),
                $this->challenge(),
                (int) $data['duration_minutes'],
                $data['intention'] ?? null,
                (bool) ($data['is_group'] ?? false),
            );
        } catch (WarRuleException $e) {
            return back()->with('status', $e->getMessage())->with('topup_needed', $e->shortfall());
        }

        return redirect()->route('challenges.focus.index')->with('status', (string) setting('wars.messages.focus_started', 'التحدّي بدأ — ركّز وإحنا معاك 🧘'));
    }

    public function join(Request $request, FocusWar $focus_war): RedirectResponse
    {
        try {
            $this->focus->join($request->user(), $focus_war);
        } catch (WarRuleException $e) {
            return back()->with('status', $e->getMessage())->with('topup_needed', $e->shortfall());
        }

        return redirect()->route('challenges.focus.index')->with('status', (string) setting('wars.messages.focus_joined', 'انضممت — تذكرتك راحت لصاحب التحدّي 🎟️'));
    }

    public function cancel(Request $request, FocusWar $focus_war): RedirectResponse
    {
        try {
            $refunded = $this->focus->cancel($request->user(), $focus_war);
        } catch (WarRuleException $e) {
            return back()->with('status', $e->getMessage())->with('topup_needed', $e->shortfall());
        }

        return redirect()->route('challenges.focus.index')
            ->with('status', $refunded > 0
                ? str_replace(':n', (string) $refunded, (string) setting('wars.messages.focus_cancelled_refund', 'اتلغى التحدّي ورجعت :n تذكرة للمنضمّين ✓'))
                : (string) setting('wars.messages.focus_cancelled', 'اتلغى التحدّي ✓'));
    }

    /** حرب التركيز حربٌ واحدة في الجدول — ومنها تُقرأ الـOverrides (12.10-ج) */
    private function challenge(): Challenge
    {
        return Challenge::query()->where('key', 'focus_war')->firstOrFail();
    }
}
