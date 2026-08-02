<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaItem extends Model
{
    use HasFactory;

    protected $table = 'media_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function uploaded_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
