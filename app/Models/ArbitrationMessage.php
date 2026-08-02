<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArbitrationMessage extends Model
{
    use HasFactory;

    protected $table = 'arbitration_messages';

    protected $guarded = [];

    public function arbitration(): BelongsTo
    {
        return $this->belongsTo(Arbitration::class, 'arbitration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
