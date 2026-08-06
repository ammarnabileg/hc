<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\VolunteerCard;
use App\Services\Certificates\QrCode;
use App\Services\Images\ImageRenderer;
use App\Services\Images\TemplateLayers;
use App\Services\Referral\ReferralService;
use App\Services\Volunteer\Org\CardIssuer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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

    /**
     * صورة البطاقة الفعليّة — **مصدرها استوديو الصور (12.14) لا مولّدًا موازيًا**
     * (13.4-ر-أ). نسختان بمقاسٍ واحد يُعاد توزيع طبقاته نسبيًّا (`TemplateLayers::
     * rescale()`) لا تصميمان منفصلان: `badge` (بادج طباعة) و`story` (نشر).
     *
     * وإطارٌ ذهبيّ لعضو نادي +9.5 (13.4-ر-د) + **QR الدعوة اختياريًّا** بـ`?invite=1`
     * (13.4-ر-هـ) — كلاهما يُركَّب فوق الناتج المُخزَّن لا داخل محرّك الرسم العامّ،
     * فيبقى محرّك استوديو الصور عامًّا لكلّ الأغراض لا خاصًّا بالبطاقة.
     */
    public function image(Request $request, string $code, string $variant, ImageRenderer $renderer, ReferralService $referrals): Response
    {
        $card = $this->resolve($code);

        $presets = (new TemplateLayers)->presets();
        abort_unless(array_key_exists($variant, $presets), 404);

        $template = $card->image_template ?: $this->issuer->defaultTemplate($card->language);
        abort_unless($template, 404, (string) setting('volunteer_card.image.no_template', 'مفيش تصميم بطاقة مُفعَّل بعد.'));

        $preset = $presets[$variant];
        $data = $this->issuer->imageData($card);
        $data['_variant'] = $variant;

        $withInvite = $request->boolean('invite');

        $canvas = clone $template;
        $canvas->layers = (new TemplateLayers)->rescale(
            (array) $template->layers,
            max(1, (int) $template->width_px),
            max(1, (int) $template->height_px),
            (int) $preset['width'],
            (int) $preset['height'],
        );
        $canvas->width_px = $preset['width'];
        $canvas->height_px = $preset['height'];

        $path = $renderer->renderWithData($canvas, $card->user, $data, $request->user());
        $binary = Storage::disk('public')->get($path);

        $isClub = (bool) ($this->issuer->publicPayload($card)['is_club'] ?? false);
        $inviteUrl = $withInvite && $card->user ? $referrals->link($card->user) : null;

        if ($isClub || $inviteUrl) {
            $binary = $this->compose($binary, (int) $preset['width'], (int) $preset['height'], $isClub, $inviteUrl);
        }

        return response($binary)->header('Content-Type', 'image/png');
    }

    /** إطار ذهبيّ (نادي +9.5) + QR دعوة اختياريّ — تركيبٌ فوق الناتج لا داخل المصنع العامّ */
    private function compose(string $binary, int $width, int $height, bool $goldFrame, ?string $inviteUrl): string
    {
        $canvas = imagecreatefromstring($binary);

        if (! $canvas) {
            return $binary;
        }

        if ($goldFrame) {
            $gold = imagecolorallocate($canvas, 0xD4, 0xAF, 0x37);
            $thickness = max(4, intdiv(min($width, $height), 120));
            imagesetthickness($canvas, $thickness);
            imagerectangle($canvas, intdiv($thickness, 2), intdiv($thickness, 2), $width - 1 - intdiv($thickness, 2), $height - 1 - intdiv($thickness, 2), $gold);
        }

        if ($inviteUrl) {
            $qrSize = (int) setting('volunteer_card.image.invite_qr_size', 220);
            $qr = @imagecreatefromstring(QrCode::png($inviteUrl, $qrSize));

            if ($qr) {
                $margin = (int) setting('volunteer_card.image.invite_qr_margin', 32);
                imagecopy($canvas, $qr, $width - $qrSize - $margin, $height - $qrSize - $margin, 0, 0, imagesx($qr), imagesy($qr));
                imagedestroy($qr);
            }
        }

        ob_start();
        imagepng($canvas);
        $out = (string) ob_get_clean();
        imagedestroy($canvas);

        return $out;
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
            ->with(['user.country', 'user.governorate', 'membership.entity.track', 'membership.position', 'image_template'])
            ->firstOrFail();

        // ⛔ لا بطاقة للعنصر الشرفيّ — ليس بوزشنًا ولا عضويّة (13.4-ص)
        abort_if((bool) $card->membership?->position?->is_honorary, 404);

        return $this->issuer->syncStatus($card);
    }
}
