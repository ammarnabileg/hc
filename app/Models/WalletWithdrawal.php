<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** طلب سحب أرباح بالدولار (19.2 · 19.3) */
class WalletWithdrawal extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    /** الحالات التي ما زال المبلغ فيها محجوزًا خارج «جاهزة للسحب» */
    public const IN_TRANSIT = [self::PENDING, self::PROCESSING];

    protected $table = 'wallet_withdrawals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'fee_percent' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function processed_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /** حالة بقاموس 2.16 — لون ومعه رمز دائمًا */
    public function state(): string
    {
        return match ($this->status) {
            self::PAID => 'ok',
            self::PROCESSING => 'warn',
            self::REJECTED => 'danger',
            default => 'idle',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PAID => 'مستلمة',
            self::PROCESSING => 'قيد التحويل',
            self::REJECTED => 'مرفوضة',
            default => 'قيد المراجعة',
        };
    }
}
