<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Services\Growth\ContentKit;
use App\Services\Growth\OgCardRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * ⭐ الكارت الأسبوعيّ (21.2-د) وحزمة محتوى المتطوّعين (21.2-هـ) — شاشة واحدة:
 * «خُد المحتوى وانشره». فعلٌ رئيسيّ واحد لكلّ بطاقة: [انسخ] أو [نزّل الصورة].
 */
class ContentKitController extends Controller
{
    public function __construct(
        private readonly ContentKit $kit,
        private readonly OgCardRenderer $renderer,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $link = $this->kit->volunteerLink($user);

        return view('growth.content-kit', [
            'card' => $this->kit->currentCard(),
            'cardUrl' => route('growth.kit.weekly-card'),
            'link' => $link,
            'scripts' => $this->kit->scripts($link),
            'templates' => $this->kit->studioTemplates(),
            'periodDays' => $this->kit->periodDays(),
        ]);
    }

    /** صورة الكارت الأسبوعيّ — عامّة لتُنشَر مباشرةً بلا حساب */
    public function weeklyCard(): Response
    {
        $card = $this->kit->currentCard();

        // النصيحة نفسها هي العنوان فتُلَفّ على أسطر — والتسمية الفوقيّة تحمل نوع الكارت
        $svg = $this->renderer->card(
            'tip',
            $card['tip'],
            [$card['from']->translatedFormat('j F').' — '.$card['to']->translatedFormat('j F Y')],
            (string) setting('growth.weekly_card.footer', 'اتعلّم معنا'),
        );

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age='.(int) setting('growth.og.cache_seconds', 3600),
        ]);
    }
}
