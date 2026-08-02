<?php

namespace App\Services\Setup\Middleware;

use App\Services\Setup\SetupState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * لا خطوة تُفتَح قبل إثبات ملكيّة الخادم بالتوكن (2.2 — أمان):
 * الرابط عامّ، والتوكن وحده هو ما يفصل المالك عن أوّل زائر يمرّ بالصدفة.
 */
class RequireSetupToken
{
    public function __construct(private readonly SetupState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->state->tokenVerified()) {
            return redirect()->route('setup.token');
        }

        return $next($request);
    }
}
