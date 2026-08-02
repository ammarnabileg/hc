<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Support\Access\MembershipContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * مبدّل سياق العضويّة في الهيدر (قسم/محافظة/ملف).
 * كلّ شيء يُقرأ ويُقيَّم داخل العضويّة النشطة — فلا سلطة عابرة للكيانات.
 */
class SetMembershipContext
{
    public function __construct(private readonly MembershipContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // تبديل صريح من الواجهة
        if ($request->filled('membership')) {
            $membership = Membership::query()
                ->where('id', $request->integer('membership'))
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($membership) {
                $this->context->set($membership);
            }
        }

        view()->share('activeMembership', $this->context->for($user));
        view()->share('userMemberships', $this->context->allFor($user));

        return $next($request);
    }
}
