<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionDecision extends Model
{
    use HasFactory;

    protected $table = 'promotion_decisions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'candidate_user_ids' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function vacatedMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'vacated_membership_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function decisionUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_user_id');
    }
}
