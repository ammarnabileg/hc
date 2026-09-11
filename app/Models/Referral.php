<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    use HasFactory;

    protected $table = 'referrals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'commission_earned' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'welcome_ticket_granted' => 'boolean',
            'referrer_ticket_granted' => 'boolean',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    /**
     * سطور العمولة المسجَّلة على شحنات المدعوّ (19.3).
     *
     * وهي مصدر «إجمالي شحنه» (24.2) كذلك: كلّ سطرٍ يحمل `base_usd` — قيمة
     * الشحنة بالدولار **بسعر الصرف لحظة تنفيذها** — فمجموعها هو ما شحنه
     * المدعوّ فعلًا بعملةٍ واحدة، لا جمعُ كوينزَ على دولارات.
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class, 'referral_id');
    }
}
