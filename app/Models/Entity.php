<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'entities';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class, 'track_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'parent_id');
    }
}
