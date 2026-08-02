<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadershipEvaluation extends Model
{
    use HasFactory;

    protected $table = 'leadership_evaluations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'average' => 'decimal:2',
            'criteria_scores' => 'array',
            'week_start' => 'date',
        ];
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function evaluatee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluatee_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }
}
