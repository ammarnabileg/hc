<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObjectionMessage extends Model
{
    use HasFactory;

    protected $table = 'objection_messages';

    protected $guarded = [];

    public function objection(): BelongsTo
    {
        return $this->belongsTo(Objection::class, 'objection_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
