<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\User;
use App\Services\Admin\AuditTrail;
use App\Support\Access\AccessEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * مصفوفة الصلاحيّات واستثناءاتها الفرديّة (12.2.2 · 12.2.1-ز).
 *
 * الاستثناء الفرديّ يجلس **فوق** الأدوار بنفس قاعدة Deny > Allow،
 * ويخضع لنفس حارسَي الدستور: منع تصعيد الامتياز، وعزل الحسّاس لمالك المنصّة.
 */
class PermissionController extends Controller
{
    public function __construct(
        private readonly AccessEngine $access,
        private readonly AuditTrail $audit,
    ) {}

    /** تصفّح المصفوفة بالبحث والمجموعات — قراءة فقط */
    public function index(Request $request): View
    {
        $actor = $request->user();
        $search = trim((string) $request->query('q', ''));

        $groups = Permission::query()
            ->when(! $this->access->isPlatformOwner($actor), fn ($q) => $q->where('is_owner_only', false))
            ->select('group')
            ->selectRaw('count(*) as total')
            ->groupBy('group')
            ->orderBy('group')
            ->pluck('total', 'group')
            ->all();

        $group = (string) $request->query('group', (string) array_key_first($groups));

        if (! array_key_exists($group, $groups)) {
            $group = (string) array_key_first($groups);
        }

        return view('admin.roles.permissions', [
            'groups' => $groups,
            'group' => $group,
            'search' => $search,
            'permissions' => Permission::query()
                ->where('group', $group)
                // ⭐ عزل الحسّاس: لا تظهر أصلًا لغير مالك المنصّة
                ->when(! $this->access->isPlatformOwner($actor), fn ($q) => $q->where('is_owner_only', false))
                ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                    ->where('key', 'like', '%'.$search.'%')
                    ->orWhere('label_ar', 'like', '%'.$search.'%')))
                ->orderBy('resource')
                ->orderBy('id')
                ->limit((int) setting('admin.roles.rows_per_group', 400))
                ->get(),
        ]);
    }

    /**
     * استثناء فرديّ: منح أو منع صلاحيّة لمستخدم بعينه.
     *
     * ⭐ **وسقف نطاق المصفوفة يُفرَض هنا وقت الكتابة (12.2.2):** كان التصديق
     * `['required','string']` وحده، فيُقبَل أيّ نصٍّ في خانة النطاق — و`scope=ALL`
     * على مفتاحٍ سقفُه TRACK **كُتِب ونفَذ**. والرفض **صريحٌ لا تضييقٌ صامت**:
     * فالمسؤول يرى ما رُفض ولماذا وما النطاقات المتاحة، ولا يُحفَظ له نطاقٌ غير
     * الذي اختاره (12.2.1-د: «يرى بعينه ما مُنِح»).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'permission' => ['required', 'exists:permissions,key'],
            // النطاق من القائمة الستّة نصًّا (12.2.1-ب) — لا نصٌّ حرّ
            'scope' => ['required', 'string', Rule::in(config('access.scopes'))],
            'effect' => ['required', 'in:allow,deny'],
        ], [], ['permission' => (string) setting('admin_roles.permissions.update_msg', 'الصلاحيّة'), 'scope' => (string) setting('admin_roles.permissions.update_msg_2', 'النطاق'), 'effect' => (string) setting('admin_roles.permissions.update_msg_3', 'الأثر')]);

        $actor = $request->user();
        $permission = Permission::where('key', $validated['permission'])->firstOrFail();

        // ⭐ عزل الحسّاس + ⭐ منع تصعيد الامتياز — قبل أيّ كتابة
        if ($permission->is_owner_only && ! $this->access->isPlatformOwner($actor)) {
            return back()->with('problem', (string) setting('admin.roles.owner_only_note', 'الصلاحيّة دي لمالك المنصّة وحده.'));
        }

        /*
         | ⭐ سقف المصفوفة — على **الإذن** وحده: توسيع المنع تشديدٌ لا تصعيد،
         | و«Deny > Allow» (12.2.1-ز-1) لا تُقيَّد بسقفٍ وُضِع لتحديد ما يُمنَح.
         */
        if ($validated['effect'] === 'allow'
            && ! $this->access->withinAllowedScopes($permission->key, $validated['scope'])) {
            $message = $this->ceilingMessage($permission, $validated['scope']);

            return back()->with('problem', $message)->withErrors([$message]);
        }

        if (! $this->access->canGrant($actor, $permission->key, $validated['scope'])) {
            $message = strtr(
                (string) setting('admin.roles.escalation_message', 'مقدرناش نحفظ «:permission» بنطاق :scope.'),
                [':permission' => $permission->label_ar.' ('.$permission->key.')', ':scope' => $validated['scope']],
            );

            return back()->with('problem', $message)->withErrors([$message]);
        }

        $override = PermissionUser::updateOrCreate(
            [
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => $validated['scope'],
            ],
            [
                'effect' => $validated['effect'],
                'assigned_by' => $actor->id,
            ],
        );

        $this->access->forget($user);

        $this->audit->record($actor, 'permission.user.updated', $user, [], [
            'permission' => $permission->key,
            'scope' => $validated['scope'],
            'effect' => $validated['effect'],
            'override_id' => $override->id,
        ]);

        return back()->with('status', (string) setting('admin_roles.permissions.update_ok', 'اتحفظ الاستثناء ✓ — والمنع يغلب الإذن دائمًا'));
    }

    /** رسالة رفض السقف — بنصٍّ من الإعدادات لا محروق (2.13) */
    private function ceilingMessage(Permission $permission, string $scope): string
    {
        return strtr(
            (string) setting(
                'admin.roles.scope_ceiling_message',
                'مقدرناش نحفظ «:permission» بنطاق :scope — المصفوفة (12.2.2) بتحدّد لها :scopes وبس.',
            ),
            [
                ':permission' => $permission->label_ar.' ('.$permission->key.')',
                ':scope' => $scope,
                ':scopes' => implode(' · ', $this->access->allowedScopesOf($permission->key)),
            ],
        );
    }
}
