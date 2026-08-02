<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerCard extends Model
{
    use HasFactory;

    protected $table = 'volunteer_cards';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_snapshot' => 'array',
            'expired_at' => 'datetime',
            'issued_at' => 'datetime',
            'show_rep' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function image_template(): BelongsTo
    {
        return $this->belongsTo(ImageTemplate::class, 'image_template_id');
    }
}
