<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppNotification extends Model
{
    use HasFactory;

    protected $table = 'app_notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'read_at' => 'datetime',
            'requires_action' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }
}
