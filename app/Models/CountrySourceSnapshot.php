<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نسخة من مصدر الدول (12.7-د) — تُثبَّت أوّلًا ثمّ تُفحَص ثمّ يُدمَج منها،
 * فيكون قرار المالك مبنيًّا على فروقٍ ثابتة لا على مصدرٍ يتغيّر تحت يده (2.11).
 */
class CountrySourceSnapshot extends Model
{
    use HasFactory;

    protected $table = 'country_source_snapshots';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'summary' => 'array',
            'report' => 'array',
            'checked_at' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
