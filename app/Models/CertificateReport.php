<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بلاغ «شهادة مشبوهة» (12.5-هـ · 24.1).
 *
 * حالاته **هي إجراءات المراجعة الثلاثة المنصوص عليها** لا غيرها:
 * تجاهل · إلغاء الشهادة · تصعيد — ولا حالة رابعة مخترَعة.
 */
class CertificateReport extends Model
{
    use HasFactory;

    /** جديد — لم يُراجَع بعد */
    public const NEW = 'new';

    /** تجاهل (24.1) */
    public const DISMISSED = 'dismissed';

    /** إلغاء الشهادة (24.1) — والإلغاء لا يقع إلّا على تزويرٍ مثبَت */
    public const REVOKED = 'revoked';

    /** تصعيد (24.1) */
    public const ESCALATED = 'escalated';

    /** @return list<string> */
    public static function actions(): array
    {
        return [self::DISMISSED, self::REVOKED, self::ESCALATED];
    }

    protected $table = 'certificate_reports';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'certificate_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
