<?php

namespace App\Services\Security;

use App\Models\User;
use App\Support\Access\AccessEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * التصفّح كمستخدم — Impersonate (12.1).
 *
 * ثلاثة شروط لا تُكسَر:
 *  1) **شريط علويّ معرِّف** ظاهر في كلّ صفحة طول الجلسة (ImpersonationBanner).
 *  2) **Audit كامل** للبدء والعودة في `audit_logs` — مين دخل كمين وامتى.
 *  3) **زرّ عودة** يرجّع الأدمن لحسابه بضغطة.
 *
 * ⛔ ولا يُنتحَل مالك المنصّة ولا يُنتحَل المرء نفسه، ولا انتحال داخل انتحال.
 */
class Impersonator
{
    public const SESSION_KEY = 'security.impersonator_id';

    public function __construct(
        private readonly UserModeration $moderation,
        private readonly AccessEngine $access,
    ) {}

    public function start(Request $request, User $actor, User $target): void
    {
        if ($target->id === $actor->id || $target->isPlatformOwner()) {
            throw new RuntimeException('الحساب ده مايتنتحلش.');
        }

        if ($this->isImpersonating($request)) {
            throw new RuntimeException('إنت أصلًا بتتصفّح كمستخدم — ارجع لحسابك الأوّل.');
        }

        $request->session()->put(self::SESSION_KEY, $actor->id);

        $this->moderation->log($actor, $target, 'impersonation.start', [], [
            'target_code' => $target->code,
            'target_name' => $target->name,
        ]);

        Auth::login($target);
        $this->access->forget($target);
    }

    /** العودة لحساب الأدمن — والسجلّ يكتب مَن رجع ومن أيّ حساب */
    public function stop(Request $request): ?User
    {
        $actorId = $request->session()->pull(self::SESSION_KEY);

        if (! $actorId) {
            return null;
        }

        $actor = User::find($actorId);
        $target = $request->user();

        if (! $actor) {
            Auth::logout();

            return null;
        }

        if ($target instanceof User) {
            $this->moderation->log($actor, $target, 'impersonation.stop', [], [
                'target_code' => $target->code,
            ]);
        }

        Auth::login($actor);
        $this->access->forget($actor);

        return $actor;
    }

    public function isImpersonating(Request $request): bool
    {
        return $request->hasSession() && $request->session()->has(self::SESSION_KEY);
    }

    public function actor(Request $request): ?User
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return $id ? User::find($id) : null;
    }
}
