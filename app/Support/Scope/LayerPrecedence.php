<?php

namespace App\Support\Scope;

use App\Models\User;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ أسبقيّة طبقة المنصّة — قاعدة حسمٍ فعليّة (الدستور 12.2.1-ز-5).
 *
 * «**الأدمنز أعلى من أيّ طبقة أخرى بلا استثناء** — فهم أساس العمل وموظّفو المنصّة،
 * أمّا المتطوّعون فمستخدمون في الأصل انضمّت إليهم طبقة تطوّع. وعليه: **مشرف عام
 * التطوّع سقف طبقة التطوّع فقط** لا سقف المنصّة، و**الأدمن العامّ يعلوه في كلّ
 * تعارض**؛ ويظلّ **مالك المنصّة** فوق الجميع بمجموعته المحميّة».
 *
 * كان `config/access.php: platform_layer` معرَّفًا **بلا استخدام واحد** في المشروع،
 * فالقاعدة كانت نصًّا لا حكمًا. وهنا تصير حكمًا يُطبَّق:
 *
 *  1) **مالك المنصّة فوق الجميع** — لا يعلوه حكم.
 *  2) **Deny > Allow تبقى فوق هذه القاعدة** — المنع الصريح يكسب أوّلًا
 *     (يحسمه `AccessEngine::allows()` قبل أن يصل الأمر إلى هنا).
 *  3) فإذا لم يحسم المنعُ الأمرَ وتعارض **حكمان على نفس الحدث** من طبقتين،
 *     **حكم طبقة المنصّة هو الحاكم** — فمَن يحمل الدورين معًا لا تُقاس سلطته
 *     على الحدث بحكم طبقة التطوّع.
 *
 * ولا تُعدَّل هنا نتيجةُ `allows()` ولا يُوسَّع أحد: القاعدة تحسم **أيّ الحكمين
 * يُقرأ**، والمنع فوقها والمالك فوق الجميع.
 */
class LayerPrecedence
{
    /** ترتيب الطبقات من الأعلى — الأدمنز أعلى من التطوّع، والتطوّع أعلى من المستخدم */
    public const ORDER = ['platform', 'volunteer', 'user'];

    public function __construct(private readonly AccessEngine $access) {}

    /**
     * الطبقة الحاكمة لهذا المستخدم: `owner` لمالك المنصّة، وإلّا أعلى طبقةٍ يحمل
     * فيها دورًا — والحساب بلا أدوار طبقتُه `user`.
     */
    public function layerOf(User $user): string
    {
        if ($this->access->isPlatformOwner($user)) {
            return 'owner';
        }

        $layers = $this->layersOf($user);

        foreach (self::ORDER as $layer) {
            if (in_array($layer, $layers, true)) {
                return $layer;
            }
        }

        return 'user';
    }

    /**
     * هل يعلو الأوّلُ الثاني عند التعارض؟ (12.2.1-ز-5)
     * مالك المنصّة فوق الجميع، ثمّ الأدمنز، ثمّ التطوّع، ثمّ المستخدم.
     */
    public function outranks(User $first, User $second): bool
    {
        return $this->rankOf($first) > $this->rankOf($second);
    }

    /** رتبة الطبقة عدديًّا — الأكبر أعلى */
    public function rankOf(User $user): int
    {
        return match ($this->layerOf($user)) {
            'owner' => 3,
            'platform' => 2,
            'volunteer' => 1,
            default => 0,
        };
    }

    /**
     * ⭐ النطاق الحاكم في صلاحيّة بعينها.
     *
     * حين يحمل المستخدم الصلاحيّة نفسها من **طبقتين**، يُقرأ حكم طبقة المنصّة —
     * فمشرف عام التطوّع سقف طبقته وحدها، ولا يرفع حكمُه سقفَ حكمِ الأدمن ولا
     * يخفضه. وحين لا ترى طبقةُ المنصّة هذه الصلاحيّة أصلًا، يبقى حكم طبقته.
     */
    public function governingScope(?User $user, string $permissionKey): ?string
    {
        if ($user === null) {
            return null;
        }

        // مالك المنصّة فوق الجميع
        if ($this->access->isPlatformOwner($user)) {
            return 'ALL';
        }

        $widest = $this->access->widestScope($user, $permissionKey);

        if ($widest === null) {
            return null;
        }

        $platform = $this->widestScopeInLayer($user, $permissionKey, (string) config('access.platform_layer', 'platform'));

        // طبقة المنصّة حكمت ⟵ حكمها هو الحاكم، وإلّا فحكم طبقته كما هو
        return $platform ?? $widest;
    }

    /** طبقات الأدوار التي يحملها المستخدم فعلًا */
    private function layersOf(User $user): array
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->pluck('roles.layer')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** أوسع نطاق `allow` يأتي من أدوار طبقةٍ بعينها — أو null إن لم تحكم الطبقة */
    private function widestScopeInLayer(User $user, string $permissionKey, string $layer): ?string
    {
        $scopes = DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('roles.layer', $layer)
            ->where('permissions.key', $permissionKey)
            ->where('permission_role.effect', 'allow')
            ->pluck('permission_role.scope')
            ->all();

        return $this->widest($scopes);
    }

    /** الأوسع من قائمة نطاقات — والنطاق المجهول يُهمَل (لا يُقرأ إذنًا) */
    private function widest(array $scopes): ?string
    {
        $order = (array) config('access.scopes');
        $best = null;
        $bestRank = -1;

        foreach ($scopes as $scope) {
            $rank = array_search($scope, $order, true);

            if ($rank !== false && $rank > $bestRank) {
                $bestRank = $rank;
                $best = $scope;
            }
        }

        return $best;
    }
}
