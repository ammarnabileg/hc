<?php

namespace App\Services\Admin\Content;

use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ⭐ مصمّم القوالب المرئيّ (12.5-ب · 24.1) — أهمّ جزء في إدارة الشهادات.
 *
 * الأدمن يرفع **صورة خلفيّة الشهادة** ويضع **كلّ النصوص فوقها بحرّيّة تامّة**:
 * الموضع X/Y · الحجم · الخطّ · اللون · المحاذاة · الدوران، مع شبكة محاذاة (Snap)
 * ولوحة طبقات (إظهار/إخفاء · رفع/إنزال · قفل) — والنتيجة JSON في
 * `certificate_templates.layers`.
 *
 * ⭐ لماذا الإحداثيّات **كسورًا من 0 إلى 1** والحجم **بالبكسل على عرض 1754**؟
 * لأنّ `CertificateRenderer` (راسم الصورة النهائيّة على الخادم) يقرأ بهذه الوحدة
 * بالضبط — فأيّ وحدة أخرى تجعل المعاينة تخالف الشهادة الصادرة. المصمّم يعرضها
 * للأدمن بالنسبة المئويّة ويخزّنها بالكسر، فتتطابق المعاينة والمقاس الحقيقيّ.
 */
class TemplateDesigner
{
    /** أنواع الطبقات المسموحة — والراسم يعرف `text` و`qr` */
    public const LAYER_TYPES = ['text', 'qr'];

    /** عرض اللوحة المرجعيّ الذي تُقاس عليه أحجام الخطوط (يطابق الراسم) */
    public const REFERENCE_WIDTH = 1754;

    /** الحقول الجاهزة في كلّ شهادة — بأسماء مفاتيح `data_snapshot` نفسها */
    public function builtInFields(): array
    {
        return [
            'holder_name' => 'اسم المتدرّب',
            'certificate_name' => 'اسم الشهادة',
            'type_name' => 'نوع الشهادة',
            'accreditation_name' => 'جهة الاعتماد',
            'issued_on' => 'تاريخ الإصدار',
            'country' => 'الدولة',
            'code' => 'كود الشهادة',
            'holder_code' => 'كود صاحبها',
        ];
    }

    /**
     * الربط بأعمدة قاعدة البيانات **بلا حدود** (12.5-ب) — من قائمة بيضاء.
     *
     * لماذا قائمة بيضاء؟ لأنّ «الجدول ← العمود» واجهة حسّاسة: بلا حصر تصير بابًا
     * لطبع أعمدة خاصّة (بيانات تواصل/أسرار) على مستند عامّ يُنشَر برابط تحقّق.
     *
     * @return array<string, array{label: string, columns: array<string, string>}>
     */
    public function bindableTables(): array
    {
        $configured = (array) setting('certificates.bindings.tables', [
            'users' => ['label' => 'المستخدمون', 'columns' => ['name' => 'الاسم', 'code' => 'الكود']],
            'courses' => ['label' => 'التدريبات', 'columns' => ['name_ar' => 'اسم التدريب', 'cert_name_ar' => 'اسم الشهادة']],
            'learning_paths' => ['label' => 'المسارات', 'columns' => ['name_ar' => 'اسم المسار']],
            'events' => ['label' => 'الفعاليّات', 'columns' => ['title_ar' => 'اسم الفعاليّة']],
            'bundles' => ['label' => 'البندلز', 'columns' => ['name_ar' => 'اسم البندل']],
            'positions' => ['label' => 'البوزشنز', 'columns' => ['name_ar' => 'اسم البوزشن']],
        ]);

        $available = [];

        foreach ($configured as $table => $meta) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = [];

            foreach ((array) ($meta['columns'] ?? []) as $column => $label) {
                if (Schema::hasColumn($table, $column)) {
                    $columns[$column] = $label;
                }
            }

            if ($columns !== []) {
                $available[$table] = ['label' => $meta['label'] ?? $table, 'columns' => $columns];
            }
        }

