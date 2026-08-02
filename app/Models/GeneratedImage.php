<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedImage extends Model
{
    use HasFactory;

    protected $table = 'generated_images';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_snapshot' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function image_template(): BelongsTo
    {
        return $this->belongsTo(ImageTemplate::class, 'image_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
