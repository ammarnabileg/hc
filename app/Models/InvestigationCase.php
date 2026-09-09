<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestigationCase extends Model
{
    protected $table = 'investigation_cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'meeting_scheduled_at' => 'datetime',
            'verdict_at' => 'datetime',
            'decision_at' => 'datetime',
            'closed_at' => 'datetime',
            'absent_in_person' => 'boolean',
            'dossier_snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seatUpline(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seat_upline_id');
    }

    public function seatDept(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seat_dept_id');
    }

    public function offboarding(): BelongsTo
    {
        return $this->belongsTo(Offboarding::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** أحد مقعدَي اللجنة — لا يُخلط بمشرف عام التطوّع صاحب القرار النهائيّ (23-0.2-4) */
    public function hasSeat(User $user): bool
    {
        return (int) $this->seat_upline_id === (int) $user->id
            || (int) $this->seat_dept_id === (int) $user->id;
    }
}
