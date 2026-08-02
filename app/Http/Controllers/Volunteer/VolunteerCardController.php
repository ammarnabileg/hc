<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\VolunteerCard;
use App\Services\Certificates\QrCode;
use App\Services\Volunteer\Org\CardIssuer;
use Illuminate\View\View;

/**
 * بطاقة المتطوّع الرقميّة — صفحة عامّة `/card/CODE` بلا تسجيل (13.4-ر).
 *
 *  - المحتوى من **القائمة المقفولة حصرًا**، و**⛔ لا بيانات تواصل إطلاقًا**.
 *  - **Rep من `volunteer_card.show_rep` وافتراضيّه مخفيّ**.
 *  - **QR يفتح صفحة تحقّق** تبيّن سارية/منتهية بنفس منظومة الشهادات (8.1).
 *  - **تصير «منتهية» تلقائيًّا بانتهاء العضويّة ولا تُحذَف**.
 *  - **⛔ لا بطاقة للعنصر الشرفيّ «أخوكم»**.
 */
class VolunteerCardController extends Controller
{
    public function __construct(private readonly CardIssuer $issuer) {}

    public function show(string $code): View
    {
        $card = $this->resolve($code);
        $data = $this->issuer->publicPayload($card);

        return view('cards.show', [
            'card' => $card,
            'data' => $data,
            'verifyUrl' => route('card.verify', $card->code),
            'qr' => QrCode::matrix(route('card.verify', $card->code)),
        ]);
    }

    /** صفحة التحقّق: **سارية** أو **منتهية** — فلا يمثّلنا أحدٌ ببطاقة قديمة */
    public function verify(string $code): View
    {
        $card = $this->resolve($code);

        return view('cards.verify', [
            'card' => $card,
            'data' => $this->issuer->publicPayload($card),
            'valid' => $this->issuer->isValid($card),
            'cardUrl' => route('card.show', $card->code),
        ]);
    }

    /** البطاقة موجودة ومفعَّلة وليست للعنصر الشرفيّ — وحالتها مُزامَنة مع العضويّة */
    private function resolve(string $code): VolunteerCard
    {
        abort_unless(setting('volunteer_card.enabled', true), 404);

        $card = VolunteerCard::query()
            ->where('code', $code)
            ->with(['user.country', 'user.governorate', 'membership.entity.track', 'membership.position'])
            ->firstOrFail();

        // ⛔ لا بطاقة للعنصر الشرفيّ — ليس بوزشنًا ولا عضويّة (13.4-ص)
        abort_if((bool) $card->membership?->position?->is_honorary, 404);

        return $this->issuer->syncStatus($card);
    }
}
