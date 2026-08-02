<?php

namespace App\Services\Ui;

use App\Models\User;
use App\Models\UserFirstRun;

/**
 * «شاشة أوّل مرّة» — **على أهمّ الشاشات فقط** منعًا للزحام (2.15-د).
 *
 * والأدمن يختار الشاشات المفعَّلة وماذا يظهر بالضبط، مع **قوالب جاهزة لكلّ
 * صفحة قابلة للتعديل** — وكلّه في إعدادَي `ux.first_time.*` (2.13)، فلا
 * نصّ محروق هنا ولا قائمة شاشات في الكود.
 */
class FirstRunScreens
{
    /** الشاشات المفعَّلة كما ضبطها الأدمن */
    public function enabled(): array
    {
        $screens = setting('ux.first_time.enabled_screens', []);

        return is_array($screens) ? array_values(array_filter(array_map('strval', $screens))) : [];
    }

    public function isEnabled(string $screen): bool
    {
        return $screen !== '' && in_array($screen, $this->enabled(), true);
    }

    /**
     * مراحل الشاشة: من إعداد المحتوى، وإلّا فالقالب الجاهز.
     *
     * @return array<int, array{title:string, body:string}>
     */
    public function stepsFor(string $screen): array
    {
        $all = setting('ux.first_time.content', []);
        $steps = is_array($all) ? ($all[$screen] ?? null) : null;

        if (! is_array($steps) || $steps === []) {
            $steps = setting('ux.first_time.default_template', []);
        }

        $clean = [];

        foreach ((array) $steps as $step) {
            if (! is_array($step)) {
                continue;
            }

            $clean[] = [
                'title' => (string) ($step['title'] ?? ''),
                'body' => (string) ($step['body'] ?? ''),
            ];
        }

        return $clean;
    }

    /** هل تُعرَض الآن لهذا المستخدم؟ — مرّة واحدة، إلّا أن يطلبها بزرّ «؟» */
    public function shouldShow(?User $user, string $screen): bool
    {
        if (! $user || ! $this->isEnabled($screen) || $this->stepsFor($screen) === []) {
            return false;
        }

        return ! UserFirstRun::query()
            ->where('user_id', $user->id)
            ->where('screen', $screen)
            ->whereNotNull('seen_at')
            ->exists();
    }
}
