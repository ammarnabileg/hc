<?php

namespace App\Services\Library;

use App\Services\Certificates\GdEngine;

/**
 * ⭐ المحرّر المرئيّ (Drag-drop) لقوالب الـCV — المرحلة 1/2 (12.7-ب).
 *
 * محتوى قوالب الـCV (`classic.blade.php` · `modern.blade.php`) **متدفّقٌ
 * متغيّر الطول** (حلقات خبرات/تعليم/مهارات) — لا حقولًا ثابتة كالشهادات، فمحرّك
 * 12.5-ب (X/Y مطلق لكلّ عنصرٍ نصّيّ) غير مناسبٍ معماريًّا هنا: لا يوجد ما
 * يُطابَق مسبقًا مع عدد صفوف الخبرة مثلًا.
 *
 * **الحلّ الهجين المُقرَّر:** طبقةٌ زخرفيّةٌ إضافيّة فقط (خلفيّة/إطار صورة/شكل/
 * نصٍّ ثابتٍ زخرفيّ) بإحداثيّات X/Y مطلقة تُوضَع **فوق أو خلف** المحتوى
 * المتدفّق الذي يبقى HTML عاديًّا بلا أيّ تغيير. الطبقة الزخرفيّة **لا** ترتبط
 * بحقول بيانات الـCV — عناصر ثابتة بصريّة بحتة يختارها الأدمن عند تصميم
 * القالب، تمامًا كخلفيّة قالب شهادة، لا كحقلٍ ديناميكيّ.
 *
 * ⭐ لماذا الإحداثيّات **نسبٌ مئويّة (0-100)** لا بكسل ولا مليمتر؟
 * مرجعا التصميم الحيّان:
 *  - `TemplateDesigner` (شهادات 12.5-ب) يخزّن كسورًا 0..1 لأنّ الراسم (GD)
 *    يرسم على عرضٍ مرجعيّ ثابت بالبكسل — والكسر يتحوّل بلا فقدٍ لأيّ مقاس صادر.
 *  - `TemplateLayers` (استوديو 12.14) يخزّن بكسلًا مطلقًا لأنّ لوحته مقاساتٌ
 *    جاهزة ثابتة بالبكسل (1080×1080 … إلخ) لا تتغيّر بين العرض والحفظ.
 * وحالتنا مختلفة عن الاثنين: المرساة `.sheet` مقاسٌ **ثابتٌ بالمليمتر**
 * (210mm × 297mm) في preview.blade.php، لكنّها تُعرَض على الشاشة **مصغَّرة**
 * بـ`transform: scale()` (سطر «مقاس الورقة الحقيقيّ» هناك) لتناسب الموبايل —
 * فأيّ إحداثيّ بوحدة px أو mm مطلقة يبقى صحيحًا بصريًّا لأنّ CSS `%` يُحسَب من
 * أبعاد الصندوق الحاوي نفسه بعد أيّ تحويل، بعكس px الذي يحتاج تحويلًا يدويًّا
 * مطابقًا لعامل `--sheet-scale`. فالنسبة المئويّة الخيار الوحيد الذي يتطابق
 * بين الشاشة المصغَّرة والطباعة الحقيقيّة **بلا أيّ حساب إضافي في الـJS أو
 * الخادم** — و`.sheet` ثابتة المقاس دائمًا (A4) فلا خطر «تغيّر المرجع» الذي
 * يبرّر الكسور بدل الأعداد في حالة الشهادات متغيّرة المقاس.
 *
 * ⭐ نقاء وقت الحفظ لا وقت القراءة: `sanitize()` تُستدعى مرّةً في
 * `CvTemplateAdminController::updateDecor()` قبل التخزين، والقراءة (عرض
 * المعاينة/التحميل) تأخذ `decor_layers` **كما هي مخزَّنة** بلا تنقيةٍ ثانية —
 * نفس مبدأ `TemplateDesigner::sanitizeLayers()` الذي يُستدعى عند الحفظ فقط،
 * لا عند كلّ رسم. فما يدخل القاعدة نظيفٌ دائمًا، ولا داعي لتكرار العمل على كلّ
 * طلب معاينة.
 */
