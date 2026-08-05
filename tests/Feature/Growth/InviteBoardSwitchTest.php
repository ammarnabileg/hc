<?php

namespace Tests\Feature\Growth;

/**
 * ⭐ مفتاح إيقافٍ للوحة متصدّري الدعوات (21.1-هـ) — «تفعيل/إيقاف كلّ حلقة على حدة».
 *
 * وكانت اللوحة **بلا مفتاح**: مسارها يفتح دائمًا مهما قال الإعداد. والمحظور
 * **يُخفى لا يُعطَّل** (2.15-أ-7) — فالإيقاف هنا يعني **404 حقيقيّ من الخادم**
 * لا زرًّا رماديًّا في واجهةٍ ما زال مسارها مفتوحًا خلفها.
 */
class InviteBoardSwitchTest extends GrowthTestCase
{
    public function test_board_is_reachable_by_default(): void
    {
        $this->actingAs($this->trainee())
            ->get(route('growth.invite.board'))
            ->assertOk();
    }

    /** ⭐ الإيقاف حقيقيّ: 404 من الخادم — لا صفحةً معطَّلة تظهر ثمّ تُخفى بالواجهة */
    public function test_disabling_the_switch_hides_the_route_with_a_real_404(): void
    {
        $this->setSetting('growth.invite_board.enabled', '0', 'bool');

        $this->actingAs($this->trainee())
            ->get(route('growth.invite.board'))
            ->assertNotFound();
    }

    /** والتفعيل يعيد المسار فورًا — لا حاجة لأثرٍ يبقى بعد إعادة التشغيل */
    public function test_re_enabling_restores_the_route(): void
    {
        $this->setSetting('growth.invite_board.enabled', '0', 'bool');
        $this->actingAs($user = $this->trainee())->get(route('growth.invite.board'))->assertNotFound();

        $this->setSetting('growth.invite_board.enabled', '1', 'bool');
        $this->actingAs($user)->get(route('growth.invite.board'))->assertOk();
    }
}
