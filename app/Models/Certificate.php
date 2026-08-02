<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Certificate extends Model
{
    use HasFactory;

    protected $table = 'certificates';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_snapshot' => 'array',
            'expired_at' => 'datetime',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'template_snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function certificate_type(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class, 'certificate_type_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function issued_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
