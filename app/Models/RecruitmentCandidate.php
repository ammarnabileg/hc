<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentCandidate extends Model
{
    use HasFactory;

    protected $table = 'recruitment_candidates';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'course_scores' => 'array',
            'is_returning' => 'boolean',
            'previous_service_from' => 'datetime',
            'previous_service_to' => 'datetime',
            'qualifying_score' => 'decimal:2',
            'readiness_renewed_at' => 'datetime',
            'renewed_readiness' => 'boolean',
            'stage_changed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
