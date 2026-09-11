<?php

namespace App\Services\Geo;

use App\Models\Governorate;

/**
 * 🏷️ **اسمان عربيّان متطابقان في نفس الدولة** — فكّهما **عند العرض وحده**.
 *
 * المصدر المعتمَد (`dr5hn`) يعطي لبعض المحافظات المختلفة **ترجمةً عربيّةً
 * واحدة** داخل الدولة نفسها. وهويّة المحافظة عندنا هي **اسمها الإنجليزيّ** لا
 * العربيّ (هجرة 2026-08-28)، فالصفّان يدخلان القاعدة صحيحَين ومتمايزَين —
 * لكنّ قائمة الاختيار تطبع `name_ar` وحده، فيرى المُسجِّل **سطرين متطابقين
 * حرفيًّا** ولا يعرف أيّهما محافظته. وهذه سبع حالاتٍ مقيسة على نسخة المصدر
 * المثبَّتة (`database/data/countries.json`):
 *
 *  · إستونيا — «توري» ⟵ `Tori` / `Türi`
 *  · فرنسا — «لوار» ⟵ `Loire` / `Loiret`
 *  · ليتوانيا — «كلايبيدا» ⟵ `Klaipėda` / `Klaipėdos miestas`
 *  · ليتوانيا — «بانيفيزيس» ⟵ `Panevėžio miestas` / `Panevėžys`
 *  · جزر المالديف — «فافو» ⟵ `Faafu` / `Vaavu`
 *  · إسبانيا — «جزر البليار» ⟵ `Balearic Islands` / `Islas Baleares`
 *  · إسبانيا — «نافارا» ⟵ `Navarra` / `Navarre`
 *
 * ⛔ **ولا يُصلَح هذا في البيانات.** `database/data/countries.json` ملفٌّ
 *    **مولَّد** من المصدر بـ`php artisan countries:check-source --force --dump=…`،
 *    وتعديله بيدٍ يضيع مع أوّل توليد؛ والكتابة فوق `name_ar` في القاعدة تدهس
 *    ما قد يكون المالك عدّله بيده من تاب «بيانات الدول» (12.7-د). فالفكّ
 *    **طبقةُ عرضٍ خالصة**: لا صفَّ يتغيّر ولا بيانَ يُكرَّر.
 *
 * ⭐ **ولا يُلمَس الاسم الفريد.** المحافظة التي لا يشاركها أحدٌ اسمَها في دولتها
 *    تخرج **كما كانت حرفًا بحرف** — فالفكّ يقع على المتصادمين وحدهم، ولا تمتلئ
 *    قائمة مصر بأقواسٍ إنجليزيّة لأنّ إسبانيا عندها تصادم.
 *
 * والمميِّز هو **الاسم الإنجليزيّ** لأنّه هويّة الصفّ أصلًا — لا رقمٌ مصطنَع ولا
 * ترتيبٌ في القائمة. والرقم يبقى **آخر الحلول** لصفٍّ بلا اسمٍ إنجليزيّ أو
 * باسمٍ يساوي العربيّ، فلا سطران متطابقان يخرجان من هنا أبدًا.
 */
class GovernorateLabels
{
    /**
     * جدول التسميات لقائمة **دولةٍ واحدة**: `id` ⟵ الاسم المعروض.
     *
     * @param  iterable<Governorate>  $governorates
     * @return array<int, string>
     */
    public function labels(iterable $governorates): array
    {
        $rows = [];

        foreach ($governorates as $governorate) {
            $rows[] = [
                'id' => (int) $governorate->id,
                'base' => $this->baseName($governorate),
                'tag' => trim((string) ($governorate->name_en ?? '')),
            ];
        }

        // كم مرّةً يتكرّر كلّ اسمٍ معروض؟ الواحد يمرّ كما هو، وما زاد يُفَكّ
        $seen = [];

        foreach ($rows as $row) {
            $seen[$row['base']] = ($seen[$row['base']] ?? 0) + 1;
        }

        $labels = [];
        $taken = [];
        $order = [];

        foreach ($rows as $row) {
            $base = $row['base'];

            if (($seen[$base] ?? 0) < 2) {
                $labels[$row['id']] = $base;
                $taken[$base] = true;

                continue;
            }

            $order[$base] = ($order[$base] ?? 0) + 1;
            $tag = $row['tag'];

            // اسمٌ إنجليزيّ يساوي العربيّ لا يميّز شيئًا — فالترتيب داخل المجموعة
            if ($tag === '' || mb_strtolower($tag) === mb_strtolower($base)) {
                $tag = (string) $order[$base];
            }

            $label = $this->compose($base, $tag);
            $bump = 1;

            // حتّى لو تطابق المميِّزان نفساهما — لا سطران متطابقان يخرجان من هنا
            while (isset($taken[$label])) {
                $label = $this->compose($base, $tag.' '.(++$bump));
            }

            $labels[$row['id']] = $label;
            $taken[$label] = true;
        }

        return $labels;
    }

    /**
     * نفس الفكّ بشكل خيارات الـSelect التي تستهلكها صفحة التسجيل (2.5-ج).
     *
     * @param  iterable<Governorate>  $governorates
     * @return array<int, array{id: int, name: string}>
     */
    public function options(iterable $governorates): array
    {
        $out = [];

        foreach ($this->labels($governorates) as $id => $label) {
            $out[] = ['id' => $id, 'name' => $label];
        }

        return $out;
    }

    /**
     * الاسم المعروض قبل الفكّ: العربيّ، و**الإنجليزيّ حين لا عربيَّ** — فصفٌّ
     * بلا ترجمة يخرج باسمه لا فارغًا.
     */
    private function baseName(Governorate $governorate): string
    {
        $arabic = trim((string) ($governorate->name_ar ?? ''));

        return $arabic !== '' ? $arabic : trim((string) ($governorate->name_en ?? ''));
    }

    /** قالب السطر المفكوك **إعدادٌ** لا شكلٌ محروق (2.13). */
    private function compose(string $base, string $tag): string
    {
        return strtr(
            (string) setting('countries.governorate.duplicate_format', ':name (:tag)'),
            [':name' => $base, ':tag' => $tag],
        );
    }
}
