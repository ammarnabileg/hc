<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سجلّ الكسب: مرّة واحدة لكلّ (تسجيل، متطوّع) — منع الدبل-فارمينج (13.4-ل). */
class VolunteerRecordingClaim extends Model
{
    use HasFactory;

    protected $table = 'volunteer_recording_claims';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'rep_awarded' => 'decimal:2',
            'vxp_awarded' => 'decimal:2',
        ];
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(VolunteerRecording::class, 'volunteer_recording_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
