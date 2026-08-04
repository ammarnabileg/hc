<?php

namespace Tests\Support;

use Illuminate\Support\Str;

/**
 * ⭐ **قراءة بنية السايد بار من الوجهة لا من اللافتة** (سجلّ القرارات 2026-08-04).
 *
 * القرار المسجَّل: «**أسماء بنود السايد بار قابلةٌ للتعديل**» سندًا لـ2.13-ب —
 * فكلّ نصٍّ في الدستور **قيمةٌ افتراضيّة** يملك المالك تغييرها من لوحته، وأسماءُ
 * القوائم ليست من «الثابت النظاميّ». **والمحفوظ من خريطتَي 12.0 و13.4-ح هو
 * بنيتُها**: عدد البنود · ترتيبها · **وجهتُها** · ومَن يراها.
 *
 * ولذلك يقرأ هذا الصنف **الـ`href`** — العنوان الذي يذهب إليه البند — لا الكلمة
 * المكتوبة عليه. فمَن غيّر لافتةً مرّ، ومَن حذف بندًا أو أزاحه أو حوّل وجهته سقط.
 *
 * والقراءة من **داخل `<aside data-sidebar>` وحده**: رابطٌ في متن الصفحة ليس بندًا
 * في الخريطة، ولو حسبناه لصار الحارس يمرّ ببندٍ محذوفٍ من السايد بار لمجرّد أنّ
 * الصفحة تذكر عنوانه في مكانٍ آخر.
 */
final class SidebarMap
{
    /** كتلة السايد بار وحدها — وما خارجها ليس خريطة */
    public static function aside(string $html): string
    {
        $aside = Str::between($html, '<aside data-sidebar', '</aside>');

        return $aside === $html ? '' : $aside;
    }

    /**
     * وجهات كلّ البنود بترتيب ظهورها — بندٌ واحد لكلّ `<a href>` في السايد بار.
     *
     * @return list<string>
     */
    public static function destinations(string $html): array
    {
        return array_map(
            fn (array $node) => $node['href'],
            array_filter(self::outline($html), fn (array $node) => $node['type'] === 'link'),
        );
    }

    /**
     * **كلّ** وجهةٍ في السايد بار — المفردة وبنودَ المجموعات معًا، بترتيبها.
     *
     * @return list<string>
     */
    public static function allDestinations(string $html): array
    {
        return self::hrefs(self::aside($html));
    }

    /**
     * **الخريطة كما ترتّبها الشاشة**: كلّ عقدةٍ إمّا رابطٌ مفرد وإمّا مجموعةُ
     * دروب-داون بوجهات بنودها بالترتيب.
     *
     * والمجموعة `<details>` والرابط `<a>` — والترتيب هو ترتيب المستند نفسه، فلا
     * يمرّ بندٌ أُزيح من مجموعةٍ إلى أخرى ولا مجموعةٌ قُدِّمت على أختها.
     *
     * @return list<array{type:string,href?:string,items?:list<string>}>
     */
    public static function outline(string $html): array
    {
        $aside = self::aside($html);

        if ($aside === '') {
            return [];
        }

        // حدود المجموعات أوّلًا، فالرابط داخل `<details>` بندٌ فيها لا عقدةً مستقلّة
        $groups = [];

        if (preg_match_all('/<details\b.*?<\/details>/s', $aside, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$block, $offset]) {
                $groups[] = [
                    'start' => $offset,
                    'end' => $offset + strlen($block),
                    'items' => self::hrefs($block),
                ];
            }
        }

        $nodes = [];

        foreach ($groups as $group) {
            $nodes[$group['start']] = ['type' => 'group', 'items' => $group['items']];
        }

        if (preg_match_all('/<a\b[^>]*\bhref="([^"]*)"/i', $aside, $links, PREG_OFFSET_CAPTURE)) {
            foreach ($links[1] as $index => [$href, $hrefOffset]) {
                $at = $links[0][$index][1];

                foreach ($groups as $group) {
                    if ($at > $group['start'] && $at < $group['end']) {
                        continue 2;
                    }
                }

                $nodes[$at] = ['type' => 'link', 'href' => self::decode($href)];
            }
        }

        ksort($nodes);

        return array_values($nodes);
    }

    /**
     * وجهات المجموعات وحدها بترتيبها — لمقارنة خريطةٍ منصوصةٍ صفًّا صفًّا.
     *
     * @return list<list<string>>
     */
    public static function groups(string $html): array
    {
        return array_values(array_map(
            fn (array $node) => $node['items'],
            array_filter(self::outline($html), fn (array $node) => $node['type'] === 'group'),
        ));
    }

    /** @return list<string> */
    private static function hrefs(string $block): array
    {
        preg_match_all('/<a\b[^>]*\bhref="([^"]*)"/i', $block, $matches);

        return array_map(self::decode(...), $matches[1]);
    }

    /** `route()` تُخرِج `&` ويكتبها بليد `&amp;` — والمقارنة على العنوان لا على ترميزه */
    private static function decode(string $href): string
    {
        return html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
