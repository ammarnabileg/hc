<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;
use App\Support\Scope\ScopeFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * قائمة المستخدمين (24.1 · 12.13) — المرجع الواحد لكلّ حسابات المنصّة.
 *
 * الجدول يفتح بأعمدته الافتراضيّة (5–7) والباقي موجود ومخفيّ خلف زرّ «أعمدة»،
 * **واختيار المستخدم يُحفَظ له** (2.15-د). والفلاتر ثلاثة ظاهرة والباقي مطويّ.
 */
class UserDirectory
{
    /** مفاتيح الأعمدة الداخليّة — ترتيب العرض، لا نصّ (2.13-ب) */
    private const COLUMN_KEYS = [
        'name', 'code', 'email', 'phone', 'country', 'status', 'roles', 'xp', 'last_seen', 'created_at',
    ];

    /** مفاتيح حالات الحساب الداخليّة (2.5-د) — لا نصّ */
    private const STATUS_KEYS = ['pending', 'active', 'rejected', 'suspended', 'banned'];

    /**
     * كلّ الأعمدة المتاحة: المفتاح => العنوان — والافتراضيّ منها في الإعدادات.
     * العناوين من `setting()` لا محروقة (2.13).
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        return [
            'name' => (string) setting('admin.users.column.name', 'المستخدم'),
            'code' => (string) setting('admin.users.column.code', 'الكود'),
            'email' => (string) setting('admin.users.column.email', 'البريد'),
            'phone' => (string) setting('admin.users.column.phone', 'الموبايل'),
            'country' => (string) setting('admin.users.column.country', 'الدولة'),
            'status' => (string) setting('admin.users.column.status', 'الحالة'),
            'roles' => (string) setting('admin.users.column.roles', 'الأدوار'),
            'xp' => (string) setting('admin.users.column.xp', 'XP'),
            'last_seen' => (string) setting('admin.users.column.last_seen', 'آخر دخول'),
            'created_at' => (string) setting('admin.users.column.created_at', 'تاريخ التسجيل'),
        ];
    }

    /**
     * حالات الحساب بعناوينها العربيّة (2.5-د) — من `setting()` لا محروقة.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'pending' => (string) setting('admin.users.status.pending', 'تحت المراجعة'),
            'active' => (string) setting('admin.users.status.active', 'معتمَد'),
            'rejected' => (string) setting('admin.users.status.rejected', 'مرفوض'),
            'suspended' => (string) setting('admin.users.status.suspended', 'معلّق'),
            'banned' => (string) setting('admin.users.status.banned', 'محظور'),
        ];
    }

    public function query(Request $request): LengthAwarePaginator
    {
        $search = trim((string) $request->query('q', ''));

        $query = User::query()->with('roles');

        /*
         | ⭐ النطاق إلزاميّ مع كلّ صلاحيّة (12.2.1-ب).
         | الحارس على المسار يفحص «هل يستطيع مبدئيًّا؟» بلا هدف، فمَن مُنِح
         | `users.list@TEAM` كان يفتح دليل المنصّة **كاملًا**. الحصر هنا على
         | البيانات نفسها لا على الباب وحده.
         */
        app(ScopeFilter::class)->applyToUsers($query, $request->user(), 'users.list');

        return $query
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
        $columns = array_values(array_intersect($columns, self::COLUMN_KEYS));

