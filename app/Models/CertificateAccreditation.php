<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateAccreditation extends Model
{
    use HasFactory;

    protected $table = 'certificate_accreditations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_platform' => 'boolean',
        ];
    }
}
