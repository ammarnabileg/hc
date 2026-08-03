<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجلّ فحص مصدر الدول (12.7-د): محاولةُ جلبٍ واحدة — نجحت أو فشلت — بسببها
 * مكتوبًا بالعربيّة.
 *
 * ⛔ الصفّ الفاشل **لا يمسّ اللقطة الأخيرة الناجحة**: هو سطرٌ هنا لا حالةٌ على
 * اللقطة، فتبقى فروق النسخة التي يراجعها المالك كما هي حتّى يقرّر فيها.
 */
class CountrySourceCheck extends Model
{
    protected $table = 'country_source_checks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'attempts' => 'integer',
            'added' => 'integer',
            'removed' => 'integer',
            'changed' => 'integer',
        ];
    }

    public function succeeded(): bool
    {
        return $this->status === 'ok';
    }

    /** كم فرقًا وجده هذا الفحص — صفرٌ يعني «المصدر مطابق لبياناتنا». */
    public function differences(): int
    {
        return $this->added + $this->removed + $this->changed;
    }

    /** حالة الشارة (2.16): لا يحمل اللون المعنى وحده — النصّ بجواره دائمًا. */
    public function badgeState(): string
    {
        return $this->succeeded() ? 'ok' : 'danger';
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(CountrySourceSnapshot::class, 'snapshot_id');
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
