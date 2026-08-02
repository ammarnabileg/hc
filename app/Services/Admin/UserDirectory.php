<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * قائمة المستخدمين (24.1 · 12.13) — المرجع الواحد لكلّ حسابات المنصّة.
 *
 * الجدول يفتح بأعمدته الافتراضيّة (5–7) والباقي موجود ومخفيّ خلف زرّ «أعمدة»،
 * **واختيار المستخدم يُحفَظ له** (2.15-د). والفلاتر ثلاثة ظاهرة والباقي مطويّ.
 */
class UserDirectory
{
    /** كلّ الأعمدة المتاحة: المفتاح => العنوان — والافتراضيّ منها في الإعدادات */
    public const COLUMNS = [
        'name' => 'المستخدم',
        'code' => 'الكود',
        'email' => 'البريد',
        'phone' => 'الموبايل',
        'country' => 'الدولة',
        'status' => 'الحالة',
        'roles' => 'الأدوار',
        'xp' => 'XP',
        'last_seen' => 'آخر دخول',
        'created_at' => 'تاريخ التسجيل',
    ];

    /** حالات الحساب (2.5-د) */
    public const STATUSES = [
        'pending' => 'تحت المراجعة',
        'active' => 'معتمَد',
        'rejected' => 'مرفوض',
        'suspended' => 'معلّق',
        'banned' => 'محظور',
    ];

    public function query(Request $request): LengthAwarePaginator
    {
        $search = trim((string) $request->query('q', ''));

        return User::query()
            ->with('roles')
            // بحث موحّد بالكود والاسم والبريد والموبايل (24.1)
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('role'), fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('key', $role)))
            ->when($request->integer('days'), fn ($q, $days) => $q->where('created_at', '>=', now()->subDays($days)))
            // فلاتر متقدّمة مطويّة
            ->when($request->query('country'), fn ($q, $country) => $q->where('country_id', $country))
            ->when($request->integer('min_xp'), fn ($q, $xp) => $q->where('xp', '>=', $xp))
            ->when($request->query('idle_days'), fn ($q, $idle) => $q->where(fn ($inner) => $inner
                ->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<=', now()->subDays((int) $idle))))
            ->latest('id')
            ->paginate((int) setting('admin.users.per_page', 25))
            ->withQueryString();
    }

    /** الأعمدة الظاهرة: اختيار المستخدم إن وُجد، وإلّا الافتراضيّ من الإعدادات */
    public function visibleColumns(User $viewer): array
    {
        $saved = ($viewer->table_columns ?? [])['admin_users'] ?? null;
        $default = setting('admin.users.default_columns', ['name', 'code', 'email', 'status', 'roles', 'last_seen']);

        $columns = is_array($saved) && $saved !== [] ? $saved : (is_array($default) ? $default : []);
        $columns = array_values(array_intersect($columns, array_keys(self::COLUMNS)));

        // الاسم عمود الهويّة ولا يُخفى أبدًا حتى لا يصير الجدول بلا مرساة
        return $columns === [] ? ['name'] : (in_array('name', $columns, true) ? $columns : array_merge(['name'], $columns));
    }

    public function saveColumns(User $viewer, array $columns): void
    {
        $columns = array_values(array_intersect($columns, array_keys(self::COLUMNS)));
        $tables = $viewer->table_columns ?? [];
        $tables['admin_users'] = $columns;

        $viewer->forceFill(['table_columns' => $tables])->saveQuietly();
    }

    /** تقنيع البيانات الحسّاسة قابل للإطفاء من الإعدادات (24.1) */
    public function mask(?string $value, string $type = 'email'): string
    {
        if (! $value) {
            return '—';
        }

        if (! setting('admin.users.mask_sensitive', true)) {
            return $value;
        }

        if ($type === 'email' && str_contains($value, '@')) {
            [$name, $domain] = explode('@', $value, 2);

            return mb_substr($name, 0, 2).str_repeat('•', max(2, mb_strlen($name) - 2)).'@'.$domain;
        }

        return str_repeat('•', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }

    /** حالة الحساب بمعنى واحد من قاموس 2.16 */
    public function statusState(string $status): string
    {
        return match ($status) {
            'active' => 'ok',
            'pending' => 'warn',
            'rejected', 'banned' => 'danger',
            default => 'idle',
        };
    }

    public function roleOptions(): array
    {
        return Role::orderBy('layer')->orderBy('id')->pluck('name_ar', 'key')->all();
    }

    /**
     * أدوات احتواء الحساب المسيء (12.1) — **إجراءات لا تسميات عرض**.
     * وما لا يملكه المشاهد **يُخفى ولا يُعطَّل** (2.15-أ-7)، فالقائمة تُبنى بصلاحيّاته.
     *
     * @return array<int, array{key:string, label:string, danger:bool}>
     */
    public function moderationActions(User $viewer, User $subject): array
    {
        $actions = [];
        $contained = in_array($subject->status, ['banned', 'suspended'], true);

        if ($viewer->allows('account_suspension.create') && ! $contained) {
            $actions[] = ['key' => 'ban', 'label' => 'حظر الحساب', 'danger' => true];
            $actions[] = ['key' => 'suspend', 'label' => 'تعليق مؤقّت', 'danger' => true];
        }

        if ($viewer->allows('account_suspension.delete') && $contained) {
            $actions[] = ['key' => 'release', 'label' => 'رفع الاحتواء', 'danger' => false];
        }

        if ($viewer->allows('user_sessions.delete')) {
            $actions[] = ['key' => 'sessions', 'label' => 'إنهاء كلّ جلساته', 'danger' => false];
        }

        if ($viewer->allows('users.edit')) {
            if (! $subject->email_verified_at) {
                $actions[] = ['key' => 'verify-email', 'label' => 'تأكيد بريده يدويًّا', 'danger' => false];
            }

            $actions[] = ['key' => 'password-link', 'label' => 'رابط تغيير كلمة السرّ', 'danger' => false];
        }

        // الانتحال مجموعة محميّة، ولا يُنتحَل مالك المنصّة ولا المشاهد نفسه
        if ($viewer->allows('impersonation.create') && $subject->id !== $viewer->id && ! $subject->isPlatformOwner()) {
            $actions[] = ['key' => 'impersonate', 'label' => 'تصفّح كـ' .$subject->shortName(1), 'danger' => false];
        }

        return $actions;
    }

    /** هل يظهر قسم الاحتواء أصلًا؟ — لا نعرض عنوانًا فارغًا بلا فعل واحد */
    public function canModerate(User $viewer, User $subject): bool
    {
        return $this->moderationActions($viewer, $subject) !== [];
    }

    /** تابات صفحة المستخدم (12.1) — وتاب التطوّع بعد «متقدّم» لمن له صلاحيّة */
    public function tabsFor(User $viewer, User $subject): array
    {
        $tabs = [
            'profile' => 'بيانات',
            'wallet' => 'محفظة ومعاملات',
            'learning' => 'تدريبات',
            'certificates' => 'شهادات',
            'advanced' => 'متقدّم',
        ];

        $volunteerPermission = (string) setting('admin.user_tabs.volunteer_permission', 'memberships.view');

        if ($viewer->allows($volunteerPermission)) {
            $tabs['volunteer'] = 'التطوّع';
        }

        return $tabs;
    }
}
