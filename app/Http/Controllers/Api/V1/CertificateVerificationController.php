<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Trainee\PublicVerificationController;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/v1/certificates/{code}/verify` — Scope `read:certificates` (12.15-أ).
 *
 * ⛔ **لا بيانات حسّاسة إضافيّة** — الحالة وحدها، بلا اسم صاحب الشهادة ولا أيّ
 * حقلٍ آخر. والاستعلام **نفسه** الذي تستخدمه صفحة التحقّق العامّة
 * (`PublicVerificationController::lookup()`) — مصدر حقيقةٍ واحد (2.11)، فلا
 * يختلف تعريف «صالحة/منتهية» بين شاشةٍ وواجهة API.
 */
class CertificateVerificationController extends Controller
{
    public function __invoke(string $code): JsonResponse
    {
        $certificate = PublicVerificationController::lookup($code);

        if (! $certificate) {
            return response()->json(['code' => $code, 'status' => 'not_found']);
        }

        return response()->json([
            'code' => $certificate->code,
            'status' => $certificate->status,
        ]);
    }
}
