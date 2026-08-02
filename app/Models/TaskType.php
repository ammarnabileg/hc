<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskType extends Model
{
    use HasFactory;

    protected $table = 'task_types';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            // تشيك ليست القالب: بنود تتعبّى تلقائيًّا عند اختيار النوع (23-0.3)
            'checklist' => 'array',
            'default_vxp' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
