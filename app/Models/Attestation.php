<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attestation extends Model
{
    use HasFactory;

    protected $table = 'attestations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function from_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
}
