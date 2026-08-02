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
        ];
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
}
