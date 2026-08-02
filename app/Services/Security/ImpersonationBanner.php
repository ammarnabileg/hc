<?php

namespace App\Services\Security;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ⭐ الشريط العلويّ المعرِّف أثناء التصفّح كمستخدم (12.1).
 *
 * لماذا حقنٌ في الردّ لا كتلة في كلّ ليَاوت؟ لأنّ الشريط **شرط سلامة** لازم يظهر
 * في كلّ صفحة بلا استثناء — والاعتماد على تذكُّر كلّ ليَاوت بإضافته يعني أنّ
 * ليَاوتًا واحدًا منسيًّا = أدمن بيتصرّف باسم مستخدم وهو ناسي.
 */
class ImpersonationBanner
{
    public function __construct(private readonly Impersonator $impersonator) {}

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
            // الشريط زينة أمان لا سبب لسقوط الصفحة
            return $response;
        }

        return $response;
    }

    private function shouldInject(Request $request, Response $response): bool
    {
        if (! $this->impersonator->isImpersonating($request)) {
            return false;
        }

        if ($request->expectsJson()) {
            return false;
        }

        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function markup(Request $request): string
    {
        $actor = $this->impersonator->actor($request);
        $target = $request->user();

        $text = str_replace(
            ['{actor}', '{target}', '{code}'],
            [
                e($actor?->shortName() ?? '—'),
                e($target?->shortName() ?? '—'),
                e((string) ($target->code ?? '')),
            ],
            e((string) setting('impersonation.banner_text', 'إنت بتتصفّح كـ{target} (#{code}) — أيّ فعل هنا محسوب على {actor}.')),
        );

        $label = e((string) setting('impersonation.stop_label', 'ارجع لحسابي'));
        $url = route('admin.impersonate.stop');
        $csrf = e(csrf_token());

        return <<<HTML
        <div role="status" style="position: sticky; inset-block-start: 0; z-index: 80; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .75rem; padding: .6rem 1rem; background: var(--color-state-warn, #eab308); color: #241d00; font-family: 'Cairo', sans-serif; font-size: .8125rem; font-weight: 700;">
            <span>{$text}</span>
            <form method="post" action="{$url}" style="margin: 0;">
                <input type="hidden" name="_token" value="{$csrf}">
                <button type="submit" style="min-block-size: 44px; border-radius: .75rem; padding: .35rem 1rem; background: #241d00; color: #fff; font-weight: 700;">{$label}</button>
            </form>
        </div>
        HTML;
    }
}
