<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ⭐ تأجيل بانر الموافقة (21.1-د · 21.3-د) — «بلا Dark Patterns: البانر ليس حاصرًا».
 *
 * والفرق الجوهريّ عن `PublicPagesController::storeConsent()`: **التأجيل ليس
 * قرارًا**. لا يُكتَب `accepted` ولا `rejected` — لا على المستخدم ولا حتّى في
 * كوكي القرار (`tracking_consent`) — فيبقى العمود/الكوكي كما كانا: **فارغين**.
 * وغياب الموافقة يبقى **رفضًا عمليًّا** (2.9: الصمت ليس موافقة) — `Consent::allows()`
 * لا تُفرّق أصلًا بين «لم يُسأل» و«أُجِّل»، فكلاهما لا يُشغِّل شيئًا.
 *
 * وكلّ ما تفعله هذه النقطة: كوكيّ تأجيلٍ منفصل يُخفي **البانر نفسه** مؤقّتًا —
 * مدّته إعدادٌ (2.13) — ثمّ يعود من تلقاء نفسه بعد انقضائها في زيارةٍ لاحقة.
 * والقراءة في `resources/views/partials/consent-banner.blade.php` وحدها، فلا
 * حاجة لمسّ `App\Services\Ads\Consent` — تلك مبنيّةٌ للتوّ ولا تُمَسّ (2.9 · 21.3-د).
 */
class ConsentSnoozeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $days = max((int) setting('ads.consent.snooze_days', 7), 1);

        return back()->withCookie(cookie(
            'tracking_consent_snoozed_until',
            now()->addDays($days)->toIso8601String(),
            $days * 24 * 60,
        ));
    }
}
