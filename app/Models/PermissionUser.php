<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionUser extends Model
{
    use HasFactory;

    protected $table = 'permission_user';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
        ];
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function assigned_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
