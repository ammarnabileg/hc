<?php

namespace App\Support\Access;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * الشروط من **قائمة مقفولة** تقرأ حالاتٍ موجودة (12.2.1-ج).
 * لا محرّك قواعد موازٍ ولا شروط حرّة — و**أيّ شرط غير معروف يُرفَض (Fail closed)**.
 *
 * كان هذا الصنف يعيد `true` لكلّ مفتاح خارج القائمة بحجّة أنّه «توثيقٌ للقارئ»،
 * فكان يكفي حقنُ شرطٍ مخترَع في `permission_role.conditions` ليُفتَح الباب — وهو
 * عكسُ ما يقوله تعليق الصنف نفسه. القاعدة الآن: **المعروف يُقيَّم، والمجهول يُرفَض.**
 *
 * وقاعدةٌ ثانيةٌ متّسقة مع `ScopeResolver`: الشرط المقيس على **هدف** لا يُفحَص
 * حين لا هدفَ أصلًا (فحص «هل يستطيع مبدئيًّا؟» الذي تستعمله حرّاس المسارات).
 */
class ConditionEvaluator
{
    /** شروط تُقاس على الهدف — بلا هدفٍ تُتخطّى كما يتخطّى `ScopeResolver` نطاقَه */
    private const TARGET_BOUND = [
        'is_owner', 'not_self', 'assigned', 'reviewer',
        'direct_upline', 'upline', 'within_window', 'before_deadline',
        'feature_enabled', 'not_locked',
    ];

    /** أعمدة الحالة التي نقرؤها على الهدف */
    private const STATE_COLUMNS = ['status', 'state', 'stage', 'phase', 'review_status'];

    public function __construct(private readonly ScopeResolver $scopes) {}

    /** كلّ الشروط يجب أن تتحقّق معًا */
    public function passes(array $conditions, User $user, mixed $target, ?Membership $context): bool
    {
        foreach ($conditions as $condition) {
            if (! $this->check((string) $condition, $user, $target, $context)) {
                return false;
            }
        }

        return true;
    }

    private function check(string $condition, User $user, mixed $target, ?Membership $context): bool
    {
        $condition = trim($condition);

        if ($condition === '') {
            return false; // صفٌّ تالف لا يُقرأ إذنًا
        }

        if (str_starts_with($condition, ConditionMap::STATE_PREFIX)) {
            return $this->checkState(substr($condition, strlen(ConditionMap::STATE_PREFIX)), $target);
        }

        // ⭐ Fail closed: خارج القائمة المقفولة = رفض
        if (! array_key_exists($condition, config('access.conditions', []))) {
            return false;
        }

        if ($target === null && in_array($condition, self::TARGET_BOUND, true)) {
            return true;
        }

        return match ($condition) {
            'platform_owner' => $user->isPlatformOwner(),
            'is_owner' => $this->attributeMatches($target, ['user_id', 'owner_id', 'contributor_id', 'author_id'], $user->id),
            'not_self' => ! $this->isSelf($target, $user),
            'assigned' => $this->attributeMatches($target, ['assigned_to', 'assigned_user_id', 'contributor_id'], $user->id),
            'reviewer' => $this->attributeMatches($target, ['reviewer_id'], $user->id),
            'direct_upline' => $context !== null && $this->scopes->covers('TEAM', $user, $target, $context),
            'upline' => $context !== null && $this->scopes->covers('SUBTREE', $user, $target, $context),
            'active_membership' => $context !== null && $context->status === 'active',
            'within_window' => $this->beforeAny($target, ['window_due_at', 'respond_due_at', 'owner_review_due_at', 'objection_deadline_at']),
            'before_deadline' => $this->beforeAny($target, ['deadline_at', 'due_at', 'ends_at', 'closes_at', 'deadline']),
            'feature_enabled' => $this->flagOn($target, ['is_enabled', 'is_active', 'enabled', 'active']),
            'not_locked' => ! ($target instanceof Model && (bool) ($target->settings_locked ?? false)),
            default => false,
        };
    }

    /**
     * شرط الحالة `state:<slug>` — والقائمة مقفولة كذلك.
     *
     * ما وُسِم `guard => 'domain'` حالةٌ **لا يقرأها عمود حالة** (رصيد · عدّاد ·
     * عتبة مقيّمين · مستوى وصول)، فطبقة الوصول لا تملك ما تقيسه عليه ويحرسها
     * المجال صاحب الشاشة — وهي معلَنة في `config/access.php` صراحةً كي لا يُخترَع
     * شرطٌ حرّ ولا يمرّ نصٌّ بلا مفتاح.
     */
    private function checkState(string $slug, mixed $target): bool
    {
        $catalog = config('access.condition_states', []);

        // ⭐ Fail closed: حالة خارج القائمة المقفولة = رفض
        if (! array_key_exists($slug, $catalog)) {
            return false;
        }

        if (! $target instanceof Model) {
            return true; // بلا هدف: فحص المبدأ يكفي
        }

        $rule = $catalog[$slug];
        $value = $this->stateOf($target);

        if ($value === null || (! isset($rule['values']) && ! isset($rule['except']))) {
            return true;
        }

        if (isset($rule['except'])) {
            return ! in_array($value, $rule['except'], true);
        }

        return in_array($value, $rule['values'], true);
    }

    private function stateOf(Model $target): ?string
    {
        foreach (self::STATE_COLUMNS as $column) {
            if (array_key_exists($column, $target->getAttributes()) && $target->{$column} !== null) {
                return (string) $target->{$column};
            }
        }

        return null;
    }

    private function isSelf(mixed $target, User $user): bool
    {
        if ($target instanceof User) {
            return $target->id === $user->id;
        }

        return $this->attributeMatches($target, ['user_id', 'owner_id', 'contributor_id', 'author_id'], $user->id);
    }

    private function attributeMatches(mixed $target, array $keys, int $value): bool
    {
        if (! $target instanceof Model) {
            return false;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $target->getAttributes()) && (int) $target->{$key} === $value) {
                return true;
            }
        }

        return false;
    }

    /** أوّل عمود موعدٍ موجود يحكم: هل نحن قبله؟ */
    private function beforeAny(mixed $target, array $keys): bool
    {
        if (! $target instanceof Model) {
            return true;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $target->getAttributes()) && $target->{$key}) {
                return now()->lessThanOrEqualTo($target->{$key});
            }
        }

        return true;
    }

    private function flagOn(mixed $target, array $keys): bool
    {
        if (! $target instanceof Model) {
            return true;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $target->getAttributes())) {
                return (bool) $target->{$key};
            }
        }

        return true;
    }
}
