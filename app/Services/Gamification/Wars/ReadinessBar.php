<?php

namespace App\Services\Gamification\Wars;

use App\Models\WarMatch;
use App\Models\WarReadiness;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ⭐ شريط الاستعداد العائم (15.0).
 *
 * «طالما هو مستعدّ لأيّ حرب يظهر **شريط ثابت على كلّ الصفحات** فيه زرّ إلغاء
 * الاستعداد — عشان لو اتنقّل بين الحروب يقدر يلغي من أيّ مكان (وما يلحقش
 * يروح للحرب عشان يلغي)».
 *
 * لماذا حقنٌ في الردّ لا كتلة في الليَاوت؟ لأنّ الشرط «كلّ الصفحات» بلا
 * استثناء، وليَاوتٌ واحد منسيّ = مستخدم مستعدّ لا يجد زرّ الإلغاء ويُخصَم منه.
 */
class ReadinessBar
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (! $this->shouldInject($request, $response)) {
                return $response;
            }

            $content = (string) $response->getContent();
            $position = stripos($content, '<body');

            if ($position === false) {
                return $response;
            }

            $open = strpos($content, '>', $position);

            if ($open === false) {
                return $response;
            }

            $response->setContent(
                substr($content, 0, $open + 1)
                .$this->markup($request)
                .substr($content, $open + 1)
            );
        } catch (Throwable) {
            // الشريط تذكيرٌ لا سبب لسقوط الصفحة
            return $response;
        }

        return $response;
    }

    private function shouldInject(Request $request, Response $response): bool
    {
        if (! $request->user() || $request->expectsJson()) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        return Schema::hasTable('war_readiness')
            && WarReadiness::query()->where('user_id', $request->user()->id)->exists();
    }

    private function markup(Request $request): string
    {
        $readiness = WarReadiness::query()->with('challenge')->where('user_id', $request->user()->id)->first();
        $name = e((string) ($readiness?->challenge?->name_ar ?? 'حرب'));

        $inMatch = WarMatch::query()
            ->where('status', 'running')
            ->where(fn ($q) => $q->where('challenger_id', $request->user()->id)->orWhere('opponent_id', $request->user()->id))
            ->exists();

        $penalty = (int) abs((float) setting('wars.shared.withdraw', -10));
        $loss = (int) abs((float) setting('wars.shared.loss', -2));

        $text = $inMatch
            // إلغاء الاستعداد أثناء حرب نشطة ⟵ خسارة مؤكّدة + عقوبة (15.0)
            ? "إنت في مواجهة شغّالة — الإلغاء دلوقتي = خسارة {$loss} + عقوبة {$penalty} تذكرة."
            : "إنت مستعدّ لـ«{$name}» — أيّ محارب يقدر يتحدّاك.";

        $label = $inMatch ? 'انسحب وألغِ الاستعداد' : 'إلغاء الاستعداد';
        $url = route('challenges.unready');
        $csrf = e(csrf_token());

        return <<<HTML
        <div role="status" data-war-ready-bar style="position: sticky; inset-block-start: 0; z-index: 70; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .75rem; padding: .55rem 1rem; background: var(--color-brand-800, #04372f); color: var(--color-brand-100, #d8fff7); font-family: 'Cairo', sans-serif; font-size: .8125rem; font-weight: 600; border-block-end: 1px solid var(--color-brand-500, #00d4b8);">
            <span aria-hidden="true">⚔️</span>
            <span>{$text}</span>
            <form method="post" action="{$url}" style="margin: 0;">
                <input type="hidden" name="_token" value="{$csrf}">
                <button type="submit" style="min-block-size: 44px; min-inline-size: 44px; border-radius: .75rem; padding: .35rem 1rem; background: var(--color-brand-500, #00d4b8); color: #04201c; font-weight: 700;">{$label}</button>
            </form>
        </div>
        HTML;
    }
}