class CvTemplateDecor
{
    /** الأنواع المسموحة فقط — وأيّ نوعٍ آخر يُسقِط الطبقة كاملةً صامتًا */
    public const LAYER_TYPES = ['image', 'text'];

    /**
     * تنقية الطبقات القادمة من فورم الأدمن (المرحلة 2 تبني واجهته).
     *
     * طبقةٌ فاسدة (نوعٌ غير معروف، أو — لطبقة نصٍّ — لونٌ ليس Hex صحيحًا)
     * تُستبعَد **صامتًا** فلا يكسر عطبُ طبقةٍ واحدة حفظَ البقيّة، بنفس مبدأ
     * `TemplateLayers::sanitize()` (تخطٍّ بـ`continue` لا استثناء يوقف الحلقة).
     *
     * @param  array<int, array<string, mixed>>  $layers
     * @return array<int, array<string, mixed>>
     */
    public function sanitize(array $layers): array
    {
        $max = (int) setting('cv.template.decor.max_layers', 20);
        $clean = [];

        foreach (array_slice(array_values($layers), 0, $max) as $layer) {
            if (! is_array($layer)) {
                continue;
            }

            $type = $layer['type'] ?? null;

            if (! in_array($type, self::LAYER_TYPES, true)) {
                continue;
            }

            $common = [
                'type' => $type,
                // النسبة المئويّة من مقاس `.sheet` (210mm×297mm) — راجع تعليق الصنف
                'x' => $this->percent($layer['x'] ?? 0),
                'y' => $this->percent($layer['y'] ?? 0),
                // ⚠️ حصر الصفحة الأولى (297mm) CSS بحتٌ — راجع partials/decor-layer؛
                // z تُستعمَل كـCSS z-index حرفيًّا فتُقرَّر «فوق/خلف» المحتوى المتدفّق
                // بترتيب الرسم القياسيّ (سالبٌ = خلف، صفر/موجبٌ = فوق) بلا حاجة لإعادة
                // ترتيب DOM.
                'z' => max(-100, min(100, (int) ($layer['z'] ?? 0))),
            ];

            if ($type === 'text') {
                $color = $this->hexOrNull($layer['color'] ?? null);

                // لا لونَ افتراضيًّا آمنًا لعنصر زخرفيّ حرّ كما في التصميم الافتراضيّ
                // للشهادات — فطبقةٌ بلون فاسد تُستبعَد كاملةً لا تُستبدَل بلونٍ تخمينيّ.
                if ($color === null) {
                    continue;
                }

                $clean[] = $common + [
                    // نصّ ثابتٌ زخرفيّ يكتبه الأدمن حرفيًّا — لا ربط حقل بيانات (12.7-ب)
                    'text' => mb_substr((string) ($layer['text'] ?? ''), 0, 200),
                    // نقطة (pt) كباقي أحجام القالب (`.sheet h1 { font-size: 22pt }`)
                    'size' => max(6, min(96, (int) ($layer['size'] ?? 14))),
                    'color' => $color,
                    'align' => $this->oneOf($layer['align'] ?? null, ['right', 'center', 'left'], 'right'),
                    'rotate' => max(-180, min(180, (int) ($layer['rotate'] ?? 0))),
                ];

                continue;
            }

            // type === 'image': المسار يأتي من منتقي المكتبة الموجود — لا رفع جديد هنا
            $path = trim((string) ($layer['path'] ?? ''));

            if ($path === '') {
                continue;
            }

            $clean[] = $common + [
                'path' => mb_substr($path, 0, 255),
                'w' => max(1, min(100, (int) ($layer['w'] ?? 100))),
                'h' => max(1, min(100, (int) ($layer['h'] ?? 100))),
                'opacity' => max(0, min(100, (int) ($layer['opacity'] ?? 100))),
            ];
        }

        return $clean;
    }

    private function percent(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    private function oneOf(mixed $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? (string) $value : $fallback;
    }

    private function hexOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return GdEngine::isValidHex($value) ? $value : null;
    }
}
