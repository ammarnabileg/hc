<?php

namespace Tests\Feature\Volunteer\Org;

use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Models\VolunteerCard;
use App\Services\Volunteer\Org\CardIssuer;
use Illuminate\Support\Facades\Cache;

/**
 * بطاقة المتطوّع الرقميّة (13.4-ر): صفحة عامّة بلا تسجيل، من القائمة المقفولة حصرًا،
 * **⛔ بلا بيانات تواصل**، وRep مخفيّ افتراضيًّا، وتصير «منتهية» بانتهاء العضويّة ولا تُحذَف.
 */
class VolunteerCardTest extends OrgTestCase
{
    private function card(string $code = 'VOL-C1'): VolunteerCard
    {
        return VolunteerCard::where('user_id', User::where('code', $code)->value('id'))->firstOrFail();
    }

    public function test_public_card_opens_without_login(): void
    {
        $card = $this->card();

        $this->get(route('card.show', $card->code))
            ->assertOk()
            ->assertSee($card->user->name)
            ->assertSee('#'.$card->user->code);
    }

    /** ⛔ ولا بيانات تواصل إطلاقًا — لا هاتف ولا بريد ولا زرّ واتساب */
    public function test_card_carries_no_contact_data_whatsoever(): void
    {
        $card = $this->card();
        $user = $card->user;

        $html = $this->get(route('card.show', $card->code))->assertOk()->getContent();

        $this->assertStringNotContainsString((string) $user->phone, $html);
        $this->assertStringNotContainsString((string) $user->email, $html);
        $this->assertStringNotContainsString('wa.me', $html);
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('tel:', $html);
    }

    /** إظهار Rep إعدادٌ وافتراضيّه **مخفيّ** */
    public function test_rep_is_hidden_by_default_and_shown_only_when_enabled(): void
    {
        $card = $this->card('VOL-SUP1'); // عضو نادي تميّز — Rep موجب عالٍ

        $this->assertNull(app(CardIssuer::class)->publicPayload($card)['rep_label']);

        Setting::where('key', 'volunteer_card.show_rep')->update(['value' => '1']);
        Cache::forget('settings');
        $card->update(['show_rep' => true]);

        $this->assertNotNull(app(CardIssuer::class)->publicPayload($card->refresh())['rep_label']);
    }

    public function test_card_turns_expired_when_membership_ends_and_is_not_deleted(): void
    {
        $card = $this->card();

        $this->get(route('card.verify', $card->code))->assertOk()->assertSee('سارية');

        $card->membership->update(['status' => 'ended', 'ended_at' => now()->subDay()]);

        $this->get(route('card.verify', $card->code))->assertOk()->assertSee('منتهية');

        // ولا تُحذَف — تبقى في السجلّ بتاريخيها (13.4-ق)
        $this->assertDatabaseHas('volunteer_cards', ['code' => $card->code, 'status' => 'expired']);
        $this->assertNotNull($card->refresh()->expired_at);
    }

    public function test_qr_points_at_the_verification_page(): void
    {
        $card = $this->card();

        $this->get(route('card.show', $card->code))
            ->assertOk()
            ->assertSee(route('card.verify', $card->code), false);
    }

    /** ⛔ لا بطاقة للعنصر الشرفيّ «أخوكم» — ليس بوزشنًا ولا عضويّة (13.4-ص) */
    public function test_no_card_is_ever_issued_for_the_honorary_element(): void
    {
        $honorary = User::where('code', 'VOL-HON')->firstOrFail();
        $membership = $honorary->memberships()->firstOrFail();

        $this->assertTrue((bool) Position::find($membership->position_id)->is_honorary);
        $this->assertNull(app(CardIssuer::class)->issueFor($membership));
        $this->assertDatabaseMissing('volunteer_cards', ['user_id' => $honorary->id]);
    }

    public function test_card_page_is_unavailable_when_the_feature_is_off(): void
    {
        $card = $this->card();

        Setting::where('key', 'volunteer_card.enabled')->update(['value' => '0']);
        Cache::forget('settings');

        $this->get(route('card.show', $card->code))->assertNotFound();
    }
}
