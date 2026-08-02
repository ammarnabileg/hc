<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentRequest extends Model
{
    use HasFactory;

    protected $table = 'consent_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'consent_expires_at' => 'datetime',
            'cooldown_until' => 'datetime',
            'granted_at' => 'datetime',
            'request_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
