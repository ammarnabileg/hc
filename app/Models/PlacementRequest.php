<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacementRequest extends Model
{
    use HasFactory;

    protected $table = 'placement_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'respond_due_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function recruitment_candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'recruitment_candidate_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function requested_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
