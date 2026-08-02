<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $table = 'announcements';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'expires_at' => 'datetime',
            'is_pinned' => 'boolean',
            'push_to_notifications' => 'boolean',
            'reactions_enabled' => 'boolean',
            'requires_acknowledge' => 'boolean',
            'scheduled_at' => 'datetime',
        ];
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
