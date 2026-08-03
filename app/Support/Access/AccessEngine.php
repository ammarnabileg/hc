<?php

namespace App\Support\Access;

use App\Models\Membership;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * محرّك الصلاحيّات (الدستور 12.2.1).
 *
 * قواعد حاكمة:
 *  1) الصلاحيّة تُسمّى «المورد.الفعل».
 *  2) النطاق إلزاميّ ويُقيَّم داخل سياق العضويّة النشطة.
 *  3) Deny > Allow دائمًا.
 *  4) منع تصعيد الامتياز: لا يمنح أحدٌ ما لا يملك ولا نطاقًا أوسع.
 *  5) عزل الحسّاس: الماليّ ونظائره لمالك المنصّة وحده.
 *  6) أسبقيّة طبقة المنصّة: الأدمنز أعلى من طبقة التطوّع.
 */
class AccessEngine
{
    /** كاش لكلّ طلب: user_id => Collection<Grant> */
    private array $cache = [];

    /** مفاتيح المجموعة المحميّة (مالك المنصّة وحده) — تُمسَح مع الكاش */
    private ?array $ownerOnly = null;

    /** شروط المصفوفة لكلّ صلاحيّة: key => string[] — تُمسَح مع الكاش */
    private ?array $permissionConditions = null;

    /** سقف نطاقات المصفوفة لكلّ صلاحيّة: key => string[] — تُمسَح مع الكاش */
    private ?array $allowedScopes = null;

    /** مفاتيح الصلاحيّات التي تفتح باب اللوحة — تُمسَح مع الكاش */
    private ?array $adminKeys = null;

    /** هل هو مالك المنصّة؟ user_id => bool — تُمسَح مع الكاش */
    private array $owners = [];

    public function __construct(
        private readonly ScopeResolver $scopes,
        private readonly ConditionEvaluator $conditions,
        private readonly MembershipContext $context,
    ) {}

    /**
     * هل يملك المستخدم هذه الصلاحيّة على هذا الهدف؟
     *
     * @param  string  $permissionKey  مثل: volunteers.approve
     * @param  mixed  $target  السجلّ المستهدَف (اختياريّ — بدونه يُفحَص المبدأ فقط)
     */
    public function allows(User $user, string $permissionKey, mixed $target = null, ?Membership $context = null): bool
    {
        /*
         | ⭐ 6) مالك المنصّة فوق الجميع (12.2.1-ز-5) — **قبل** فحص المنع.
         |
         | والنصّان يُقرآن معًا لا يُلغي أحدهما الآخر:
         |  • ز-1 «المنع يغلب الإذن» يحكم **تعارض المصادر على المُخوَّلين**: مَن مُنِح
         |    من مصدرٍ ومُنِع من آخر ⟵ المنع يكسب. وهذا باقٍ كما هو أدناه لكلّ
         |    مَن سوى المالك، بلا استثناءٍ ولا تخفيف.
         |  • ز-5 «ويظلّ **مالك المنصّة** فوق الجميع بمجموعته المحميّة» يحكم **موقع
         |    الحساب الجذر من السلّم كلّه**: فهو ليس طرفًا في تعارض المصادر أصلًا،
         |    وصلاحيّته ليست ممنوحةً من صفٍّ حتى يُلغيها صفّ.
         |
         | وكان الترتيب المقلوب يجعل **صفّ منعٍ واحدًا** — يكتبه أيّ مسارٍ يكتب في
         | `permission_user` — يقفل الحساب الجذر عن منصّته، ومعه شاشةُ الأدوار التي
         | وحدَها تفكّ القفل. فقفلٌ لا رجعة فيه، وهو نقيض «فوق الجميع».
         */
        if ($this->isPlatformOwner($user)) {
            return true;
        }

        $membership = $context ?? $this->context->for($user);
        $grants = $this->grantsFor($user)->where('permissionKey', $permissionKey);

        // 3) Deny > Allow — يُفحَص المنع أوّلًا وقبل أيّ شيء (لكلّ مَن سوى المالك).
        // وصفّ منعٍ بنطاقٍ تالف يُحسَب مانعًا: ما لا نفهمه لا نقرؤه إذنًا.
        foreach ($grants->where('effect', 'deny') as $deny) {
            if (! $deny->hasValidScope() || $this->matches($deny, $user, $target, $membership)) {
                return false;
            }
        }

        // 5) عزل الحسّاس: ما هو owner-only لا يُمنَح لغير مالك المنصّة مهما كان الدور
        if ($this->isOwnerOnly($permissionKey)) {
            return false;
        }

        foreach ($grants->where('effect', 'allow') as $allow) {
            if ($this->matches($allow, $user, $target, $membership)) {
                return true;
            }
        }

        return false;
    }

