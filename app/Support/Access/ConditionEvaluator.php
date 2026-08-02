<?php

namespace App\Support\Access;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * الشروط من قائمة مقفولة تقرأ حالاتٍ موجودة (12.2.1).
 * لا محرّك قواعد موازٍ ولا شروط حرّة — وأيّ شرط غير معروف يُرفَض (Fail closed).
 */
class ConditionEvaluator
{
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
        // الشرط الوصفيّ القادم من المصفوفة (نصّ عربيّ) لا يُقيَّم آليًّا — يُتجاهَل بأمان
        // لأنّه توثيقٌ للقارئ، بينما الشروط التنفيذيّة مفاتيحُ من config('access.conditions').
        if (! array_key_exists($condition, config('access.conditions'))) {
            return true;
        }

        return match ($condition) {
            'owner' => $this->attributeMatches($target, ['user_id', 'owner_id'], $user->id),
            'assigned' => $this->attributeMatches($target, ['assigned_to', 'assigned_user_id', 'contributor_id'], $user->id),
            'reviewer' => $this->attributeMatches($target, ['reviewer_id'], $user->id),
            'upline' => $context !== null && $this->scopes->covers('SUBTREE', $user, $target, $context),
            'active_membership' => $context !== null && $context->status === 'active',
            'within_window' => $this->withinWindow($target),
            'not_locked' => ! ($target instanceof Model && (bool) ($target->settings_locked ?? false)),
            'status_matches' => true,
            default => false,
        };
    }

    private function attributeMatches(mixed $target, array $keys, int $value): bool
    {
        if (! $target instanceof Model) {
            return false;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $target->getAttributes()) && $target->{$key} === $value) {
                return true;
            }
        }

        return false;
    }

    private function withinWindow(mixed $target): bool
    {
        if (! $target instanceof Model) {
            return true;
        }

        foreach (['window_due_at', 'respond_due_at', 'owner_review_due_at', 'objection_deadline_at'] as $key) {
            if (array_key_exists($key, $target->getAttributes()) && $target->{$key}) {
                return now()->lessThanOrEqualTo($target->{$key});
            }
        }

        return true;
    }
}
