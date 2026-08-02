<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateType extends Model
{
    use HasFactory;

    protected $table = 'certificate_types';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'auto_issue' => 'boolean',
            'is_active' => 'boolean',
            'lang_ar_enabled' => 'boolean',
            'lang_en_enabled' => 'boolean',
            'signature_enabled' => 'boolean',
        ];
    }

    public function accreditation(): BelongsTo
    {
        return $this->belongsTo(CertificateAccreditation::class, 'accreditation_id');
    }
}
