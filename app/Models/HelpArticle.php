<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    use HasFactory;

    protected $table = 'help_articles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }
}
