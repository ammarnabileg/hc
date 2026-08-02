<?php

namespace Tests\Feature\Volunteer\Org;

use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Org\RepBadge;

/**
 * ⭐ المؤشّر الأحمر يعني **تخطّي −8** لا −5 (13.4-هـ · 13.4-و · 13.4-ن-ز · 13.4-م).
 *
 * المعتمَد بعد حسم التعارض النصّيّ: **−5 عتبة إنذار · −8 مؤشّر أحمر**؛ والتسمية
 * بين قوسين في 13.4-هـ («عتبة الإنذار — القسم 23») خطأ تحريريّ.
 *
 * و`RepBadge` هي ما يُرسَم بجانب الاسم في كلّ مكان — الكانفاس ودليل الأعضاء
 * والبروفايل والبطاقة — فكان كلّ من بين −5 و−8 يُفضَح بالأحمر أمام فريقه،
 * وكلّ مُسكَّنٍ حديثًا (Rep = 0) يبدأ بشارة تنبيه صفراء.
 */
class RepBadgeThresholdsTest extends OrgTestCase
{
    /** ⭐ −6 ليس أحمر، و−9 أحمر */
    public function test_minus_six_is_not_red_and_minus_nine_is(): void
    {
        $this->assertSame('warn', RepBadge::state(-6.0));
        $this->assertSame('danger', RepBadge::state(-9.0));

        // وعلى العتبة نفسها يبدأ الأحمر
        $this->assertSame('danger', RepBadge::state(rep_rule('limit.red_indicator')));
        $this->assertSame('warn', RepBadge::state(rep_rule('limit.red_indicator') + 0.1));
    }

    /** ⭐ والمُسكَّن حديثًا (صفر) لا يُعرَض بتنبيه أصفر */
    public function test_a_newly_placed_volunteer_at_zero_is_not_warned(): void
    {
        $this->assertSame('ok', RepBadge::state(0.0));
    }

    /** الشارة والخدمة **مصدرٌ واحد** — فلا تتناقض شاشتان على نفس الرقم */
    public function test_badge_and_service_agree_on_every_state(): void
    {
        $rep = app(RepService::class);

        foreach ([0.0, -0.5, -5.1, -7.9, -8.0, -9.0, -10.0, 3.0, 9.4] as $score) {
            $this->assertSame(
                $rep->state($score),
                RepBadge::state($score),
                'اختلفت الشارة عن الخدمة عند '.$score,
            );
        }
    }

    /** والتميّز يبقى فوقهما: من بلغ عتبة النادي شارته ذهبيّة */
    public function test_club_threshold_still_wins_and_null_is_idle(): void
    {
        $this->assertSame('honor', RepBadge::state(RepBadge::clubThreshold()));
        $this->assertSame('idle', RepBadge::state(null));
    }
}
