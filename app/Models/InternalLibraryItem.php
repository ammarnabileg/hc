<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalLibraryItem extends Model
{
    use HasFactory;

    protected $table = 'internal_library_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'tags' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function min_position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'min_position_id');
    }
}