        return $available;
    }

    public function bindingAllowed(string $table, string $column): bool
    {
        $tables = $this->bindableTables();

        return isset($tables[$table]['columns'][$column]);
    }

    /** مفتاح الحقل المربوط داخل لقطة البيانات. */
    public function bindingKey(string $table, string $column): string
    {
        return 'bind:'.$table.'.'.$column;
    }

    /**
     * القالبان (ع/إ) لنوعٍ ما — ويُنشَآن بالتصميم الافتراضيّ إن لم يوجدا،
     * فالشاشة لا تظهر فارغةً أبدًا: «يظهر التصميم الافتراضيّ الجاهز فورًا» (24.1).
     *
     * @return array<string, CertificateTemplate>
     */
    public function templatesFor(CertificateType $type): array
    {
        $templates = [];

        foreach (['ar', 'en'] as $language) {
            $template = CertificateTemplate::query()
                ->where('certificate_type_id', $type->id)
                ->where('language', $language)
                ->orderByDesc('is_default')
                ->orderByDesc('version')
                ->first();

            /*
             | ⭐ صفٌّ موجود بطبقاتٍ **فارغة** ليس «تصميمًا اختاره الأدمن» بل أثرُ
             | إنشاءٍ ناقص: الراسم يتخطّى كلّ طبقةٍ فارغة، فالنتيجة صندوقٌ خالٍ
             | يبدأ منه الأدمن من الصفر — وهذا نقض 12.5-ب: «تصميم افتراضيّ جاهز
             | لكلّ نوع شهادة … **والأدمن يعدّله**». فيُملأ بتصميم نوعه مرّةً.
             | ولا يمسّ ذلك شهادةً صدرت: نسختها مجمَّدة في `template_snapshot`.
             */
            if ($template && $this->isBlank($template)) {
                $template->update([
                    'layers' => $this->defaultLayers($language, $type),
                    'version' => (int) $template->version + 1,
                ]);

                $template->refresh();
            }

            $templates[$language] = $template ?? $this->createDefault($type, $language);
        }

        return $templates;
    }

    /** قالبٌ بلا طبقةٍ واحدة — لا خيارَ تصميمٍ بل فراغ (12.5-ب). */
    public function isBlank(CertificateTemplate $template): bool
    {
        $layers = $template->layers;
        $layers = is_string($layers) ? (json_decode($layers, true) ?: []) : (array) $layers;

        return $layers === [];
    }

    /** ⭐ تصميم افتراضيّ جاهز لكلّ نوع شهادة، ولكلّ لغة خلفيّتها ومواضعها (12.5-ب). */
    public function createDefault(CertificateType $type, string $language): CertificateTemplate
    {
        return CertificateTemplate::create([
            'certificate_type_id' => $type->id,
            'language' => $language,
            'name' => $type->name_ar.' — '.($language === 'ar' ? 'عربيّة' : 'إنجليزيّة'),
            'width_px' => (int) setting('certificates.render.default_width_px', self::REFERENCE_WIDTH),
            'height_px' => (int) setting('certificates.render.default_height_px', 1240),
            'layers' => $this->defaultLayers($language, $type),
            'is_default' => true,
            'version' => 1,
        ]);
    }

    /** إعادة القالب لتصميمه الافتراضيّ (24.1 — «افتراضيّ + Reset»). */
    public function reset(CertificateTemplate $template): CertificateTemplate
    {
        $type = CertificateType::query()->find($template->certificate_type_id);

        $template->update([
            'layers' => $this->defaultLayers($template->language, $type),
            'version' => (int) $template->version + 1,
        ]);

        return $template->refresh();
    }

    /**
     * تنظيف الطبقات القادمة من المصمّم قبل الحفظ — ولا يُقبَل ربطٌ خارج القائمة البيضاء.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeLayers(mixed $raw): array
    {
        $raw = is_string($raw) ? (json_decode($raw, true) ?: []) : (array) $raw;
        $max = (int) setting('certificates.designer.max_layers', 40);
        $layers = [];

        foreach (array_slice(array_values($raw), 0, $max) as $index => $layer) {
            if (! is_array($layer)) {
                continue;
            }

            $type = in_array($layer['type'] ?? 'text', self::LAYER_TYPES, true) ? (string) $layer['type'] : 'text';
            $binding = $this->sanitizeBinding($layer['binding'] ?? null);
            $field = $this->sanitizeField($layer['field'] ?? null, $binding);

            $layers[] = [
                'id' => (string) ($layer['id'] ?? Str::uuid()),
                'type' => $type,
                'label' => mb_substr(trim((string) ($layer['label'] ?? 'طبقة')) ?: 'طبقة', 0, 64),
                // «نصّ ثابت يكتبه الأدمن + العمود المختار» (12.5-ب)
                'text' => mb_substr((string) ($layer['text'] ?? ''), 0, 240),
                'field' => $field,
                'binding' => $binding,
                // كسور 0…1 كما يقرؤها الراسم
                'x' => round($this->clamp($layer['x'] ?? 0.5, 0, 1), 4),
                'y' => round($this->clamp($layer['y'] ?? 0.5, 0, 1), 4),
                // النصّ: بكسل على عرض 1754 · الـQR: كسر من العرض
                'size' => $type === 'qr'
                    ? round($this->clamp($layer['size'] ?? 0.12, 0.02, 0.5), 4)
                    : round($this->clamp($layer['size'] ?? setting('certificates.designer.default_font_size', 32), 8, 200), 1),
                'font' => mb_substr((string) ($layer['font'] ?? setting('certificates.designer.default_font', 'Cairo')), 0, 48),
                'color' => $this->sanitizeColor($layer['color'] ?? null),
                // 'start' = اليمين في RTL · 'end' = اليسار · الافتراضيّ توسيط (يطابق الراسم)
                'align' => in_array($layer['align'] ?? 'center', ['start', 'center', 'end'], true) ? (string) ($layer['align'] ?? 'center') : 'center',
                'rotate' => round($this->clamp($layer['rotate'] ?? 0, -180, 180), 2),
                'bold' => (bool) ($layer['bold'] ?? false),
                'max_chars' => (int) $this->clamp($layer['max_chars'] ?? setting('certificates.render.max_chars_per_line', 48), 8, 200),
                // لوحة الطبقات: إظهار/إخفاء · قفل · ترتيب (رفع للأعلى/إنزال للأسفل)
                'visible' => ! array_key_exists('visible', $layer) || (bool) $layer['visible'],
                'locked' => (bool) ($layer['locked'] ?? false),
                'z' => (int) ($layer['z'] ?? $index + 1),
                // حقل شرطيّ: الفاضي لا يظهر (12.5-ب)
                'conditional' => (bool) ($layer['conditional'] ?? false),
            ];
        }

        usort($layers, fn ($a, $b) => $a['z'] <=> $b['z']);

        // المخفيّ لا يُرسَم — والراسم لا يعرف «الإخفاء»، فنستبعده هنا عند الحفظ؟ لا:
        // نُبقيه في القالب كي يعود الأدمن إليه، ونُخفيه لحظة التجميد (freezeLayers).
        return array_values($layers);
    }

    /** الطبقات الظاهرة فقط — هذه هي التي تُجمَّد في الشهادة وتُرسَم (12.5-ج). */
    public function visibleLayers(array $layers): array
    {
        return array_values(array_filter($layers, fn ($layer) => ($layer['visible'] ?? true) === true));
    }

    /**
     * صيغة التخزين: الطبقة المخفيّة تُحفَظ بمحتواها **معلَّقًا** (`_text`/`_field`)
     * ومحتواها الظاهر فارغًا — لأنّ الراسم يتخطّى كلّ طبقة قيمتها فارغة،
     * فلا نحتاج تعديل الراسم ولا نفقد ما كتبه الأدمن حين يعيد إظهارها.
     *
     * @param  array<int, array<string, mixed>>  $layers
     * @return array<int, array<string, mixed>>
     */
    public function forStorage(array $layers): array
    {
        return array_map(function (array $layer) {
            if (($layer['visible'] ?? true) === true) {
                return $layer;
            }

            return array_merge($layer, [
                '_text' => $layer['text'] ?? '',
                '_field' => $layer['field'] ?? null,
                'text' => '',
                'field' => null,
            ]);
        }, $layers);
    }

    /**
     * عكس `forStorage` — لتحميل المصمّم بحالته الحقيقيّة.
     *
     * @param  array<int, mixed>  $layers
     * @return array<int, array<string, mixed>>
     */
    public function fromStorage(mixed $layers): array
    {
        $layers = is_string($layers) ? (json_decode($layers, true) ?: []) : (array) $layers;

        $restored = array_map(function ($layer) {
            if (! is_array($layer)) {
                return $layer;
            }

            if (array_key_exists('_text', $layer) || array_key_exists('_field', $layer)) {
                $layer['text'] = $layer['_text'] ?? '';
                $layer['field'] = $layer['_field'] ?? null;
                unset($layer['_text'], $layer['_field']);
            }

            return $layer;
        }, $layers);

        return $this->sanitizeLayers($restored);
    }

    /**
     * بيانات وهميّة للمعاينة الحيّة قبل الحفظ (12.5-ب).
     *
     * @return array<string, string>
     */
    public function sampleData(CertificateType $type, string $language = 'ar'): array
    {
        $data = [
            'holder_name' => (string) setting('certificates.preview.sample_name', 'محمّد أحمد عبد الله'),
            'certificate_name' => $language === 'ar' ? $type->name_ar : ($type->name_en ?: $type->name_ar),
            'type_name' => $type->name_ar,
            'accreditation_name' => (string) ($type->accreditation?->name_ar ?? setting('certificates.accreditation.default_name', 'اعتماد المنصّة')),
            'issued_on' => now()->format((string) setting('certificates.render.date_format', 'Y/m/d')),
            'country' => (string) setting('certificates.preview.sample_country', 'مصر'),
            'code' => ($type->numbering_prefix ?: 'HC').'-'.now()->year.'-'.str_pad('1', (int) setting('certificates.numbering.padding', 6), '0', STR_PAD_LEFT),
            'holder_code' => (string) setting('certificates.preview.sample_code', 'U-1234'),
        ];

        foreach ($this->bindableTables() as $table => $meta) {
            foreach ($meta['columns'] as $column => $label) {
                $data[$this->bindingKey($table, $column)] = (string) setting('certificates.preview.sample_binding', 'قيمة من قاعدة البيانات');
            }
        }

        return $data;
    }

    /**
     * ⭐ **تصميم افتراضيّ جاهز لكلّ نوع شهادة** — يعمل من أوّل يوم بلا رفع أيّ
     * خلفيّة، **والأدمن يعدّله** (12.5-ب حرفيًّا).
     *
     * ولماذا **لكلّ نوع** لا تصميمٌ واحد للثمانية؟ لأنّ النصّ يقول «لكلّ نوع»،
     * ولأنّ شهادة خبرة تطوّع لا تقول «قد أتمّ بنجاح» وشهادة تقدير لا تقول
     * «حضر». فالهندسة (المواضع والأحجام والألوان) مشتركة — وهي ما يسحبه الأدمن
     * ويعدّله — و**النصوص الثابتة** تأتي من وصفة النوع في الإعدادات، فيفتح
     * الراسمَ على تصميمٍ يخصّ نوعه لا على فراغ.
     *
     * والوصفة إعدادٌ لا نصٌّ محروق: `certificates.default_design.recipes`
     * (2.13) — يعدّلها المالك فيتغيّر التصميم الافتراضيّ لكلّ نوعٍ جديد.
     *
     * @return array<int, array<string, mixed>>
     */
    public function defaultLayers(string $language, ?CertificateType $type = null): array
    {
        $ar = $language === 'ar';
        $recipe = $this->defaultDesignRecipe($type, $language);
        $labels = $this->defaultDesignLabels($language);
        $honor = (string) setting('certificates.render.honor_color', '#d4af37');
        $brand = (string) setting('certificates.render.brand_color', '#00d4b8');
        $ink = (string) setting('certificates.render.text_color', '#e8f5f2');
        $muted = (string) setting('certificates.designer.muted_color', '#9bb3ad');

        $layers = [
            ['id' => 'heading', 'type' => 'text', 'label' => 'العنوان', 'text' => $recipe['heading'] ?: ($ar ? 'شهادة معتمدة' : 'Certificate'), 'x' => 0.5, 'y' => 0.22, 'size' => 62, 'color' => $honor, 'bold' => true, 'z' => 1],
            ['id' => 'lead', 'type' => 'text', 'label' => 'تمهيد', 'text' => $recipe['lead'] ?: ($ar ? 'تشهد المنصّة بأنّ' : 'This is to certify that'), 'x' => 0.5, 'y' => 0.32, 'size' => 30, 'color' => $muted, 'z' => 2],
            ['id' => 'holder', 'type' => 'text', 'label' => 'اسم المتدرّب', 'field' => 'holder_name', 'x' => 0.5, 'y' => 0.43, 'size' => 54, 'color' => $ink, 'bold' => true, 'z' => 3],
            ['id' => 'completion', 'type' => 'text', 'label' => 'نصّ الإتمام', 'text' => $recipe['body'] ?: ($ar ? 'قد أتمّ بنجاح' : 'has successfully completed'), 'x' => 0.5, 'y' => 0.51, 'size' => 28, 'color' => $muted, 'z' => 4],
            ['id' => 'subject', 'type' => 'text', 'label' => 'اسم الشهادة', 'field' => 'certificate_name', 'x' => 0.5, 'y' => 0.60, 'size' => 40, 'color' => $brand, 'z' => 5],
            ['id' => 'closing', 'type' => 'text', 'label' => $labels['closing'], 'text' => $recipe['closing'], 'x' => 0.5, 'y' => 0.67, 'size' => 24, 'color' => $muted, 'z' => 6],
            ['id' => 'accreditation', 'type' => 'text', 'label' => 'جهة الاعتماد', 'field' => 'accreditation_name', 'x' => 0.5, 'y' => 0.74, 'size' => 24, 'color' => $muted, 'z' => 7],
            // ⚠️ الختم في **وسط** الأسفل لا يمينه: عند 0.75 كان يصطدم بمربّع الـQR
            // (0.79…0.91 عرضًا) فيُقرَأ نصفُه — ولقطةُ الشاشة هي التي كشفته.
            ['id' => 'seal', 'type' => 'text', 'label' => $labels['seal'], 'text' => $recipe['seal'], 'x' => 0.5, 'y' => 0.90, 'size' => 22, 'color' => $honor, 'z' => 8],
            ['id' => 'issued', 'type' => 'text', 'label' => 'التاريخ', 'field' => 'issued_on', 'x' => 0.25, 'y' => 0.83, 'size' => 24, 'color' => $muted, 'z' => 9],
            ['id' => 'country', 'type' => 'text', 'label' => 'الدولة', 'field' => 'country', 'x' => 0.25, 'y' => 0.87, 'size' => 24, 'color' => $muted, 'conditional' => true, 'z' => 10],
            ['id' => 'code', 'type' => 'text', 'label' => 'كود الشهادة', 'field' => 'code', 'x' => 0.25, 'y' => 0.91, 'size' => 24, 'color' => $muted, 'z' => 11],
            ['id' => 'qr', 'type' => 'qr', 'label' => 'QR التحقّق', 'x' => 0.85, 'y' => 0.84, 'size' => 0.12, 'z' => 12],
        ];

        /*
         | طبقةٌ نصّيّة بلا نصٍّ ولا حقل لا تُضاف أصلًا: لو خلت وصفةُ النوع من
         | «الخاتمة» أو «الختم» فوجودُ طبقةٍ فارغة في لوحة الطبقات ضجيجٌ يربك
         | الأدمن — والراسم يتخطّاها على أيّ حال.
         */
        return $this->sanitizeLayers(array_values(array_filter(
            $layers,
            fn (array $layer) => ($layer['type'] ?? 'text') !== 'text'
                || trim((string) ($layer['text'] ?? '')) !== ''
                || ($layer['field'] ?? null) !== null,
        )));
    }

    /**
     * وصفة نصوص النوع (ع/إ) من الإعدادات — والارتداد إلى الوصفة العامّة
     * `default` حين لا يكون للنوع وصفةٌ خاصّة (نوعٌ أضافه الأدمن بيده).
     *
     * @return array{heading: string, lead: string, body: string, closing: string, seal: string}
     */
    public function defaultDesignRecipe(?CertificateType $type, string $language): array
    {
        $recipes = (array) setting('certificates.default_design.recipes', []);
        $key = $type?->key ?: '';

        $recipe = (array) ($recipes[$key][$language] ?? $recipes['default'][$language] ?? []);

        return [
            'heading' => trim((string) ($recipe['heading'] ?? '')),
            'lead' => trim((string) ($recipe['lead'] ?? '')),
            'body' => trim((string) ($recipe['body'] ?? '')),
            'closing' => trim((string) ($recipe['closing'] ?? '')),
            'seal' => trim((string) ($recipe['seal'] ?? '')),
        ];
    }

    /**
     * تسميات الطبقتين المضافتين في لوحة الطبقات — من الإعدادات لا محروقةً (2.13).
     *
     * @return array{closing: string, seal: string}
     */
    private function defaultDesignLabels(string $language): array
    {
        $labels = (array) setting('certificates.default_design.layer_labels', []);
        $set = (array) ($labels[$language] ?? $labels['ar'] ?? []);

        return [
            'closing' => (string) ($set['closing'] ?? setting('certificates.designer.layer_label_fallback', 'طبقة')),
            'seal' => (string) ($set['seal'] ?? setting('certificates.designer.layer_label_fallback', 'طبقة')),
        ];
    }

    /**
     * قيمة الطبقة كما ستظهر — نفس منطق الراسم كي تتطابق المعاينة مع الصادر.
     *
     * @param  array<string, mixed>  $layer
     * @param  array<string, mixed>  $data
     */
    public function renderLayer(array $layer, array $data): string
    {
        $static = (string) ($layer['text'] ?? '');
        $field = $layer['field'] ?? null;
        $dynamic = $field ? (string) ($data[$field] ?? '') : '';

        if ($field && $dynamic === '') {
            return '';
        }

        return trim($static.($static !== '' && $dynamic !== '' ? ' ' : '').$dynamic);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array{table: string, column: string}|null */
    private function sanitizeBinding(mixed $binding): ?array
    {
        if (! is_array($binding)) {
            return null;
        }

        $table = (string) ($binding['table'] ?? '');
        $column = (string) ($binding['column'] ?? '');

        return $this->bindingAllowed($table, $column) ? ['table' => $table, 'column' => $column] : null;
    }

    /** @param  array{table: string, column: string}|null  $binding */
    private function sanitizeField(mixed $field, ?array $binding): ?string
    {
        if ($binding) {
            return $this->bindingKey($binding['table'], $binding['column']);
        }

        $field = (string) $field;

        return array_key_exists($field, $this->builtInFields()) ? $field : null;
    }

    private function sanitizeColor(mixed $color): string
    {
        $color = trim((string) $color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color)
            ? $color
            : (string) setting('certificates.designer.default_color', '#e8f5f2');
    }

    private function clamp(mixed $value, float $min, float $max): float
    {
        return max($min, min($max, (float) $value));
    }
}
