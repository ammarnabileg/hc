<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\MembershipAbsence;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Volunteer\Org\AbsenceService;
use App\Support\Scope\ScopeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * ⭐ شاشة إدارة الغيابات والتفويض المؤقّت (الدستور 23-6 · 24).
 *
 * لماذا شاشةٌ مركزيّة والإضافة تتمّ من «الأعضاء والبوزشنز»؟ لأنّ الإضافة فعلٌ
 * موضعيّ يعرفه الدايركتور داخل قسمه، أمّا **الإدارة** فسؤالٌ آخر تمامًا:
 * «مَن الغائب اليوم في المنصّة كلّها، ومَن يقرّر مكانه، وإلى متى؟». وبلا هذه
 * الشاشة تبقى نوافذ القرار موزَّعةً على بدلاء لا يعرفهم أحد مجتمعين.
 *
 * ⛔ ولا منطق جديدًا هنا: `AbsenceService` هو صاحب القاعدة (التوجيه للبديل ·
 * إعفاء التباطؤ · تجميد الساعات · منع الإسناد) — وهذه الشاشة **تستهلكه**.
 */
class DelegationAdminController extends Controller
{
    /** مفاتيح الحالات الثلاث (والفلتر ثلاثة ظاهرة — 2.15-أ-4) — مفاتيح لا نصوص */
    public const STATE_KEYS = ['current', 'upcoming', 'ended'];

    /**
     * أسماء الحالات كما تُعرَض — ميثودٌ لا `const` كي يحرّرها المالك (2.13-أ).
     *
     * @return array<string, string>
     */
    public static function states(): array
    {
        return [
            'current' => (string) setting('delegation.admin.state_current', 'سارية الآن'),
            'upcoming' => (string) setting('delegation.admin.state_upcoming', 'قادمة'),
            'ended' => (string) setting('delegation.admin.state_ended', 'منتهية'),
        ];
    }

    public function __construct(
        private readonly AbsenceService $absences,
        private readonly ScopeFilter $scopes,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'state' => $this->state($request->string('state')->toString()),
            'entity' => $request->integer('entity') ?: null,
            'q' => trim($request->string('q')->toString()),
        ];

        $rows = $this->rows($request, $filters);

