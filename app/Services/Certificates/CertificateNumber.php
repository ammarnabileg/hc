<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use Illuminate\Support\Facades\DB;

/**
 * نظام الترقيم المخصّص لكلّ نوع/اعتماد: بادئة + سنة + تسلسل (12.5-ب)
 * **بضمان عدم التكرار ولا الفجوات**.
 *
 * ⭐ لماذا عدّادٌ مستقلّ لا اشتقاقٌ من الصفوف القائمة؟ لأنّ الاشتقاق يرجع
 * للخلف: تُحذَف شهادةٌ أو يُعاد بذر البيانات فيُعاد إنتاج **نفس الكود** لشخصٍ
 * آخر — والكود هو ما تتحقّق به الجهات (8 · 8.1)، فتكرارُه بابُ تزوير مفتوح.
 * العدّاد لا يرجع أبدًا: رقمٌ صدر مرّةً لا يصدر ثانيةً ولو مُحِي صفّه.
 *
 * وبلا فجوات كذلك: النداء يجري داخل معاملة الإصدار نفسها، فالمحاولة التي
 * تفشل تُرجِع العدّاد معها ولا تستهلك رقمًا.
 */
class CertificateNumber
{
    private const TABLE = 'certificate_number_sequences';

    public function next(CertificateType $type, ?int $year = null): string
    {
        $prefix = $type->numbering_prefix ?: (string) setting('certificates.numbering.default_prefix', 'HC');
        $separator = (string) setting('certificates.numbering.separator', '-');
        $padding = (int) setting('certificates.numbering.padding', 6);
        $year ??= (int) now()->year;

        $scope = $prefix.$separator.$year.$separator;

        return DB::transaction(function () use ($scope, $padding) {
            // أوّل نداءٍ لهذا النطاق يبدأ من أعلى رقمٍ صادرٍ فعلًا، فلا يصطدم بما سبقه
            DB::table(self::TABLE)->insertOrIgnore([
                'scope' => $scope,
                'last_number' => $this->highestIssued($scope),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table(self::TABLE)->where('scope', $scope)->lockForUpdate()->first();
            $next = (int) ($row->last_number ?? 0) + 1;

            DB::table(self::TABLE)->where('scope', $scope)->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

            return $scope.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
        });
    }

    /** أعلى رقمٍ صادرٍ في هذا النطاق — للبَذر الأوّل للعدّاد وحده */
    private function highestIssued(string $scope): int
    {
        return (int) (Certificate::query()
            ->where('code', 'like', $scope.'%')
            ->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($scope)))
            ->max() ?? 0);
    }
}
