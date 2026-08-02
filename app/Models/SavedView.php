<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** عرض محفوظ: تركيبة فلاتر تُحفَظ بضغطة وتظهر كرقاقة فوق الجدول (2.15-د) */
class SavedView extends Model
{
    use HasFactory;

    protected $table = 'saved_views';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** الرابط بالفلاتر المحفوظة — يُبنى من الشاشة نفسها فلا يتسرّب مسار غريب */
    public function url(): string
    {
        $query = array_filter((array) $this->filters, fn ($v) => $v !== null && $v !== '');

        return \Illuminate\Support\Facades\Route::has($this->screen)
            ? route($this->screen, $query)
            : url('/').'?'.http_build_query($query);
    }
}
