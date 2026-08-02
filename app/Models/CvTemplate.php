<?php

namespace App\Models;

use App\Services\Gamification\EconomyRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CvTemplate extends Model
{
    use HasFactory;

    protected $table = 'cv_templates';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_free' => 'boolean',
            'price_tickets' => 'decimal:2',
            'ats_options' => 'array',
        ];
    }

    /**
     * تباعد وأحجام هذا القالب في مخرَج الـATS (9) — فأثرُ القالب المدفوع
     * يظهر في الملفّ لا في المعاينة وحدها. والمفاتيح المسموحة فقط تمرّ.
     *
     * @return array<string, float>
     */
    public function atsOptions(): array
    {
        $allowed = ['margin_pt', 'body_size_pt', 'heading_size_pt', 'title_size_pt', 'leading'];
        $stored = (array) ($this->getAttribute('ats_options') ?? []);

        $clean = [];

        foreach ($allowed as $key) {
            if (isset($stored[$key]) && is_numeric($stored[$key])) {
                $clean[$key] = (float) $stored[$key];
            }
        }

        return $clean;
    }

    /**
     * السعر الفعليّ بالتذاكر (24.5 · 2.13): عمود القالب Override صريح،
     * وNULL يعني «اتبع جدول أوجه الصرف» (`xp_rules.spend` ⟵ `cv.export`).
     * فمصدر السعر واحدٌ لا اثنان، وتعديل الجدول يظهر أثره فورًا.
     */
    public function priceTickets(): float
    {
        return app(EconomyRules::class)->costFor(
            'cv.export',
            $this->getAttribute('price_tickets'),
            (float) setting('cv.template.default_price_tickets', 2),
        );
    }
}
