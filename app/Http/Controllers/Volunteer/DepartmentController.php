<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\ConsentRequest;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Services\Volunteer\Org\AbsenceService;
use App\Services\Volunteer\Org\DepartmentScope;
use App\Services\Volunteer\Org\MemberDirectory;
use App\Services\Volunteer\Profile\ConsentFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «الأعضاء والبوزشنز» (24.4-7): سؤال واحد للشاشة — «مين معايا في القسم وبيعمل إيه؟».
 *
 * القسم **كاملًا حتى لو كنتُ في فرعيّ**، وكروت مضغوطة **بلا أرقام أداء تفصيليّة
 * لغير المخوَّل**، والعنصر الشرفيّ «أخوكم» **سطر شرفيّ في الرأس** لا صفّ في القائمة
 * ولا رقم في العدّاد (13.4-ص-ب).
 */
class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentScope $scope,
        private readonly MemberDirectory $directory,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $root = $this->scope->rootFor($user, (int) $request->query('entity') ?: null);

        if (! $root) {
            return view('volunteer.org.department', [
                'root' => null,
                'roots' => collect(),
                'cards' => collect(),
                'counters' => ['members' => 0, 'sub_entities' => 0, 'vacancies' => 0],
                'subEntities' => collect(),
                'positions' => collect(),
                'honorary' => null,
                'filters' => [],
                'view' => 'cards',
            ]);
        }

        $memberships = $this->scope->memberships($this->scope->entityIds($root));
        $cards = $this->directory->cards($memberships, $user);

        $filters = [
            'entity' => $request->query('sub'),
            'position' => $request->query('position'),
            'status' => $request->query('status'),
            'q' => $request->query('q'),
        ];

        return view('volunteer.org.department', [
            'root' => $root,
            'roots' => $this->scope->rootsFor($user),
            'cards' => $this->directory->filter($cards, $filters),
            'counters' => $this->directory->counters($root, $memberships),
            'subEntities' => $this->scope->subEntities($root),
            'positions' => Position::where('is_honorary', false)->orderByDesc('rank')->get(),
            // سطرٌ شرفيّ في رأس الصفحة — ومكانه من «أماكن الظهور» (13.4-ص-ب)
            'honorary' => $this->scope->honorary('members'),
            'filters' => $filters,
            // مبدّل عرض: كروت أو جدول — والاختيار يُحفَظ في الرابط
            'view' => $request->query('view') === 'table' ? 'table' : 'cards',
        ]);
    }

    /** بوب-أب ملفّ عضو مختصر — تفاصيل في بانل لا صفحة جديدة (2.15-أ-6) */
    public function member(Request $request, Membership $membership): JsonResponse
    {
        $viewer = $request->user();
        $root = $this->scope->rootFor($viewer);

        abort_unless($root && $this->sharesDepartment($membership, $root), 403);

        $pool = $this->scope->memberships($this->scope->entityIds($root));

        return response()->json($this->directory->profile($membership, $viewer, $pool));
    }

    /**
     * ⭐ فتح وضع «غائب» بتفويض مؤقّت (23-6): يضيفه **مشرف عام التطوّع أو مشرف
     * المسار أو دايركتور الكيان — لا الشخص نفسه** (منعًا للتهرّب). وأثره:
     * نوافذ القرار للبديل · لا أثر تباطؤ على الغائب · لا إسناد جديد له ·
     * وساعات مهامّه مجمَّدة حتى يعود.
     */
    public function absence(Request $request, Membership $membership): RedirectResponse
    {
        $viewer = $request->user();
        $root = $this->scope->rootFor($viewer);

        abort_unless($root && $this->sharesDepartment($membership, $root), 403);

        $data = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date'],
            'delegate_membership_id' => ['nullable', 'integer', 'exists:memberships,id'],
            'reason' => ['nullable', 'string', 'max:300'],
        ], [], ['from_date' => 'تاريخ البداية', 'to_date' => 'تاريخ النهاية']);

        $absence = app(AbsenceService::class)->open(
            $membership,
            $viewer,
            $data['from_date'],
            $data['to_date'],
            $data['delegate_membership_id'] ?? null,
            $data['reason'] ?? null,
        );

        $delegate = app(AbsenceService::class)->delegateOfAbsence($absence);

        return back()->with('status', 'اتسجّل وضع «غائب» ✓'
            .($delegate ? ' — البديل: '.$delegate->shortName().'، وكلّ القرارات هتروح له.' : ''));
    }

    /**
     * «اطلب إظهار الرقم» (13.4-م-2): الطلب على **البيانات** لا على الشخص.
     * صالح 72 ساعة، وتبريد 72 ساعة بعد الرفض/الانتهاء، والردّ صامت دائمًا.
     */
    public function requestConsent(Request $request, Membership $membership): RedirectResponse
    {
        $viewer = $request->user();
        $root = $this->scope->rootFor($viewer);

        abort_unless($root && $this->sharesDepartment($membership, $root), 403);
        abort_if((int) $membership->user_id === $viewer->id, 403);

        $validity = (int) setting('volunteer.consent.request_hours', 72);

        $existing = ConsentRequest::query()
            ->where('requester_id', $viewer->id)
            ->where('owner_id', $membership->user_id)
            ->where('field', 'phone')
            ->latest('id')
            ->first();

        // تبريد إعادة الطلب — منعًا لإزعاج صاحب البيانات
        $blocked = $existing
            && (($existing->status === 'pending' && $existing->request_expires_at > now())
                || ($existing->cooldown_until && $existing->cooldown_until > now()));

        if (! $blocked) {
            $consent = ConsentRequest::create([
                'requester_id' => $viewer->id,
                'owner_id' => $membership->user_id,
                'field' => 'phone',
                'reason' => trim((string) $request->input('reason')) ?: null,
                'status' => 'pending',
                'request_expires_at' => now()->addHours($validity),
                // ⛔ التبريد لا يبدأ من هنا: الدستور يبدأه **بعد انتهاء الطلب أو رفضه** (13.4-م-2)
                'cooldown_until' => null,
            ]);

            // إشعار صاحب البروفايل ليردّ — بلا إشعارٍ كان الطلب يظلّ معلّقًا أبدًا
            app(ConsentFlow::class)->announce($consent);
        }

        // الردّ محايد دائمًا — لا يكشف قبولًا ولا رفضًا (حفظًا للعلاقة داخل الفريق)
        return back()->with('status', (string) setting(
            'volunteer.consent.request_sent',
            'وصل طلبك — هيوصلك الردّ لمّا يتاح.',
        ));
    }

    private function sharesDepartment(Membership $membership, Entity $root): bool
    {
        return in_array((int) $membership->entity_id, $this->scope->entityIds($root), true);
    }
}
