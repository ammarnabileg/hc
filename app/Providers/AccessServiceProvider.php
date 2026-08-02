<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Support\Access\ConditionEvaluator;
use App\Support\Access\MembershipContext;
use App\Support\Access\PermissionExpander;
use App\Support\Access\ScopeResolver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ScopeResolver::class);
        $this->app->singleton(MembershipContext::class);
        $this->app->singleton(ConditionEvaluator::class);
        $this->app->singleton(AccessEngine::class);
        $this->app->singleton(PermissionExpander::class);
    }

    public function boot(): void
    {
        /*
         | ربط بوّابة Laravel بمحرّكنا: أيّ اسم يحمل نقطة يُعامَل «مورد.فعل».
         | فتعمل @can و $user->can و authorize() على مصفوفتنا مباشرةً.
         */
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! str_contains($ability, '.')) {
                return null; // ليست صلاحيّة من مصفوفتنا — اتركها لبوّابات Laravel
            }

            return app(AccessEngine::class)->allows($user, $ability, $arguments[0] ?? null)
                ? true
                : false;
        });

        // @scope('ENTITY', 'tasks.edit') — لعرض عناصر تعتمد على أوسع نطاق
        Blade::if('scope', function (string $scope, string $permission) {
            $user = auth()->user();

            if (! $user) {
                return false;
            }

            $widest = $user->widestScope($permission);

            if (! $widest) {
                return false;
            }

            $order = config('access.scopes');

            return array_search($widest, $order, true) >= array_search($scope, $order, true);
        });

        // @owner — لعناصر مالك المنصّة وحده (المجموعة المحميّة)
        Blade::if('owner', fn () => auth()->check() && auth()->user()->isPlatformOwner());

        // @volunteer — طبقة التطوّع لا تظهر لغير المتطوّعين
        Blade::if('volunteer', fn () => auth()->check() && auth()->user()->isVolunteer());
    }
}
