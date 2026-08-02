<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use Illuminate\Support\Facades\DB;

/**
 * نظام الترقيم المخصّص لكلّ نوع/اعتماد: بادئة + سنة + تسلسل (12.5-ب)
 * **بضمان عدم التكرار ولا الفجوات** — التسلسل يُشتقّ من أكبر رقمٍ قائم داخل معاملة،
 * فالمحاولة الفاشلة لا تستهلك رقمًا ولا تترك ثغرة.
 */
class CertificateNumber
{
    public function next(CertificateType $type, ?int $year = null): string
    {
        $prefix = $type->numbering_prefix ?: (string) setting('certificates.numbering.default_prefix', 'HC');
        $separator = (string) setting('certificates.numbering.separator', '-');
        $padding = (int) setting('certificates.numbering.padding', 6);
        $year ??= (int) now()->year;

        $scope = $prefix.$separator.$year.$separator;

        return DB::transaction(function () use ($scope, $padding) {
            $last = Certificate::query()
                ->where('code', 'like', $scope.'%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->pluck('code')
                ->map(fn (string $code) => (int) substr($code, strlen($scope)))
                ->max() ?? 0;

            return $scope.str_pad((string) ($last + 1), $padding, '0', STR_PAD_LEFT);
        });
    }
}
