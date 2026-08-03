<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use App\Services\Admin\AuditTrail;
use App\Services\Admin\RoleEditor;
use App\Support\Access\AccessEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الأدوار والصلاحيّات (الدستور 12.2 · 12.2.1 · 12.2.3 · 24.1) — أهمّ شاشة في اللوحة.
 *
 * الشاشة لوح عمودين: الأدوار يمينًا والمصفوفة يسارًا، وبها **بحث** و**مجموعات عرض**
 * و**`manage`** و**قوالب جاهزة** — وهي شرط قبول لا تحسين (12.2.1-ط).
 */
class RoleController extends Controller
{
    public function __construct(
        private readonly RoleEditor $editor,
        private readonly AuditTrail $audit,
    ) {}

    public function index(Request $request): View
    {
        $roles = $this->editor->roles();

        return view('admin.roles.index', [
            'roles' => $roles,
            'editor' => $this->editor,
            // آخر تغيير فقط — يظهر بالـHover مع رابط لبروفايل المحرّر (12.2.1-ز-4)
            'lastChanges' => $this->audit->lastChangeFor(
                (new Role)->getMorphClass(),
                $roles->pluck('id')->all(),
                ['role.permissions.updated', 'role.created'],
            ),
        ]);
    }

    /** دور جديد = نسخ قالب وتعديله — لا بناء من الصفر (12.2.3-4) */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'template' => ['required', 'exists:roles,id'],
        ], [], ['name_ar' => 'اسم الدور', 'template' => 'القالب']);

        $template = Role::findOrFail($validated['template']);
        $copy = $this->editor->duplicate($template, $validated['name_ar'], $request->user());

        return redirect()->route('admin.roles.edit', $copy)
            ->with('status', "اتعمل الدور «{$copy->name_ar}» نسخةً من «{$template->name_ar}» ✓");
    }

    public function edit(Request $request, Role $role): View
    {
        $actor = $request->user();
        $groups = $this->editor->groups($actor);
        $group = (string) $request->query('group', (string) array_key_first($groups));

        if (! array_key_exists($group, $groups)) {
            $group = (string) array_key_first($groups);
        }

        return view('admin.roles.edit', [
            'role' => $role,
            'groups' => $groups,
            'group' => $group,
            'search' => trim((string) $request->query('q', '')),
            'rows' => $this->editor->matrix($role, $actor, $group, trim((string) $request->query('q', ''))),
            'scopes' => config('access.scopes'),
            'canEdit' => $actor->allows('roles.edit'),
            'canDelete' => $this->editor->canDelete($role) && $actor->allows('roles.delete'),
            'lastChange' => $this->audit->lastChange($role, ['role.permissions.updated', 'role.created']),
        ]);
    }

    /**
     * حفظ مجموعة من مصفوفة الدور.
     *
     * ⭐ **سقف نطاق المصفوفة: رفضٌ صريح لا تضييقٌ صامت (12.2.2 · 12.2.1-د).**
     * كان النطاق الخارج عن `allowed_scopes` **يُستبدَل بالافتراضيّ** بلا أيّ إخبار،
     * فيضغط المسؤول `@ALL` ويُحفَظ له `@SELF` ويمضي واثقًا أنّه منح ما لم يُمنَح —
     * وهو عين ما يمنعه نصّ «**يرى بعينه ما مُنِح — لا وراثة صامتة**». فالآن يُردّ
     * الحفظ ويُقال ما رُفض وما النطاقات المتاحة، ولا يُكتَب صفٌّ واحد.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'group' => ['required', 'string'],
        ], [], ['group' => 'مجموعة الصلاحيّات']);

        if ($beyond = $this->scopesBeyondCeiling((array) $request->input('rows', []))) {
            return redirect()
                ->route('admin.roles.edit', ['role' => $role, 'group' => $validated['group']])
                ->withErrors($beyond)
                ->with('problem', 'مافيش سطر اتحفظ: في نطاقات بره سقف المصفوفة — اقرأ التفاصيل فوق وصغّرها.');
        }

        $result = $this->editor->save(
            $role,
            $request->user(),
            $validated['group'],
            (array) $request->input('rows', []),
        );

        $redirect = redirect()->route('admin.roles.edit', [
            'role' => $role,
            'group' => $validated['group'],
        ]);

        // ⭐ الرفض برسالة واضحة تشرح لماذا رُفض المنح (12.2.1-ز-2)
        if ($result['rejected'] !== []) {
            return $redirect
                ->withErrors([...$result['rejected'], ...$result['dropped']])
                ->with('problem', 'في سطور مااتحفظتش عشان منع تصعيد الامتياز — اقرأ التفاصيل فوق.');
        }

        /*
         | ⭐ «يرى بعينه ما مُنِح — لا وراثة صامتة» (12.2.1-د): فرد `manage` قد يُسقِط
         | أفعالًا لا تقبل النطاق المطلوب، والمسؤول لازم يعرف **ما سقط ولماذا** قبل
         | أن يكتشفه من شكوى مستخدم.
         */
        if ($result['dropped'] !== []) {
            return $redirect
                ->withErrors($result['dropped'])
                ->with('problem', "اتحفظ {$result['written']} سطر — بس في سطور سقطت، اقرأ التفاصيل فوق.");
        }

        return $redirect->with('status', "اتحفظ ✓ — {$result['written']} سطر صلاحيّة مفرود ظاهر قدّامك");
    }

    /**
     * الصفوف التي نطاقُها خارج القائمة الستّة أو خارج سقف المصفوفة — برسالةٍ لكلٍّ.
     *
     * والمنع لا يُقاس بالسقف: توسيعه تشديدٌ لا تصعيد (12.2.1-ز-1).
     *
     * @param  array<int|string, array{on?: string, scope?: string, effect?: string}>  $rows
     * @return array<int, string>
     */
    private function scopesBeyondCeiling(array $rows): array
    {
        $wanted = [];

        foreach ($rows as $permissionId => $row) {
            $scope = trim((string) ($row['scope'] ?? ''));

            // بلا اختيار أو بلا نطاق: النطاق الافتراضيّ يتولّاه المحرّر كما هو
            if (! is_array($row) || empty($row['on']) || $scope === '') {
                continue;
            }

            if ((($row['effect'] ?? 'allow') === 'deny')) {
                continue;
            }

            $wanted[(int) $permissionId] = $scope;
        }

        if ($wanted === []) {
            return [];
        }

        $access = app(AccessEngine::class);
        $errors = [];

        foreach (Permission::whereIn('id', array_keys($wanted))->get() as $permission) {
            $scope = $wanted[$permission->id];

            if ($access->withinAllowedScopes($permission->key, $scope)
                && in_array($scope, (array) config('access.scopes'), true)) {
                continue;
            }

            $errors[] = strtr(
                (string) setting(
                    'admin.roles.scope_ceiling_message',
                    'مقدرناش نحفظ «:permission» بنطاق :scope — المصفوفة (12.2.2) بتحدّد لها :scopes وبس.',
                ),
                [
                    ':permission' => $permission->label_ar.' ('.$permission->key.')',
                    ':scope' => $scope,
                    ':scopes' => implode(' · ', $access->allowedScopesOf($permission->key)),
                ],
            );
        }

        return $errors;
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        // دور مالك المنصّة ثابت نظاميّ (12.2.3)
        if (! $this->editor->canDelete($role)) {
            return back()->with('problem', (string) setting('admin.roles.protected_message', 'الدور ده محميّ ولا يُحذَف.'));
        }

        $name = $role->name_ar;
        $this->editor->delete($role, $request->user());

        return redirect()->route('admin.roles.index')->with('status', "اتمسح الدور «{$name}» ✓");
    }

    // ------------------------------------------------- إسناد دور داخل عضويّة

    public function assign(Request $request): View
    {
        $target = $request->integer('user') ? User::find($request->integer('user')) : null;

        return view('admin.roles.assign', [
            'roles' => Role::orderBy('layer')->orderBy('id')->get(),
            'target' => $target,
            'memberships' => $target
                ? Membership::where('user_id', $target->id)->with('entity', 'position')->get()
                : collect(),
            'assignments' => $this->editor->assignments(),
            'lastChanges' => $this->audit->lastChangeFor(
                (new User)->getMorphClass(),
                $this->editor->assignments()->pluck('user_id')->all(),
                ['role.assigned', 'role.unassigned'],
            ),
        ]);
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user' => ['required', 'exists:users,id'],
            'role' => ['required', 'exists:roles,id'],
            'membership' => ['nullable', 'exists:memberships,id'],
        ], [], ['user' => 'المستخدم', 'role' => 'الدور', 'membership' => 'العضويّة']);

        $result = $this->editor->assign(
            $request->user(),
            User::findOrFail($validated['user']),
            Role::findOrFail($validated['role']),
            isset($validated['membership']) ? Membership::find($validated['membership']) : null,
        );

        return $result['ok']
            ? back()->with('status', $result['message'])
            : back()->with('problem', $result['message'])->withErrors([$result['message']]);
    }

    public function destroyAssignment(Request $request, RoleUser $assignment): RedirectResponse
    {
        $this->editor->unassign($request->user(), $assignment);

        return back()->with('status', 'اتسحب الدور ✓');
    }
}
