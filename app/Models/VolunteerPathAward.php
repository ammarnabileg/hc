<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حارس «مرّة واحدة» لمكافآت المسارات التطوّعيّة:
 * 1000 XP للمسار التأهيليّ (13.4-ب) · Rep إكمال المسار الأكاديميّ (13.4-ل).
 *
 * القيد الفريد في الجدول هو الحارس الأخير — لا شرط `if` في الكود.
 */
class VolunteerPathAward extends Model
{
    use HasFactory;

    /** مكافأة إتمام المسار التأهيليّ — 1000 XP (13.4-ب) */
    public const QUALIFYING_XP = 'qualifying_xp';

    /** مكافأة إكمال المسار الأكاديميّ — Rep من جدول Rep (13.4-ل · 13.4-ن) */
    public const ACADEMY_REP = 'academy_rep';

    protected $table = 'volunteer_path_awards';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'awarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'learning_path_id');
    }
}