        return view('admin.volunteer.delegations', [
            'rows' => $rows,
            'filters' => $filters,
            'states' => self::states(),
            'entities' => $this->entityOptions($request),
            'kpis' => $this->kpis($request),
            'audit' => AuditTrail::latest('delegation.', (int) setting('volunteer.absence.audit_rows', 15)),
            'maxDays' => $this->absences->maxDays(),
            'maxPerMonth' => $this->absences->maxPerMonth(),
        ]);
    }

    /**
     * ⭐ الإنهاء المبكّر — «رجع قبل ميعاده»: يقفل الوضع لحظتَه فتعود نوافذ
     * قراره إليه، وتُفَكّ ساعاته بالمدّة الفعليّة (23-6). وبسبب إلزاميّ
     * ليقرأه مَن يفتح السجلّ بعد شهر (24 — إجراءات الصفّ).
     */
    public function end(Request $request, MembershipAbsence $membershipAbsence): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:300'],
        ], [], ['note' => (string) setting('delegation.admin.end_msg', 'سبب الإنهاء')]);

        // النطاق إلزاميّ مع الصلاحيّة (12.2.1-ب) — ولا يُنهي أحدٌ غيابًا خارج نطاقه
        abort_unless($this->visibleIds($request)->contains($membershipAbsence->id), 403);

        try {
            $absence = $this->absences->endEarly($membershipAbsence, $request->user(), $data['note']);
        } catch (ValidationException $e) {
            return back()->withInput()->with('status', $e->validator->errors()->first());
        }

        AuditTrail::log($request->user(), 'delegation.end_early', $absence, [
            'to_date' => $absence->to_date?->toDateString(),
        ], [
            'ended_at' => $absence->ended_at?->toDateTimeString(),
            'note' => $data['note'],
        ]);

        return back()->with('status', (string) setting('delegation.admin.end_ok', 'اتقفل وضع «غائب» ✓ — رجعت قراراته له، وساعات مهامّه اتزاحت بمدّة غيابه الفعليّة.'));
    }

    // ------------------------------------------------------------------ داخليّ

    private function state(string $value): string
    {
        return in_array($value, self::STATE_KEYS, true) ? $value : 'current';
    }

    /**
     * الاستعلام الأساس: الغيابات داخل نطاق مَن يقرأ.
     *
     * والجدول بلا `user_id` ولا `entity_id`، فيُوصَل بعضويّة الغائب ويُحصَر
     * بعمودَيها — فيبقى معيار النطاق واحدًا لا اجتهادًا لكلّ شاشة.
     *
     * @return Builder<MembershipAbsence>
     */
    private function scoped(Request $request): Builder
    {
        return MembershipAbsence::query()
            ->join('memberships', 'memberships.id', '=', 'membership_absences.membership_id')
            ->tap(fn ($q) => $this->scopes->apply(
                $q,
                $request->user(),
                'delegations.list',
                'memberships.user_id',
                'memberships.entity_id',
            ))
            ->select('membership_absences.*');
    }

    /** @return Collection<int, int> */
    private function visibleIds(Request $request): Collection
    {
        // بأليَس صريح: `pluck` يقرأ النقطة كخاصّيّة كائنٍ لا كعمودٍ مُسمّى بجدوله
        return $this->scoped($request)->toBase()
            ->select('membership_absences.id as id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    /** @return Collection<int, MembershipAbsence> */
    private function rows(Request $request, array $filters): Collection
    {
        return $this->scoped($request)
            ->with([
                'membership.user:id,name,code',
                'membership.entity:id,name_ar',
                'membership.position:id,name_ar',
                'delegate_membership.user:id,name,code',
                'delegate_membership.position:id,name_ar',
                'created_by:id,name,code',
                'ended_by:id,name,code',
            ])
            ->tap(fn ($q) => $this->applyState($q, $filters['state']))
            ->when($filters['entity'], fn ($q, $entity) => $q->where('memberships.entity_id', $entity))
            ->when($filters['q'] !== '', fn ($q) => $q->whereHas(
                'membership.user',
                fn ($u) => $u->where('code', mb_strtoupper($filters['q']))->orWhere('name', 'like', '%'.$filters['q'].'%'),
            ))
            ->orderByDesc('membership_absences.from_date')
            ->limit((int) setting('volunteer.absence.admin_rows', 50))
            ->get();
    }

    /**
     * الحالات الثلاث بمعنًى واحدٍ لا لبس فيه:
     * سارية = بدأت ولم تنتهِ ولم تُنهَ · قادمة = بدايتها في المستقبل ولم تُلغَ
     * · منتهية = مضى موعدها **أو** أُنهيت مبكّرًا.
     *
     * @param  Builder<MembershipAbsence>  $query
     */
    private function applyState(Builder $query, string $state): void
    {
        match ($state) {
            'current' => $this->absences->runningToday($query),
            'upcoming' => $query
                ->whereDate('membership_absences.from_date', '>', today())
                ->whereNull('membership_absences.ended_at'),
            default => $query->where(
                fn ($q) => $q->whereDate('membership_absences.to_date', '<', today())
                    ->orWhereNotNull('membership_absences.ended_at'),
            ),
        };
    }

    /** أربعة كروت KPI بحدّ أقصى (2.15-أ-3) */
    private function kpis(Request $request): array
    {
        $soonDays = (int) setting('volunteer.absence.upcoming_days', 14);

        return [
            'current' => $this->scoped($request)->tap($this->absences->runningToday(...))->count(),
            'upcoming' => $this->scoped($request)
                ->whereNull('membership_absences.ended_at')
                ->whereDate('membership_absences.from_date', '>', today())
                ->whereDate('membership_absences.from_date', '<=', today()->addDays($soonDays))
                ->count(),
            'ending_soon' => $this->scoped($request)
                ->tap($this->absences->runningToday(...))
                ->whereDate('membership_absences.to_date', '<=', today()->addDays((int) setting('volunteer.absence.ending_soon_days', 3)))
                ->count(),
            // بلا بديل نشِط = نافذة قرارٍ معلَّقة على الهواء — أخطر رقم في الشاشة
            'no_delegate' => $this->scoped($request)
                ->tap($this->absences->runningToday(...))
                ->where(fn ($q) => $q->whereNull('membership_absences.delegate_membership_id')->orWhereHas(
                    'delegate_membership',
                    fn ($m) => $m->where('status', '!=', 'active'),
                ))
                ->count(),
            'soon_days' => $soonDays,
        ];
    }

    /** كيانات الغيابات المرئيّة وحدها — فلترٌ لا يعرض ما لا يُرى */
    private function entityOptions(Request $request): Collection
    {
        $ids = $this->scoped($request)->toBase()
            ->select('memberships.entity_id as entity_id')
            ->distinct()
            ->pluck('entity_id')
            ->filter();

        return Entity::query()->whereIn('id', $ids)->orderBy('name_ar')->get(['id', 'name_ar']);
    }
}
