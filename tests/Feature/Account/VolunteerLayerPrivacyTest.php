<?php

namespace Tests\Feature\Account;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\RepScore;
use App\Models\Track;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Volunteer\Profile\ViewerLevel;

/**
 * طبقة التطوّع «تظهر فقط لمن هو متطوّع، **ولا يراها ولا يعرف بوجودها غيره**»
 * (الدستور 10.0) — والمستويات **أربعة** لا خمسة (10.0-ج · 13.4-م):
 * صاحب البروفايل · زميل **متطوّع** · أبلاين مخوَّل · أدمن.
 *
 * فالزائر بلا جلسة ليس واحدًا من الأربعة، ولا يقع في «زميل» — والحجب
 * **على الخادم**: البيانات لا تُحسَب ولا تُرسَل، لا عنصرٌ يُخفى في القالب.
 */
class VolunteerLayerPrivacyTest extends AccountTestCase
{
    private function volunteer(array $attributes = []): User
    {
        $user = $this->trainee($attributes + ['code' => 'VOL001', 'name' => 'هدير المتطوّعة']);

        $track = Track::firstOrCreate(['key' => 'main'], ['name_ar' => 'المسار الرئيسيّ']);
        $entity = Entity::firstOrCreate(
            ['track_id' => $track->id, 'name_ar' => 'قسم الإعلام'],
            ['status' => 'active'],
        );
        $position = Position::where('key', 'coordinator')->firstOrFail();

        Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => $position->id,
            'is_primary' => true,
            'started_at' => now()->subMonths(7),
            'status' => 'active',
        ]);

        // أرقام الطبقة التي رصدها الأوديت: درجة الالتزام · VXP · الترتيب
        RepScore::create(['user_id' => $user->id, 'score' => 7.25]);

        $vxp = Currency::where('code', 'vxp')->firstOrFail();
        WalletBalance::create(['user_id' => $user->id, 'currency_id' => $vxp->id, 'balance' => 4321]);

        return $user->fresh();
    }

    /**
     * ⛔ التسريب نفسه: طلبٌ **بلا أيّ جلسة** على تاب التطوّع —
     * فلا Rep ولا VXP ولا ترتيب ولا بوزشن ولا مدّة خدمة في جسم الردّ **أصلًا**.
     */
    public function test_a_guest_receives_no_volunteer_layer_at_all(): void
    {
        $owner = $this->volunteer();

        $response = $this->get(route('u.profile', ['code' => $owner->code, 'tab' => 'volunteer']));

        // البروفايل العامّ نفسه يبقى مفتوحًا (13.1) — الطبقة وحدها هي المحجوبة
        $response->assertOk()->assertSee('هدير المتطوّعة');

        // «ولا **يعرف بوجودها**» — فلا رقاقة تاب ولا رابط إليها
        $response->assertDontSee('tab=volunteer', false)
            ->assertDontSee('data-volunteer-profile', false);

        // ولا تُحسَب ولا تُرسَل: لا أرقام الطبقة ولا عناوينها
        $response->assertDontSee('درجة الالتزام')
            ->assertDontSee('7.25')
            ->assertDontSee('نقاط الخبرة VXP')
            ->assertDontSee('4321')
            ->assertDontSee('مدّة الخدمة')
            ->assertDontSee('حضور الاجتماعات')
            ->assertDontSee('قسم الإعلام');
    }

    /** ولا فرق بين تاب وتاب: كلّ تابات الطبقة الخمس محجوبة عن الزائر */
    public function test_every_volunteer_tab_is_dead_for_a_guest(): void
    {
        $owner = $this->volunteer();

        foreach (['volunteer', 'volunteer_contact', 'volunteer_org', 'volunteer_performance', 'volunteer_notes'] as $tab) {
            $this->get(route('u.profile', ['code' => $owner->code, 'tab' => $tab]))
                ->assertOk()
                ->assertDontSee('data-volunteer-profile', false)
                ->assertDontSee('درجة الالتزام');
        }
    }

    /** المستويات أربعة: الزائر المجهول **ليس** «زميلًا» — والزميل متطوّع (13.4-م-2) */
    public function test_the_four_levels_never_admit_a_guest_as_a_peer(): void
    {
        $owner = $this->volunteer();
        $levels = app(ViewerLevel::class);

        $this->assertNull($levels->for(null, $owner), 'الزائر بلا حساب خارج المستويات الأربعة.');

        // ومَن ليس متطوّعًا ليس «زميلًا متطوّعًا» ولو كان مسجَّلًا
        $this->assertNull($levels->for($this->trainee(['code' => 'UPLAIN01']), $owner));
    }
}
