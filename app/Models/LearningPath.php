<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningPath extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'learning_paths';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'forced_order' => 'boolean',
            'is_indexable' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
