<?php

namespace App\Services\AdminScreens;

use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Engagement\AmbassadorService;
use App\Services\Referral\ReferralService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * لوحة إدارة الريفيرال والسفراء (24.2).
 *
 * الشاشة العامّة `/ambassadors` تعرض الألقاب للناس، وهي لا تدير شيئًا: لا تعرف
 * مَن دعا مَن، ولا أيّ مكافأة معلّقة، ولا تسمح بتعديل عتبة. هذه الخدمة هي
 * الطرف الإداريّ: الدعوات وحالاتها · صرف المكافآت المعلّقة · العتبات والألقاب.
 *
 * 🔒 والعمولة **رقم ماليّ** — تُحسَب هنا لكنّها لا تخرج للواجهة إلّا لمالك
 * المنصّة (المجموعة المحميّة — 12.7).
 */
class ReferralAdmin
{
    public function __construct(
        private readonly AmbassadorService $ambassadors,
        private readonly ReferralService $referrals,
    ) {}

    /** حالة الدعوة الظاهرة للأدمن (24.2): مكتمل · انتظار التفعيل · لم يكمل التسجيل */
    public const STATUSES = [
        'completed' => 'مكتمل',
        'waiting' => 'بانتظار التفعيل',
        'incomplete' => 'لم يكمل التسجيل',
    ];

    public const PAYOUTS = [
        'pending' => 'معلّقة',
        'paid' => 'مصروفة',
        'held' => 'موقوفة',
    ];

    public function defaultRangeDays(): int
    {
        return max(1, (int) setting('referral_admin.default_range_days', 30));
    }

    public function commissionPercent(): float
    {
        return (float) setting('referral_admin.commission_percent', $this->referrals->commissionPercent());
    }

    /**
     * تاب «المدعوّون» — والفلاتر ثلاثة ظاهرة: الحالة · حالة المكافأة · البحث.
     *
     * @param  array{q?:string,status?:string,payout?:string,flagged?:string,from?:string,to?:string}  $filters
     */
    public function invitesQuery(array $filters): Builder
    {
        $from = ($filters['from'] ?? '') !== ''
            ? CarbonImmutable::parse($filters['from'])->startOfDay()
            : CarbonImmutable::now()->subDays($this->defaultRangeDays() - 1)->startOfDay();

        $to = ($filters['to'] ?? '') !== ''
            ? CarbonImmutable::parse($filters['to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        return Referral::query()
            ->with(['referrer:id,name,code', 'referred:id,name,code,status'])
            ->whereBetween('referrals.created_at', [$from, $to])
            // ⚠️ البحث مجموعٌ داخل قوسين: بغيرهما تهرب `or` من فلتر الفترة فيعود
            // الجدول بصفوف خارج المدى المطلوب — وهو خطأ صامت لا تراه العين.
            ->when(($filters['q'] ?? '') !== '', function ($q) use ($filters) {
                $term = '%'.$filters['q'].'%';

                $q->where(fn ($w) => $w
                    ->where('referrals.code', 'like', $term)
                    ->orWhereHas('referrer', fn ($r) => $r->where('name', 'like', $term)->orWhere('code', 'like', $term))
                    ->orWhereHas('referred', fn ($r) => $r->where('name', 'like', $term)->orWhere('code', 'like', $term)));
            })
            ->when(($filters['status'] ?? '') === 'completed', fn ($q) => $q->whereHas('referred', fn ($r) => $r->where('status', 'active')))
            ->when(($filters['status'] ?? '') === 'waiting', fn ($q) => $q->whereHas('referred', fn ($r) => $r->where('status', '!=', 'active')))
            ->when(($filters['status'] ?? '') === 'incomplete', fn ($q) => $q->whereNull('referred_id'))
            ->when(($filters['payout'] ?? '') !== '', fn ($q) => $q->where('payout_status', $filters['payout']))
            ->when(($filters['flagged'] ?? '') === '1', fn ($q) => $q->where('is_flagged', true))
            ->latest('referrals.id');
    }

    public function invites(array $filters): LengthAwarePaginator
    {
        return $this->invitesQuery($filters)
            ->paginate(max(5, (int) setting('referral_admin.per_page', 20)))
            ->withQueryString();
    }

    /** حالة سطر الدعوة — تُحسَب ولا تُخزَّن كي لا تتناقض مع حالة المستخدم */
    public function statusOf(Referral $referral): string
    {
        if (! $referral->referred_id) {
            return 'incomplete';
        }

        return $referral->referred?->status === 'active' ? 'completed' : 'waiting';
    }

    /**
     * أربعة أرقام فقط (2.15-أ-3).
     *
     * @return array{invites:int,completed:int,pending:int,ambassadors:int,commission:float}
     */
    public function stats(array $filters): array
    {
        $base = $this->invitesQuery($filters);

        return [
            'invites' => (int) (clone $base)->count(),
            'completed' => (int) (clone $base)->whereHas('referred', fn ($r) => $r->where('status', 'active'))->count(),
            'pending' => (int) (clone $base)->where('payout_status', 'pending')->count(),
            'ambassadors' => (int) User::query()->whereNotNull('ambassador_tier')->count(),
            // 🔒 لا تُعرَض إلّا لمالك المنصّة — والحساب هنا لا يعني الإظهار
            'commission' => (float) (clone $base)->sum('commission_earned'),
        ];
    }

    /**
     * تاب «السفراء»: الاسم · الدعوات المفعَّلة · اللقب · الترتيب.
     *
     * @return Collection<int,User>
     */
    public function ambassadors(string $search = ''): Collection
    {
        return User::query()
            ->where('ambassador_invites', '>', 0)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('code', 'like', '%'.$search.'%')))
            ->orderByDesc('ambassador_invites')
            ->limit(max(1, (int) setting('ambassadors.leaderboard.limit', 20)))
            ->get(['id', 'name', 'code', 'ambassador_invites', 'ambassador_tier', 'ambassador_title', 'ambassador_granted_at']);
    }

