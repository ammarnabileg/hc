<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipAbsence extends Model
{
    use HasFactory;

    protected $table = 'membership_absences';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'thawed_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** أُنهي مبكّرًا؟ — الغياب حينها لا يسري ولو كان `to_date` في المستقبل (23-6) */
    public function endedEarly(): bool
    {
        return $this->ended_at !== null;
    }

    /**
     * المدّة **الفعليّة** بالأيّام: حتى لحظة الإنهاء المبكّر إن وقع، وإلّا
     * حتى `to_date`. فالمعروض في الشاشة هو ما جرى لا ما أُعلِن أوّلًا.
     */
    public function effectiveDays(): int
    {
        $from = $this->from_date?->copy()->startOfDay();

        if (! $from) {
            return 0;
        }

        $to = $this->ended_at?->copy() ?? $this->to_date?->copy()->endOfDay();

        return $to && $to->greaterThan($from) ? (int) ceil($from->diffInHours($to) / 24) : 0;
    }

    /** حالة الصفّ للعرض: سارية · قادمة · منتهية (نفس تقسيم شاشة الإدارة) */
    public function state(): string
    {
        if ($this->ended_at !== null || ($this->to_date && $this->to_date->lt(today()))) {
            return 'ended';
        }

        return $this->from_date && $this->from_date->gt(today()) ? 'upcoming' : 'current';
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function delegate_membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'delegate_membership_id');
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ended_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
