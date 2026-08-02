<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryAccessRequest extends Model
{
    use HasFactory;

    protected $table = 'library_access_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function internal_library_item(): BelongsTo
    {
        return $this->belongsTo(InternalLibraryItem::class, 'internal_library_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function decided_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
