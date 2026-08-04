<?php

namespace App\Services\Images;

/**
 * طبقات القالب: الترتيب والرفع/الإنزال والقفل وإعادة التوزيع النسبيّ عند تغيير المقاس (12.14-أ).
 *
 * الطبقة مصفوفة بسيطة عمدًا حتى تُخزَّن كما هي في `image_templates.layers`
 * وتُحرَّر من المتصفّح بلا طبقة تحويل ثالثة تختلف عن الخادم.
 */
class TemplateLayers
{
    /** المقاسات الجاهزة — كلّها إعداد قابل للتعديل (12.14-ح) */
    public function presets(): array
    {
        $configured = setting('images.presets');

        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return [
            'square' => ['label' => setting('images.template_layers.presets_1', 'بوست مربّع'), 'width' => 1080, 'height' => 1080],
            'story' => ['label' => setting('images.template_layers.presets_2', 'ستوري'), 'width' => 1080, 'height' => 1920],
            'cover' => ['label' => setting('images.template_layers.presets_3', 'كوفر'), 'width' => 1640, 'height' => 856],
            'whatsapp' => ['label' => setting('images.template_layers.presets_4', 'واتساب'), 'width' => 1080, 'height' => 1350],
        ];
    }

    /**
     * ⭐ تغيير المقاس يعيد ترتيب الطبقات **نسبيًّا** فلا يفسد التصميم:
     * كلّ إحداثيّ وحجم يُضرَب في نسبة التغيير على محوره.
     *
     * @param  array<int, array<string,mixed>>  $layers
     * @return array<int, array<string,mixed>>
     */
    public function rescale(array $layers, int $fromW, int $fromH, int $toW, int $toH): array
    {
        if ($fromW <= 0 || $fromH <= 0) {
            return $layers;
        }

        $rx = $toW / $fromW;
        $ry = $toH / $fromH;
        $rf = min($rx, $ry); // الخطّ والقُطر يتبعان الأصغر حتى لا يتشوّه

        foreach ($layers as $i => $layer) {
            $layers[$i]['x'] = (int) round((float) ($layer['x'] ?? 0) * $rx);
            $layers[$i]['y'] = (int) round((float) ($layer['y'] ?? 0) * $ry);

            if (isset($layer['w'])) {
                $layers[$i]['w'] = (int) round((float) $layer['w'] * $rx);
            }

            if (isset($layer['h'])) {
                $layers[$i]['h'] = (int) round((float) $layer['h'] * $ry);
            }

            if (isset($layer['size'])) {
                $layers[$i]['size'] = max(8, (int) round((float) $layer['size'] * $rf));
            }
        }

        return $layers;
    }

    /** رفع طبقة للأعلى أو إنزالها — والمقفولة لا تتحرّك */
    public function move(array $layers, int $index, string $direction): array
    {
        $target = $direction === 'up' ? $index + 1 : $index - 1;

        if (! isset($layers[$index], $layers[$target])) {
            return $layers;
        }

        if (! empty($layers[$index]['locked']) || ! empty($layers[$target]['locked'])) {
            return $layers;
        }

        [$layers[$index], $layers[$target]] = [$layers[$target], $layers[$index]];

        return $layers;
    }

    /**
     * تنقية الطبقات القادمة من الفورم: أنواع معروفة فقط وقيم داخل حدودها.
     *
     * @return array<int, array<string,mixed>>
     */
    public function sanitize(array $raw): array
    {
        $clean = [];

        foreach ($raw as $layer) {
            $type = $layer['type'] ?? 'text';

            if (! in_array($type, ['text', 'avatar', 'image'], true)) {
                continue;
            }

            $common = [
                'type' => $type,
                'name' => (string) ($layer['name'] ?? $type),
                'x' => (int) ($layer['x'] ?? 0),
                'y' => (int) ($layer['y'] ?? 0),
                'rotate' => (int) ($layer['rotate'] ?? 0),
                'visible' => (bool) ($layer['visible'] ?? true),
                'locked' => (bool) ($layer['locked'] ?? false),
            ];

            if ($type === 'text') {
                $clean[] = $common + [
                    'field' => (string) ($layer['field'] ?? ''),
                    'text' => (string) ($layer['text'] ?? ''),
                    'size' => max(8, (int) ($layer['size'] ?? 32)),
                    'color' => $this->color($layer['color'] ?? '#ffffff'),
                    'align' => $this->oneOf($layer['align'] ?? null, ['right', 'center', 'left'], 'right'),
                    'max_chars' => max(0, (int) ($layer['max_chars'] ?? setting('images.text.default_max_chars', 28))),
                    // سلوك التجاوز: تصغير تلقائيّ أو قصّ بثلاث نقاط (12.14-ج)
                    'overflow' => $this->oneOf($layer['overflow'] ?? null, ['shrink', 'truncate'], 'shrink'),
                ];

                continue;
            }

            $clean[] = $common + [
                'w' => max(8, (int) ($layer['w'] ?? 240)),
                'h' => max(8, (int) ($layer['h'] ?? 240)),
                // ⭐ بلا هالة حول الأفاتار (2.10.1)
                'shape' => $this->oneOf($layer['shape'] ?? null, ['square', 'circle', 'circle_border'], 'circle'),
                'fit' => $this->oneOf($layer['fit'] ?? null, ['cover', 'contain'], 'cover'),
                'border_color' => $this->color($layer['border_color'] ?? '#00d4b8'),
                'border_width' => max(0, (int) ($layer['border_width'] ?? 0)),
                'path' => (string) ($layer['path'] ?? ''),
            ];
        }

        return $clean;
    }

    /** قيمة من قائمة مقفولة، وإلّا فالافتراضيّ — فلا يمرّ خيارٌ غير معروف للرسّام */
    private function oneOf(mixed $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? (string) $value : $fallback;
    }

    private function color(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#ffffff';
    }
}
