<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** تسجيلات الأكاديمية (13.4-ل) — والـOTP وحده هو ما يجعل التسجيل مانحًا للنقاط. */
class VolunteerRecording extends Model
{
    use HasFactory;

    protected $table = 'volunteer_recordings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'broken_reported_at' => 'datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(VolunteerRecordingClaim::class, 'volunteer_recording_id');
    }

    public function grantsPoints(): bool
    {
        return filled($this->otp);
    }
}
