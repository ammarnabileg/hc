<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use HasFactory;

    protected $table = 'projects';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_permanent' => 'boolean',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }
}