        // الاسم عمود الهويّة ولا يُخفى أبدًا حتى لا يصير الجدول بلا مرساة
        return $columns === [] ? ['name'] : (in_array('name', $columns, true) ? $columns : array_merge(['name'], $columns));
    }

    public function saveColumns(User $viewer, array $columns): void
    {
        $columns = array_values(array_intersect($columns, self::COLUMN_KEYS));
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
            $actions[] = ['key' => 'ban', 'label' => setting('admin_users.user_directory.moderation_actions_1', 'حظر الحساب'), 'danger' => true];
            $actions[] = ['key' => 'suspend', 'label' => setting('admin_users.user_directory.moderation_actions_2', 'تعليق مؤقّت'), 'danger' => true];
        }

        if ($viewer->allows('account_suspension.delete') && $contained) {
            $actions[] = ['key' => 'release', 'label' => setting('admin_users.user_directory.moderation_actions_3', 'رفع الاحتواء'), 'danger' => false];
        }

        // الانتحال مجموعة محميّة، ولا يُنتحَل مالك المنصّة ولا المشاهد نفسه
        if ($viewer->allows('impersonation.create') && $subject->id !== $viewer->id && ! $subject->isPlatformOwner()) {
            $actions[] = ['key' => 'impersonate', 'label' => strtr(setting('admin_users.user_directory.moderation_actions_4', 'تصفّح كـ:p1'), [':p1' => (string) ($subject->shortName(1))]), 'danger' => false];
        }

        // تصدير بيانات المستخدم كملفّ — بصلاحيّته وحدها (12.1-متقدّم-6)
        if ($viewer->allows('admin_user_detail.export')) {
            $actions[] = ['key' => 'export', 'label' => setting('admin_users.user_directory.moderation_actions_5', 'تصدير بياناته كملفّ'), 'danger' => false];
        }

        return $actions;
    }

    /**
     * أدوات **تاب الأمان** (12.1-الأمان): رابط تغيير كلمة السرّ القابل للنسخ ·
     * الجلسات النشطة وإنهاؤها · تأكيد البريد يدويًّا.
     *
     * فُصلت عن أدوات الاحتواء لأنّها أدوات دعمٍ يوميّة لا عقوبة — وخلطُهما في
     * قسمٍ أحمر واحد كان بيخوّف فريق الدعم من فعلٍ عاديّ.
     *
     * @return array<int, array{key:string, label:string, danger:bool}>
     */
    public function securityActions(User $viewer, User $subject): array
    {
        $actions = [];

        if ($viewer->allows('users.edit')) {
            $actions[] = ['key' => 'password-link', 'label' => setting('admin_users.user_directory.security_actions_1', 'رابط تغيير كلمة السرّ'), 'danger' => false];

            if (! $subject->email_verified_at) {
                $actions[] = ['key' => 'verify-email', 'label' => setting('admin_users.user_directory.security_actions_2', 'تأكيد بريده يدويًّا'), 'danger' => false];
            }
        }

        if ($viewer->allows('user_sessions.delete')) {
            $actions[] = ['key' => 'sessions', 'label' => setting('admin_users.user_directory.security_actions_3', 'إنهاء كلّ جلساته'), 'danger' => false];
        }

        return $actions;
    }

    /** هل يظهر قسم الاحتواء أصلًا؟ — لا نعرض عنوانًا فارغًا بلا فعل واحد */
    public function canModerate(User $viewer, User $subject): bool
    {
        return $this->moderationActions($viewer, $subject) !== [];
    }

    /**
     * تابات صفحة المستخدم (12.1) — بالترتيب المنصوص:
     * المعلومات الأساسيّة · **الجداول** · الأمان · الإدارة · متقدّم · التطوّع،
     * ومعها تابات المحتوى (الأرصدة والتدريبات والشهادات).
     *
     * وكلّ تابٍ **يُخفى لمن لا يملك صلاحيّته** ولا يُعرَض معطَّلًا (2.15-أ-7).
     */
    public function tabsFor(User $viewer, User $subject): array
    {
        $tabs = ['profile' => setting('admin_users.user_directory.tabs_for_1', 'بيانات')];

        // الجداول الثلاثة (معاملات · سحوبات · دعوات) ماليّة الطابع — بصلاحيّة العرض
        if ($viewer->allows('admin_user_detail.view')) {
            $tabs['tables'] = setting('admin_users.user_directory.tabs_for_2', 'الجداول');
        }

        $tabs += [
            'wallet' => setting('admin_users.user_directory.tabs_for_3', 'أرصدة'),
            'learning' => setting('admin_users.user_directory.tabs_for_4', 'تدريبات'),
            'certificates' => setting('admin_users.user_directory.tabs_for_5', 'شهادات'),
        ];

        // تاب الأمان: رابط تغيير كلمة السرّ والجلسات النشطة (12.1-الأمان)
        if ($viewer->allows('users.edit') || $viewer->allows('user_sessions.delete')) {
            $tabs['security'] = setting('admin_users.user_directory.tabs_for_6', 'الأمان');
        }

        // تاب الإدارة: اعتماد/رفض الحساب وتعيين الأدوار (12.1-الإدارة)
        if ($viewer->allows('user_approvals.approve') || $viewer->allows('user_approvals.reject') || $viewer->allows('roles.assign')) {
            $tabs['admin'] = setting('admin_users.user_directory.tabs_for_7', 'الإدارة');
        }

        $tabs['advanced'] = setting('admin_users.user_directory.tabs_for_8', 'متقدّم');

        $volunteerPermission = (string) setting('admin.user_tabs.volunteer_permission', 'memberships.view');

        if ($viewer->allows($volunteerPermission)) {
            $tabs['volunteer'] = setting('admin_users.user_directory.tabs_for_9', 'التطوّع');
        }

        return $tabs;
    }

    /**
     * ⭐ **فلتر «من فترة لفترة»** على جداول صفحة المستخدم (12.1-الجداول).
     *
     * كانت الجداول تعرض آخر 25 صفًّا ثابتًا بلا فلتر — فمَن يسأل «إيه اللي حصل
     * في رمضان؟» ماكانش يلاقي إجابة. والمدى الافتراضيّ آخر 30 يومًا (2.15-أ-9).
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function period(Request $request): array
    {
        $days = max(1, (int) setting('ux.lists.default_range_days', 30));

        $from = ($this->date($request->query('from')) ?? now()->subDays($days))->startOfDay();
        $to = ($this->date($request->query('to')) ?? now())->endOfDay();

        // مدى مقلوب = صفر نتائج بلا سبب ظاهر — فنصلّحه بدل ما نعاقب المستخدم عليه
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return ['from' => $from, 'to' => $to];
    }

    private function date(mixed $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
