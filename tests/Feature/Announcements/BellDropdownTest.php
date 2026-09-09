<?php

namespace Tests\Feature\Announcements;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Track;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Database\Seeders\AnnouncementDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⭐ جرس الإشعارات (2.8): كلّ تاب بعدّاده الخاصّ — لا عدّادًا إجماليًّا واحدًا
 * فوق الجرس يُنسَب خطأً لكلّ التابات الثلاثة. ومعه: زرّ «تعليم الكلّ كمقروء»
 * داخل قائمة الجرس المنسدلة كان بلا فورمٍ ولا معالج JS إطلاقًا — زرٌّ ميّت.
 */
class BellDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AnnouncementDemoSeeder::class);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'مستخدم اختبار',
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);
    }

    private function makeVolunteer(): User
    {
        $user = $this->makeUser();

        $entity = Entity::create([
            'track_id' => Track::where('key', 'department')->value('id'),
            'name_ar' => 'كيان اختبار',
            'status' => 'active',
        ]);

        Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::where('key', 'coordinator')->value('id'),
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_each_tab_shows_its_own_unread_count(): void
    {
        $volunteer = $this->makeVolunteer();

        Notifier::send($volunteer, 'system', 'إشعار منصّة 1');
        Notifier::send($volunteer, 'system', 'إشعار منصّة 2');
        Notifier::send($volunteer, 'task', 'إشعار تطوّع', null, null, 'volunteer');

        $response = $this->actingAs($volunteer)->get(route('notifications.index'));

        $response->assertOk();
        // الكلّ = 3، المنصّة = 2، التطوّع = 1 — ثلاثة أرقام مختلفة لا رقمٌ واحد مكرّر
        $response->assertSeeInOrder(['data-bell-tab="all"'], false);
        $this->assertBellTabCount($response->getContent(), 'all', 3);
        $this->assertBellTabCount($response->getContent(), 'platform', 2);
        $this->assertBellTabCount($response->getContent(), 'volunteer', 1);
    }

    public function test_the_volunteer_tab_badge_is_absent_for_non_volunteers(): void
    {
        $trainee = $this->makeUser();
        Notifier::send($trainee, 'system', 'إشعار منصّة');

        $response = $this->actingAs($trainee)->get(route('notifications.index'));

        $response->assertOk()->assertDontSee('data-bell-tab="volunteer"', false);
    }

    /**
     * الزرّ كان بلا فورمٍ ولا معالج JS — بلا أثرٍ على الخادم إطلاقًا مهما
     * ضغطه المستخدم. والقياس هنا **داخل قائمة الجرس المنسدلة بعينها**
     * (`data-bell-panel`) لا الصفحة كلّها — فالصفحة نفسها تحمل فورم «تعليم
     * الكلّ» الحقيقيّ الخاصّ بمركز الإشعارات الكامل أصلًا، وقياسٌ غير مُحكَم
     * كان سيمرّ بالصدفة بسبب ذاك الفورم الآخر لا فورم الجرس نفسه.
     */
    public function test_the_mark_all_button_in_the_dropdown_is_a_real_working_form(): void
    {
        $user = $this->makeUser();

        $html = $this->actingAs($user)->get(route('notifications.index'))->getContent();

        $panelStart = strpos($html, 'data-bell-panel');
        $this->assertNotFalse($panelStart, 'قائمة الجرس المنسدلة مش موجودة في الصفحة.');

        // نطاق قائمة الجرس وحدها — قبل أوّل تاب، حيث يقع زرّ «تعليم الكلّ»
        $panel = substr($html, $panelStart, strpos($html, 'data-bell-tab="all"') - $panelStart);

        $this->assertStringContainsString('<form', $panel, 'زرّ «تعليم الكلّ» في الجرس بلا فورمٍ يحمله.');
        $this->assertStringContainsString('action="'.route('notifications.read-all').'"', $panel);
        $this->assertStringContainsString('type="submit"', $panel, 'الزرّ لسّه type="button" بلا فعلٍ حقيقيّ.');
    }

    private function assertBellTabCount(string $html, string $tab, int $expected): void
    {
        $needle = 'data-bell-tab="'.$tab.'"';
        $pos = strpos($html, $needle);
        $this->assertNotFalse($pos, "التاب $tab مش موجود في الصفحة.");

        // نطاق قريب من زرّ التاب — العدّاد شارةٌ داخل نفس الزرّ
        $window = substr($html, $pos, 400);

        if ($expected > 0) {
            $this->assertStringContainsString('>'.$expected.'<', $window, "تاب $tab المفروض يعرض $expected.");
        }
    }
}