    public function denies(User $user, string $permissionKey, mixed $target = null, ?Membership $context = null): bool
    {
        return ! $this->allows($user, $permissionKey, $target, $context);
    }

    /**
     * 4) منع تصعيد الامتياز:
     * لا يُسنِد أحدٌ صلاحيّةً لا يملكها، ولا بنطاقٍ أوسع من نطاقه فيها.
     *
     * ⭐ ويفحص الشروط كذلك: صفٌّ مشروطٌ لا يتحقّق شرطُه في سياق المانح **لا يؤهّله
     * للمنح**، وإلّا صار الشرط بابًا خلفيًّا يُغسَل به الامتياز (يملكها «داخل النافذة»
     * فيمنحها بلا شرط). والفحص بلا هدف — فالشروط المقيسة على سجلٍّ بعينه لا معنى
     * لها وقت الإسناد، أمّا شروط السياق (`active_membership` · `platform_owner`) فتُقاس.
     */
    public function canGrant(User $granter, string $permissionKey, string $scope, ?Membership $context = null): bool
    {
        if ($this->isPlatformOwner($granter)) {
            return true;
        }

        if ($this->isOwnerOnly($permissionKey)) {
            return false;
        }

        $membership = $context ?? $this->context->for($granter);
        $target = array_search($scope, config('access.scopes'), true);

        if ($target === false) {
            return false;
        }

        $grants = $this->grantsFor($granter)->where('permissionKey', $permissionKey);
        $intrinsic = $this->conditionsOf($permissionKey);

        // منعٌ صريح على الصلاحيّة يبطل المنح كلّه — والنطاق التالف يُحسَب مانعًا
        foreach ($grants->where('effect', 'deny') as $deny) {
            if (! $deny->hasValidScope() || $deny->scopeRank() >= $target) {
                return false;
            }
        }

        foreach ($grants->where('effect', 'allow') as $allow) {
            if (! $allow->hasValidScope() || $allow->scopeRank() < $target) {
                continue;
            }

            if (! $this->membershipApplies($allow, $membership)) {
                continue;
            }

            if (! $this->conditions->passes([...$intrinsic, ...$allow->conditions], $granter, null, $membership)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * ⭐ باب لوحة الإدارة (12.2.1-أ): «الشاشة نتيجةٌ للصلاحيّات لا صلاحيّةً بذاتها،
     * ومنه: لوحة الإدارة تظهر لمن له **أيّ** صلاحيّة».
     *
     * فلا مفتاح `admin_panel.view`. والباب كان يُحسَب بقائمة **مفاتيح** تحرس مسارات
     * `admin.*`، فانقلب على النصّ في الاتّجاهين:
     *
     *  • **يقفل في وجه أصحاب الصلاحيّة:** 246 مفتاحًا من 1033 فقط دخلت الحساب،
     *    فمَن يملك `courses.manage@ALL` أو `paths.manage@ALL` — ولا تحرس `manage`
     *    مسارًا بذاتها — يُردّ، ومعه دور **«فريق التوظيف»** كلّه (49 صلاحيّة،
     *    وشاشاتُه تحت `volunteer.*` لا `admin.*`) وهو قالبٌ منصوص في 12.2.3-ب-17.
     *  • **ويقفل لأنّ المفتاح مشترَك:** `store_products.list` و`events.list` و
     *    `referrals.view` تحرس شاشات إدارة **ويحملها المتدرّب أيضًا**، فأُقصيت
     *    كلّها — فسقط معها مسؤول المتجر ومسؤول الفعاليّات.
     *
     * ⭐ فالمعيار الآن **مصدر الصفّ لا اسم المفتاح** (12.2.3): مَن يحمل صلاحيّةً
     * من دورٍ **خارج قالب المستخدم النهائيّ** (طبقة `user`) — أو من استثناءٍ فرديّ
     * كتبه مسؤولٌ بيده — فهو صاحب سلطةٍ ويفتح الباب. ومَن كلُّ ما يحمله من قالب
     * المستخدم النهائيّ **لا يفتحه**، وهو عين المتدرّب و«تحت المراجعة».
     *
     * وهذا يحسم الاشتباك الذي لا يحسمه اسمُ المفتاح: `store_products.list@ALL`
     * **نفسها** يحملها المتدرّب (كتالوج المتجر) ويحملها مسؤول التسويق — والفارق
     * الوحيد بينهما هو **مِن أين جاءت**.
     *
     * ثمّ **كلّ صفحةٍ بعد الباب تُحرَس بصلاحيّتها هي** — والباب لا يمنح شيئًا.
     */
    public function opensAdminPanel(User $user): bool
    {
        if ($this->isPlatformOwner($user)) {
            return true;
        }

        $endUserLayer = (string) config('access.panel.end_user_layer', 'user');

        // نمرّ على ما يملكه هو لا على السطح كلّه: المتدرّب لا يستدعي `allows` ولا مرّة
        $candidates = $this->grantsFor($user)
            ->filter(fn (Grant $grant) => $grant->isAllow() && $grant->layer !== $endUserLayer)
            ->pluck('permissionKey')
            ->unique();

        foreach ($candidates as $key) {
            if ($this->allows($user, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * مفاتيح الصلاحيّات الإداريّة — تُشتقّ من جدول المسارات نفسه لا من قائمة محروقة:
     *   ما يحرس مسارًا داخل `admin.*`
     *   − ولا يحرس أيّ مسارٍ خارجها (وإلّا فهو مفتاح صفحةٍ عامّة لا سلطةَ إدارة)
     *   − ولا يدخل في قالب المستخدم النهائيّ (طبقة `user` — 12.2.3).
     *
     * @return array<int, string>
     */
    public function adminPermissionKeys(): array
    {
        return $this->adminKeys ??= AdminPanelSurface::keys();
    }

    /** أوسع نطاق يملكه المستخدم في صلاحيّة — يفيد في بناء الاستعلامات */
    public function widestScope(User $user, string $permissionKey): ?string
    {
        if ($this->isPlatformOwner($user)) {
            return 'ALL';
        }

        $allows = $this->grantsFor($user)
            ->where('permissionKey', $permissionKey)
            ->where('effect', 'allow')
            ->filter(fn (Grant $g) => $g->hasValidScope());

        if ($allows->isEmpty()) {
            return null;
        }

        return $allows->sortByDesc(fn (Grant $g) => $g->scopeRank())->first()->scope;
    }

    public function isPlatformOwner(User $user): bool
    {
        // كاش لكلّ طلب: كانت تُستدعى مرّاتٍ داخل الحلقة الواحدة فتضرب القاعدة كلّ مرّة
        return $this->owners[$user->id] ??= $this->roleKeys($user)->contains(config('access.owner_role'));
    }

    /** مسح الكاش — يُستدعى بعد أيّ تغيير في الأدوار أو الإسنادات */
    public function forget(?User $user = null): void
    {
        if ($user) {
            unset($this->cache[$user->id], $this->owners[$user->id]);

            return;
        }

        $this->cache = [];
        $this->owners = [];
        $this->ownerOnly = null;
        $this->permissionConditions = null;
        $this->allowedScopes = null;
        $this->adminKeys = null;
    }

    // ------------------------------------------------------------------ داخليّ

    private function matches(Grant $grant, User $user, mixed $target, ?Membership $membership): bool
    {
        /*
         | ⭐ سقف نطاق المصفوفة (12.2.2) يُفرَض **وقت التقييم** كذلك لا وقت الكتابة
         | وحده: صفُّ إذنٍ بنطاقٍ يتجاوز `allowed_scopes` المنصوصة — أيًّا كان الباب
         | الذي كتبه (استيراد · هجرة · كتابة مباشرة) — **لا يُقرَأ إذنًا**، وهو نفس
         | حكم النطاق التالف: ما يتجاوز النصّ لا نفهمه فلا نمنح عليه.
         |
         | والمنع لا يخضع لهذا السقف عمدًا: توسيع **المنع** تشديدٌ لا تصعيد،
         | و«Deny > Allow» (12.2.1-ز-1) يجب أن تبقى بلا ثغرةٍ يُفلَت منها.
         */
        if ($grant->isAllow() && ! $this->withinMatrixCeiling($grant)) {
            return false;
        }

        if (! $this->membershipApplies($grant, $membership)) {
            return false;
        }

        if (! $this->scopes->covers($grant->scope, $user, $target, $membership)) {
            return false;
        }

        /*
         | ⭐ شرطان يُقيَّمان معًا:
         |  (أ) شرط **المصفوفة** — عمود «الشرط» في 12.2.2 صفةٌ للصلاحيّة نفسها
         |      («المالك فقط» · «ليس نفسه» · «الحالة = منشور»)، وكان يُخزَّن نصًّا
         |      عربيًّا يُعرَض ولا يُقيَّم. صار مفاتيح مقفولةً تُقيَّم وقت الطلب.
         |  (ب) شرط **الصفّ** — ما أضافه المسؤول على إسناد بعينه.
         */
        $conditions = [...$this->conditionsOf($grant->permissionKey), ...$grant->conditions];

        return $this->conditions->passes($conditions, $user, $target, $membership);
    }

    /**
     * ⭐ نطاقات الصلاحيّة المنصوصة في المصفوفة (12.2.2) — المرجع لا `permissions.json`
     * ولا اجتهاد: تُقرأ من عمود `allowed_scopes` الذي زُرِع منها.
     *
     * @return array<int, string>
     */
    public function allowedScopesOf(string $permissionKey): array
    {
        $this->allowedScopes ??= Permission::query()
            ->pluck('allowed_scopes', 'key')
            ->map(fn ($value) => is_array($value) ? $value : (json_decode((string) $value, true) ?: []))
            ->all();

        return $this->allowedScopes[$permissionKey] ?? [];
    }

    /** هل هذا النطاق داخل سقف المصفوفة؟ (صلاحيّةٌ بلا سقفٍ منصوص تقبل الستّة) */
    public function withinAllowedScopes(string $permissionKey, string $scope): bool
    {
        $allowed = $this->allowedScopesOf($permissionKey);

        return $allowed === [] || in_array($scope, $allowed, true);
    }

    private function withinMatrixCeiling(Grant $grant): bool
    {
        return $this->withinAllowedScopes($grant->permissionKey, $grant->scope);
    }

    /**
     * شروط المصفوفة لصلاحيّة — مفاتيح مقفولة لا نصوص عربيّة.
     *
     * @return array<int, string>
     */
    private function conditionsOf(string $permissionKey): array
    {
        $this->permissionConditions ??= Permission::query()
            ->whereNotNull('condition_keys')
            ->pluck('condition_keys', 'key')
            ->map(fn ($value) => is_array($value) ? $value : (json_decode((string) $value, true) ?: []))
            ->all();

        return $this->permissionConditions[$permissionKey] ?? [];
    }

    /**
     * الإسناد المرتبط بعضويّة لا يسري إلّا داخلها (قفص العضويّة).
     * والإسناد بلا عضويّة (أدوار المنصّة) يسري في كلّ سياق.
     */
    private function membershipApplies(Grant $grant, ?Membership $membership): bool
    {
        if ($grant->membershipId === null) {
            return true;
        }

        return $membership !== null && $grant->membershipId === $membership->id;
    }

    /** @return Collection<int, Grant> */
    private function grantsFor(User $user): Collection
    {
        if (isset($this->cache[$user->id])) {
            return $this->cache[$user->id];
        }

        $grants = collect();

        // (أ) من الأدوار
        $roleRows = \DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->get([
                'permissions.key as permission_key',
                'permission_role.scope',
                'permission_role.effect',
                'permission_role.conditions',
                'role_user.membership_id',
                'roles.key as role_key',
                'roles.layer as role_layer',
            ]);

        foreach ($roleRows as $row) {
            $grants->push(new Grant(
                permissionKey: $row->permission_key,
                scope: $row->scope,
                effect: $row->effect,
                conditions: $this->decode($row->conditions),
                membershipId: $row->membership_id,
                origin: 'role:'.$row->role_key,
                layer: $row->role_layer,
            ));
        }

        // (ب) استثناءات فرديّة فوق الأدوار — بنفس قاعدة Deny > Allow
        $userRows = \DB::table('permission_user')
            ->join('permissions', 'permissions.id', '=', 'permission_user.permission_id')
            ->where('permission_user.user_id', $user->id)
            ->get([
                'permissions.key as permission_key',
                'permission_user.scope',
                'permission_user.effect',
                'permission_user.conditions',
                'permission_user.membership_id',
            ]);

        foreach ($userRows as $row) {
            $grants->push(new Grant(
                permissionKey: $row->permission_key,
                scope: $row->scope,
                effect: $row->effect,
                conditions: $this->decode($row->conditions),
                membershipId: $row->membership_id,
                origin: 'user',
            ));
        }

        return $this->cache[$user->id] = $grants;
    }

    private function roleKeys(User $user): Collection
    {
        return collect(\DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->pluck('roles.key'));
    }

    private function isOwnerOnly(string $permissionKey): bool
    {
        /*
         | خاصّيّة لا `static`: القائمة المحميّة كانت تُحفَظ في متغيّر ساكن يعيش
         | بعمر العمليّة كلّها، فلا يمسحه `forget()` ولا انتهاء الطلب. وفي عامل
         | طوابير طويل العمر يعني ذلك أنّ صلاحيّةً وُسِمَت ماليّةً للتوّ **تبقى
         | غير محميّة** في ذلك العامل حتى يُعاد تشغيله — وهو آخر ما نحتمله في
         | مجموعةٍ عزلُها قاعدةٌ حمراء.
         */
        $this->ownerOnly ??= Permission::query()->where('is_owner_only', true)->pluck('key')->all();

        return in_array($permissionKey, $this->ownerOnly, true);
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return json_decode($value, true) ?: [];
        }

        return [];
    }
}
