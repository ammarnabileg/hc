<?php

namespace Tests\Feature\Volunteer\Org;

use App\Models\ImageTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Models\VolunteerCard;
use Illuminate\Support\Facades\Cache;

/**
 * صورة البطاقة الفعليّة (13.4-ر-أ · د · هـ): مصدرها استوديو الصور، نسختان
 * جاهزتان (بادج/ستوري) بنفس التصميم، إطار ذهبيّ لعضو نادي +9.5، وQR دعوة اختياريّ.
 */
class VolunteerCardImageTest extends OrgTestCase
{
    private function card(string $code = 'VOL-C1'): VolunteerCard
    {
        return VolunteerCard::where('user_id', User::where('code', $code)->value('id'))->firstOrFail();
    }

    private function makeTemplate(): ImageTemplate
    {
        return ImageTemplate::create([
            'name' => 'قالب اختبار البطاقة',
            'purpose' => 'volunteer_card',
            'width_px' => 400,
            'height_px' => 600,
            'preset' => 'badge',
            'layers' => [
                ['type' => 'text', 'field' => 'name', 'x' => 20, 'y' => 20, 'size' => 24, 'color' => '#ffffff'],
            ],
            'audience' => 'everyone',
            'language' => 'ar',
            'is_active' => true,
            'is_archived' => false,
        ]);
    }

    public function test_the_image_route_serves_a_png_using_the_default_active_template(): void
    {
        $this->makeTemplate();
        $card = $this->card();

        $response = $this->get(route('card.image', [$card->code, 'badge']))->assertOk();

        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertNotFalse(@imagecreatefromstring($response->getContent()));
    }

    /** ⭐ نسختان جاهزتان بنفس التصميم — مقاسان مختلفان لا تصميمان منفصلان (13.4-ر-د) */
    public function test_badge_and_story_variants_render_at_their_own_preset_dimensions(): void
    {
        $this->makeTemplate();
        $card = $this->card();

        $badge = getimagesizefromstring($this->get(route('card.image', [$card->code, 'badge']))->getContent());
        $story = getimagesizefromstring($this->get(route('card.image', [$card->code, 'story']))->getContent());

        $this->assertSame([1200, 1800], [$badge[0], $badge[1]]);
        $this->assertSame([1080, 1920], [$story[0], $story[1]]);
    }

    public function test_an_unknown_variant_is_rejected(): void
    {
        $this->makeTemplate();
        $card = $this->card();

        $this->get(route('card.image', [$card->code, 'poster']))->assertNotFound();
    }

    public function test_no_template_yet_is_a_clean_404_not_a_crash(): void
    {
        $card = $this->card();

        $this->get(route('card.image', [$card->code, 'badge']))->assertNotFound();
    }

    public function test_the_image_route_is_gated_by_the_same_feature_toggle_as_the_page(): void
    {
        $this->makeTemplate();
        $card = $this->card();

        Setting::where('key', 'volunteer_card.enabled')->update(['value' => '0']);
        Cache::forget('settings');

        $this->get(route('card.image', [$card->code, 'badge']))->assertNotFound();
    }

    /** ⭐ إطار ذهبيّ لعضو نادي +9.5 فقط — لا لكلّ متطوّع (13.4-ر-د) */
    public function test_the_gold_frame_appears_only_for_club_members(): void
    {
        $this->makeTemplate();

        $regular = imagecreatefromstring($this->get(route('card.image', [$this->card('VOL-C1')->code, 'badge']))->getContent());
        $club = imagecreatefromstring($this->get(route('card.image', [$this->card('VOL-SUP1')->code, 'badge']))->getContent());

        // بكسل على خطّ الإطار المتوقَّع: ذهبيّ (#d4af37) لعضو النادي فقط
        $goldRgb = ['red' => 0xD4, 'green' => 0xAF, 'blue' => 0x37];

        $regularPixel = imagecolorsforindex($regular, imagecolorat($regular, 5, 900));
        $clubPixel = imagecolorsforindex($club, imagecolorat($club, 5, 900));

        $this->assertNotEquals($goldRgb['red'], $regularPixel['red'], 'العضو العاديّ ما لوش إطار ذهبيّ');
        $this->assertEquals($goldRgb, ['red' => $clubPixel['red'], 'green' => $clubPixel['green'], 'blue' => $clubPixel['blue']], 'عضو النادي لازم يظهر بإطار ذهبيّ');
    }

    /** ⭐ QR الدعوة اختياريّ — يظهر فقط بـ?invite=1 (13.4-ر-هـ) */
    public function test_the_invite_qr_is_only_composited_when_requested(): void
    {
        $this->makeTemplate();
        $card = $this->card();

        $plain = $this->get(route('card.image', [$card->code, 'badge']))->getContent();
        $withInvite = $this->get(route('card.image', ['code' => $card->code, 'variant' => 'badge', 'invite' => 1]))->getContent();

        $this->assertNotSame($plain, $withInvite);
    }
}
