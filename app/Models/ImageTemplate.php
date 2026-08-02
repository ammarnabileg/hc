<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageTemplate extends Model
{
    use HasFactory;

    protected $table = 'image_templates';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'folders' => 'array',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
            'layers' => 'array',
            'tags' => 'array',
        ];
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