    /** تاب «العتبات والألقاب» — مصدرها إعداد `ambassadors.tiers` لا جدول */
    public function tiers(): array
    {
        return $this->ambassadors->tiers();
    }

    /**
     * حفظ العتبات كلّها دفعةً واحدة (صفوف قابلة للترتيب) — والترتيب يُفرَض
     * تصاعديًّا على الخادم فلا يمكن حفظ سلّم مقلوب يمنح الماسيّ قبل البرونزيّ.
     */
    public function saveTiers(array $rows, ?User $actor = null): int
    {
        $tiers = [];

        foreach ($rows as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            $threshold = (int) ($row['threshold'] ?? 0);

            if ($key === '' || $label === '' || $threshold <= 0) {
                continue;
            }

            $tiers[$key] = ['key' => $key, 'label' => $label, 'threshold' => $threshold];
        }

        $tiers = array_values($tiers);
        usort($tiers, fn ($a, $b) => $a['threshold'] <=> $b['threshold']);

        $setting = Setting::firstOrNew(['key' => 'ambassadors.tiers']);
        $old = $setting->value;

        $setting->fill([
            'group' => 'ambassadors',
            'label_ar' => 'عتبات ألقاب السفراء',
            'type' => 'json',
            'default_value' => $setting->default_value ?: $old,
            'value' => json_encode($tiers, JSON_UNESCAPED_UNICODE),
        ])->save();

        Cache::forget('settings');
        AuditTrail::log($actor, 'ambassadors.tiers.update', $setting, ['value' => $old], ['value' => $setting->value]);

        return count($tiers);
    }

