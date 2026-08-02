<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $table = 'certificate_templates';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'layers' => 'array',
        ];
    }

    public function certificate_type(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class, 'certificate_type_id');
    }
}
