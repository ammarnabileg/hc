<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/ping` — بلا Scope، يتطلّب مفتاحًا صالحًا فقط (12.15-أ).
 * أفضل نقطة لاختبار المصادقة كاملةً: مفتاحٌ صحيح/خاطئ/منتهٍ/مُبطَل.
 */
class PingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('apiKey');

        return response()->json([
            'ok' => true,
            'key_name' => $apiKey->name,
        ]);
    }
}
