<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use HasFactory;

    protected $table = 'meetings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attendance_closes_at' => 'datetime',
            'ended_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(MeetingQuestion::class, 'meeting_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class, 'meeting_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(MeetingPost::class, 'meeting_id');
    }
}
