<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterviewCriterion extends Model
{
    use HasFactory;

    protected $table = 'interview_criteria';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }
}
