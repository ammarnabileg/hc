<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * سؤال الاختبار التمهيديّ (2.5-د-2) — يُدار من الأدمن، وله وسيطه ومكافأته.
 *
 * ⚠️ ليس له صلة بـ`PlacementRequest` (تسكين المتطوّعين — 13.4).
 */
class PlacementTestQuestion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PlacementTestAnswer::class);
    }

    /** خيارات العرض — والإجابة الصحيحة **لا تخرج** مع هذه الدالّة أبدًا */
    public function publicOptions(): array
    {
        return array_values(array_filter(
            array_map('strval', (array) ($this->options ?? [])),
            fn (string $option) => trim($option) !== '',
        ));
    }
}
