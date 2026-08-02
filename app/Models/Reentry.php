<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reentry extends Model
{
    use HasFactory;

    protected $table = 'reentries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function offboarding(): BelongsTo
    {
        return $this->belongsTo(Offboarding::class, 'offboarding_id');
    }

    public function expired_certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'expired_certificate_id');
    }

    public function exam_attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function new_certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'new_certificate_id');
    }
}
