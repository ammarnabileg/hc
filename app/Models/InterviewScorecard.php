<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewScorecard extends Model
{
    use HasFactory;

    protected $table = 'interview_scorecards';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'criteria_scores' => 'array',
            'is_draft' => 'boolean',
            'total_score' => 'decimal:2',
        ];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class, 'interview_id');
    }
}
