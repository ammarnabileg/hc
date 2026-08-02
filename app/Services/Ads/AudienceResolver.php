<?php

namespace App\Services\Ads;

use App\Models\AdAudience;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ إعادة الاستهداف: **الشرائح تُبنى من قاعدة بياناتنا لا من البكسل وحده** (21.3-ب)
 *    — «لأنّنا نعرف ما لا تعرفه المنصّات الإعلانيّة».
 *
 * وكلّ شرط هنا **منفَّذ فعلًا** لا مُعلَنًا: القائمة مقفولة، ومَن رفض التتبّع
 * يخرج من كلّ شريحة (21.3-د)، ولا يُبنى شرطٌ على حقلٍ خصوصيّته مقيّدة.
 */
class AudienceResolver
{
    /**
     * الشروط المعتمَدة — قائمة مقفولة لا شروط حرّة (21.3-ب).
     *
     * @return array<string,string>
     */
    public static function rules(): array
    {
        return [
            'viewed_course_not_registered' => 'فتح صفحة تدريب ولم يسجّل خلال 7 أيّام',
            'registered_no_first_lesson' => 'سجّل ولم يبدأ أوّل درس',
            'checkout_not_completed' => 'فتح صفحة الشراء ولم يُتِمّه',
            'completed_no_next_purchase' => 'أتمّ تدريبًا ولم يشترِ التالي',
            'best_users' => 'أفضل المستخدمين (أكمل واشترى وعاد)',
        ];
    }

    public static function supports(string $rule): bool
    {
        return array_key_exists($rule, self::rules());
    }

    /**
     * جمهور الشريحة.
     *
     * @return Collection<int,User>
     */
    public function resolve(AdAudience $audience): Collection
    {
        $rule = (string) ($audience->rule['key'] ?? '');

        if (! self::supports($rule)) {
            return collect();
        }

        $query = $this->base();

        // مدّة صلاحيّة الشريحة: مَن خرج عن نافذتها لا يُرفَع (21.3-ب)
        $ttlDays = max((int) $audience->ttl_days, 1);

        return $this->applyRule($query, $rule, $ttlDays)
            ->limit((int) setting('ads.audience.max_rows', 50000))
            ->get(['users.id', 'users.email', 'users.phone', 'users.code']);
    }

    /**
     * الأساس المشترك: نشِطون **ومَن لم يرفض التتبّع**.
     * ⭐ الرفض يُخرِج المستخدم من الشرائح فعليًّا — لا شكليًّا (21.3-د).
     */
    private function base(): Builder
    {
        return User::query()
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->where(function ($q) {
                $q->where('users.tracking_consent', Consent::ACCEPTED)
                    ->orWhere(function ($inner) {
                        // «تخصيص» يدخل فقط إن سمح بغرض الإعلان صراحةً
                        $inner->where('users.tracking_consent', Consent::CUSTOM)
                            ->where('users.tracking_scopes', 'like', '%"ads"%');
                    });
            });
    }

    private function applyRule(Builder $query, string $rule, int $ttlDays): Builder
    {
        $window = now()->subDays($ttlDays);

        return match ($rule) {
            // فتح صفحة تدريب ولم يسجّل في أيّ تدريب خلال المهلة المعتمَدة
            'viewed_course_not_registered' => $query
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('tracking_events')
                    ->whereColumn('tracking_events.user_id', 'users.id')
                    ->where('tracking_events.event', 'course_page_view')
                    ->where('tracking_events.created_at', '>=', $window)
                    ->where('tracking_events.created_at', '<=', now()->subDays($this->viewToRegisterDays())))
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('enrollments')
                    ->whereColumn('enrollments.user_id', 'users.id')),

            // سجّل في تدريب ولم يُتِمّ أوّل درس فيه
            'registered_no_first_lesson' => $query
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('enrollments')
                    ->whereColumn('enrollments.user_id', 'users.id')
                    ->where('enrollments.created_at', '>=', $window))
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('lesson_completions')
                    ->whereColumn('lesson_completions.user_id', 'users.id')),

            // فتح صفحة الشراء ولم يُتِمّه: حدث فتحٍ بلا طلبٍ مدفوع بعده
            'checkout_not_completed' => $query
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('tracking_events')
                    ->whereColumn('tracking_events.user_id', 'users.id')
                    ->where('tracking_events.event', 'checkout_opened')
                    ->where('tracking_events.created_at', '>=', $window))
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->where('orders.status', 'paid')
                    ->where('orders.created_at', '>=', $window)),

            // أتمّ تدريبًا ولم يشترِ بعده شيئًا
            'completed_no_next_purchase' => $query
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('enrollments')
                    ->whereColumn('enrollments.user_id', 'users.id')
                    ->where('enrollments.status', 'completed')
                    ->where('enrollments.updated_at', '>=', $window))
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->where('orders.status', 'paid')
                    ->whereRaw('orders.created_at >= (select max(e2.updated_at) from enrollments e2 where e2.user_id = users.id and e2.status = ?)', ['completed'])),

            // ⭐ الجمهور المشابه: المكمّلون والمشترون لا كلّ المسجّلين (21.3-ج قاعدة الجودة)
            default => $this->bestUsers($query),
        };
    }

    /**
     * ⭐ تعريف «أفضل مستخدم» **إعدادٌ قابل للتعديل** (21.3-ج):
     * أكمل تدريبًا · اشترى · عاد أكثر من مرّة — وكلّ شرطٍ يُفعَّل على حدة.
     */
    private function bestUsers(Builder $query): Builder
    {
        $rule = setting('ads.best_user.rule', []);
        $rule = is_array($rule) ? $rule : [];

        if ((bool) ($rule['completed_course'] ?? true)) {
            $query->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('enrollments')
                ->whereColumn('enrollments.user_id', 'users.id')
                ->where('enrollments.status', 'completed'));
        }

        if ((bool) ($rule['purchased'] ?? true)) {
            $query->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('orders')
                ->whereColumn('orders.user_id', 'users.id')
                ->where('orders.status', 'paid'));
        }

        if ((bool) ($rule['returned'] ?? true)) {
            /*
             * «عاد أكثر من مرّة»: مضى على تفعيله مدّة العودة المعتمَدة **وعاد بعدها**.
             * والمقارنة بين عمودين وتاريخٍ محسوب في PHP — فتعمل على SQLite وMySQL معًا
             * بلا دوالّ خاصّة بمحرّك بعينه.
             */
            $days = (int) setting('ads.best_user.return_after_days', 7);

            $query->whereNotNull('users.last_seen_at')
                ->whereNotNull('users.activated_at')
                ->where('users.activated_at', '<=', now()->subDays($days))
                ->whereColumn('users.last_seen_at', '>', 'users.activated_at');
        }

        return $query->orderByDesc('users.xp');
    }

    private function viewToRegisterDays(): int
    {
        return (int) setting('ads.audience.view_to_register_days', 7);
    }
}
