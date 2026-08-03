<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    protected $table = 'announcements';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'expires_at' => 'datetime',
            'is_pinned' => 'boolean',
            'push_to_notifications' => 'boolean',
            // القنوات الموحّدة من مكان واحد: تاب · Toast/إشعار · بريد (12.6-أ)
            'show_in_feed' => 'boolean',
            'email_enabled' => 'boolean',
            'reactions_enabled' => 'boolean',
            'requires_acknowledge' => 'boolean',
            'scheduled_at' => 'datetime',
            // استطلاع داخل المنشور (12.6-أ)
            'poll_options' => 'array',
            'poll_results_public' => 'boolean',
            'poll_closes_at' => 'datetime',
            // الجدولة المتكرّرة وسلسلة الـOnboarding (12.6-أ)
            'recurrence_until' => 'datetime',
            'recurrence_last_at' => 'datetime',
            'onboarding_step' => 'integer',
            'onboarding_delay_days' => 'integer',
        ];
    }

    /** أصوات الاستطلاع (12.6-أ). */
    public function pollVotes(): HasMany
    {
        return $this->hasMany(AnnouncementPollVote::class);
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
