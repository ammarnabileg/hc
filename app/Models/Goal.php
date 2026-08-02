<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use HasFactory;

    protected $table = 'goals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'end_date' => 'date',
            'progress_percent' => 'decimal:2',
            'sent_to_execution_at' => 'datetime',
            'target_from' => 'decimal:2',
            'target_to' => 'decimal:2',
        ];
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
