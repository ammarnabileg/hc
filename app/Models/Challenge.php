<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Challenge extends Model
{
    use HasFactory;

    protected $table = 'challenges';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_cost' => 'decimal:2',
            'is_active' => 'boolean',
            'limits' => 'array',
            'question_source' => 'array',
            'rewards' => 'array',
            'settings_locked' => 'boolean',
        ];
    }

    public function entry_currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'entry_currency_id');
    }
}
