<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Exam extends Model
{
    use HasFactory;

    protected $table = 'exams';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_coins' => 'decimal:2',
            'requires_retake_on_version_change' => 'boolean',
        ];
    }

    public function examable(): MorphTo
    {
        return $this->morphTo();
    }
}
