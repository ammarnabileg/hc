<?php

namespace App\Services\Growth;

/**
 * ⭐ UTM موحّد على **كلّ رابط تولّده المنصّة** (21.2-ح): دعوات · مشاركات · صور · مقالات.
 *
 * السبب المنصوص: «فلا يُصرَف على قناةٍ لا نعرف عائدها» — ولوحة مصادر الاكتساب
 * لا تعرف مصدرًا لم يُوسَم أصلًا. فالوسم يحدث **في مكانٍ واحد** لا في كلّ زرّ.
 */
class UtmBuilder
{
    /** المعايير من الإعدادات لا محروقة (21.2-ي) */
    public function defaults(): array
    {
        $configured = setting('growth.utm.defaults');

        return is_array($configured) ? $configured : [
            'utm_source' => 'platform',
            'utm_medium' => 'share',
        ];
    }

    /** المصادر المسموحة — قائمة مقفولة فلا يتسرّب مصدرٌ عشوائيّ للوحة */
    public function mediums(): array
    {
        $configured = setting('growth.utm.mediums');

        return is_array($configured) && $configured !== []
            ? array_map(fn ($v) => (string) $v, $configured)
            : ['invite', 'share', 'article', 'image', 'weekly_card', 'volunteer_kit', 'certificate', 'preview'];
    }

    public function enabled(): bool
    {
        return (bool) setting('growth.utm.enabled', true);
    }

    /**
     * وسم رابط بمعايير الحملة — ولا يُلمَس معيارٌ موجود في الرابط أصلًا،
     * حتّى لا نكسر رابطًا جاء موسومًا من حملة حقيقيّة.
     */
    public function tag(string $url, string $medium, ?string $campaign = null, ?string $content = null): string
    {
        if (! $this->enabled() || $url === '') {
            return $url;
        }

        $medium = in_array($medium, $this->mediums(), true)
            ? $medium
            : (string) (setting('growth.utm.default_medium', 'share'));

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        $params = array_filter([
            'utm_source' => (string) ($this->defaults()['utm_source'] ?? 'platform'),
            'utm_medium' => $medium,
            'utm_campaign' => $campaign ?: (string) setting('growth.utm.default_campaign', 'organic'),
            'utm_content' => $content,
        ], fn ($v) => $v !== null && $v !== '');

        foreach ($params as $key => $value) {
            // الموجود يفوز: رابطٌ جاء موسومًا من حملة لا نعيد وسمه
            $query[$key] = $query[$key] ?? $value;
        }

        $rebuilt = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');

        return $rebuilt.'?'.http_build_query($query).(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
