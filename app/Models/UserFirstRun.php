<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سجلّ «شاشة أوّل مرّة» (2.15-د) — من رآها ومتى، وزرّ «؟» يعيدها وقت ما شاء */
class UserFirstRun extends Model
{
    use HasFactory;

    protected $table = 'user_first_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
