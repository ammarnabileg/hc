<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** بنك أسئلة الحروب (12.10-ب) — الإجابة لا تغادر الخادم أبدًا (15.2-3). */
class WarQuestion extends Model
{
    protected $table = 'war_questions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_numeric' => 'boolean',
            'tolerance' => 'decimal:4',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