    /**
     * صرف مكافأة معلّقة (24.2): **استكمال بيانات المدعوّ + موافقة الأدمن**.
     * ونمنح تذكرة الترحيب عبر خدمة الدعوات نفسها كي لا تتكرّر التذكرة أبدًا.
     *
     * @return array{ok:bool,message:string}
     */
    public function payout(Referral $referral, string $note, ?User $actor = null): array
    {
        $requiresProfile = (bool) setting('referral_admin.require_complete_profile', true);

        if ($requiresProfile && $this->statusOf($referral) !== 'completed') {
            return [
                'ok' => false,
                'message' => 'المدعوّ لسّه مافعّلش حسابه — المكافأة فضلت معلّقة. راجعه أو عطّل شرط استكمال البيانات من إعدادات الشاشة.',
            ];
        }

        $granted = $referral->referred ? $this->referrals->grantWelcomeTicket($referral->referred) : false;

        $referral->forceFill([
            'payout_status' => 'paid',
            'payout_note' => $note ?: null,
            'reviewed_by' => $actor?->id,
            'reviewed_at' => now(),
        ])->save();

        // اللقب يتحرّك بالدعوات المفعَّلة — فالمزامنة بعد الصرف لا قبله
        if ($referral->referrer) {
            $this->ambassadors->sync($referral->referrer);
        }

        AuditTrail::log($actor, 'referrals.payout', $referral, ['payout_status' => 'pending'], [
            'payout_status' => 'paid',
            'welcome_ticket' => $granted,
        ]);

        return ['ok' => true, 'message' => (string) setting('referral_admin.paid_text', 'اتصرفت المكافأة ✓')];
    }

    /** تعليق المكافأة بسببٍ مكتوب — والتعليق قرارٌ مثل الصرف فله أثره */
    public function hold(Referral $referral, string $note, ?User $actor = null): void
    {
        $referral->forceFill([
            'payout_status' => 'held',
            'payout_note' => $note ?: null,
            'is_flagged' => true,
            'reviewed_by' => $actor?->id,
            'reviewed_at' => now(),
        ])->save();

        AuditTrail::log($actor, 'referrals.hold', $referral, [], ['note' => $note]);
    }

    /**
     * تدقيق شبكة داعٍ: كم دعوة · كم مفعَّلة · أعلى عدد في يوم واحد.
     * الرقم الأخير هو ما يكشف التكرار المشبوه بلا اتّهام مسبق.
     *
     * @return array{invites:int,activated:int,busiest_day:int,threshold:int,suspicious:bool,rows:Collection}
     */
    public function audit(User $referrer): array
    {
        $rows = Referral::query()
            ->with('referred:id,name,code,status')
            ->where('referrer_id', $referrer->id)
            ->latest('id')
            ->limit(100)
            ->get();

        $busiest = (int) DB::table('referrals')
            ->where('referrer_id', $referrer->id)
            ->groupBy(DB::raw('date(created_at)'))
            ->selectRaw('count(*) as total')
            ->orderByDesc('total')
            ->value('total');

        $threshold = max(1, (int) setting('referral_admin.flag_threshold', 5));

        return [
            'invites' => $rows->count(),
            'activated' => $rows->filter(fn (Referral $r) => $r->referred?->status === 'active')->count(),
            'busiest_day' => $busiest,
            'threshold' => $threshold,
            'suspicious' => $busiest >= $threshold,
            'rows' => $rows,
        ];
    }

    /**
     * تصدير المدعوّين — والعمولة **لا تُكتَب في الملفّ** لغير مالك المنصّة،
     * فالتصدير ثغرةٌ شائعة يتسرّب منها الرقم الماليّ من وراء الواجهة.
     *
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(array $filters, bool $withCommission): array
    {
        return $this->invitesQuery($filters)->limit(50000)->get()->map(function (Referral $referral) use ($withCommission) {
            $row = [
                'code' => $referral->code,
                'referrer' => $referral->referrer?->name,
                'referred' => $referral->referred?->name,
                'invited_at' => $referral->created_at?->format('Y-m-d H:i'),
                'status' => self::STATUSES[$this->statusOf($referral)],
                'welcome_ticket' => $referral->welcome_ticket_granted ? 'صُرفت' : 'لم تُصرَف',
                'payout' => self::PAYOUTS[$referral->payout_status] ?? $referral->payout_status,
            ];

            if ($withCommission) {
                $row['commission'] = (float) $referral->commission_earned;
            }

            return $row;
        })->all();
    }
}
