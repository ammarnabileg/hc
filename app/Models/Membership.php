<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use HasFactory;

    protected $table = 'memberships';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ended_at' => 'datetime',
            'is_acting' => 'boolean',
            'is_primary' => 'boolean',
            'started_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function upline(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'upline_id');
    }
}
