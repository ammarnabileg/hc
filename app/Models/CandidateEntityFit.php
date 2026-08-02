<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateEntityFit extends Model
{
    use HasFactory;

    protected $table = 'candidate_entity_fits';

    protected $guarded = [];

    public function recruitment_candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'recruitment_candidate_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }
}
