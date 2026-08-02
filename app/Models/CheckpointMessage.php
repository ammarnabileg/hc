<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckpointMessage extends Model
{
    use HasFactory;

    protected $table = 'checkpoint_messages';

    protected $guarded = [];

    public function contribution_checkpoint(): BelongsTo
    {
        return $this->belongsTo(ContributionCheckpoint::class, 'contribution_checkpoint_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
