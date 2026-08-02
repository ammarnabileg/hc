<?php

namespace Tests\Feature\Ui;

use App\Models\SavedView;
use App\Models\Setting;
use App\Models\UserFirstRun;
use App\Services\Ui\UndoStack;
use Illuminate\Support\Facades\Cache;

/**
 * مساحة العمل (الدستور 2.15-د): التثبيت (Pin) · العروض المحفوظة ·
 * البحث الموحّد (Ctrl+K) · شاشة أوّل مرّة · التراجع خلال ثوانٍ.
 */
class WorkspaceTest extends UiTestCase
{
    // ------------------------------------------------------------ التثبيت (Pin)

    public function test_user_can_pin_and_unpin_a_page(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->postJson(route('ui.pins.toggle'), ['route' => 'achievements.leaderboard', 'label' => 'الليدر بورد'])
            ->assertOk()
            ->assertJson(['ok' => true, 'pinned' => true]);

        $this->assertSame('achievements.leaderboard', $user->fresh()->pinned_pages[0]['route']);

        // الضغطة الثانية تفكّ التثبيت — نفس الزرّ لا زرّان
        $this->actingAs($user)
            ->postJson(route('ui.pins.toggle'), ['route' => 'achievements.leaderboard', 'label' => 'الليدر بورد'])
            ->assertOk()
            ->assertJson(['pinned' => false]);

        $this->assertSame([], $user->fresh()->pinned_pages);
    }

    public function test_pinned_pages_appear_at_the_top_of_the_sidebar(): void
    {
        $user = $this->trainee();
        $user->forceFill(['pinned_pages' => [
            ['route' => 'achievements.leaderboard', 'label' => 'الليدر بورد', 'url' => route('achievements.leaderboard')],
        ]])->save();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('المثبَّتة');
    }

    public function test_pinning_an_unknown_route_is_rejected(): void
    {
        $this->actingAs($this->trainee())
            ->postJson(route('ui.pins.toggle'), ['route' => 'route.does.not.exist', 'label' => 'وهم'])
            ->assertStatus(422);
    }

    public function test_pin_limit_comes_from_settings_not_from_the_code(): void
    {
        Setting::where('key', 'ux.pins.max')->update(['value' => '1']);
        Cache::forget('settings');

        $user = $this->trainee();

        $this->actingAs($user)->postJson(route('ui.pins.toggle'), ['route' => 'dashboard', 'label' => 'الرئيسيّة'])->assertOk();

        $this->actingAs($user)
            ->postJson(route('ui.pins.toggle'), ['route' => 'achievements.leaderboard', 'label' => 'الليدر بورد'])
            ->assertStatus(422);
    }

    public function test_pins_can_be_reordered(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->postJson(route('ui.pins.toggle'), ['route' => 'dashboard', 'label' => 'الرئيسيّة']);
        $this->actingAs($user)->postJson(route('ui.pins.toggle'), ['route' => 'achievements.leaderboard', 'label' => 'الليدر بورد']);

        $this->actingAs($user)
            ->postJson(route('ui.pins.reorder'), ['routes' => ['achievements.leaderboard', 'dashboard']])
            ->assertOk();

        $this->assertSame('achievements.leaderboard', $user->fresh()->pinned_pages[0]['route']);
    }

    // ------------------------------------------------------- العروض المحفوظة

    public function test_a_filter_combination_is_saved_and_shown_as_a_chip(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->from(route('achievements.leaderboard'))
            ->post(route('ui.views.store'), [
                'screen' => 'achievements.leaderboard',
                'name' => 'محافظتي — آخر 7 أيّام',
                'filters' => ['scope' => 'governorate', 'days' => '7'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('saved_views', ['user_id' => $user->id, 'name' => 'محافظتي — آخر 7 أيّام']);

        $this->actingAs($user)
            ->get(route('achievements.leaderboard', ['days' => 7]))
            ->assertOk()
            ->assertSee('محافظتي — آخر 7 أيّام');
    }

    public function test_a_saved_view_belongs_to_its_owner_only(): void
    {
        $view = SavedView::create([
            'user_id' => $this->trainee()->id,
            'screen' => 'achievements.leaderboard',
            'name' => 'عرض غيري',
            'filters' => [],
        ]);

        $this->actingAs($this->trainee())
            ->delete(route('ui.views.destroy', $view))
            ->assertForbidden();
    }

    // ------------------------------------------------------- البحث الموحّد

    public function test_command_palette_returns_pages_people_and_tasks(): void
    {
        $user = $this->trainee();

        $response = $this->actingAs($user)->getJson(route('ui.palette', ['q' => 'الليدر']));

        $response->assertOk()->assertJsonStructure(['pages', 'people', 'tasks']);
        $this->assertNotEmpty($response->json('pages'));
    }

    public function test_command_palette_hides_pages_the_user_cannot_open(): void
    {
        // ما لا يملكه المستخدم لا يظهر في نتائجه أصلًا (2.15-أ-7)
        $labels = collect($this->actingAs($this->trainee())->getJson(route('ui.palette', ['q' => 'الماليّات']))->json('pages'))
            ->pluck('label');

        $this->assertFalse($labels->contains(fn ($label) => str_contains($label, 'الماليّات')));
    }

    // ------------------------------------------------------- شاشة أوّل مرّة

    public function test_first_run_screen_shows_once_and_can_be_replayed(): void
    {
        Setting::where('key', 'ux.first_time.enabled_screens')->update(['value' => '["dashboard"]']);
        Cache::forget('settings');

        $user = $this->trainee();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-first-run', false);

        $this->actingAs($user)->postJson(route('ui.first-run.seen'), ['screen' => 'dashboard'])->assertOk();

        $this->assertDatabaseHas('user_first_runs', ['user_id' => $user->id, 'screen' => 'dashboard']);

        // زرّ «؟» يعيدها وقت ما شاء
        $this->actingAs($user)->postJson(route('ui.first-run.seen'), ['screen' => 'dashboard', 'again' => true])->assertOk();

        $this->assertSame(0, UserFirstRun::where('user_id', $user->id)->count());
    }

    // ------------------------------------------------------- التراجع

    public function test_undo_restores_the_previous_value_within_the_window(): void
    {
        $user = $this->trainee(['name' => 'الاسم القديم']);
        $stack = app(UndoStack::class);

        $token = $stack->capture($user, $user, ['name'], 'تعديل الاسم');
        $user->forceFill(['name' => 'الاسم الجديد'])->save();

        $this->actingAs($user)->postJson(route('ui.undo', $token))->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('الاسم القديم', $user->fresh()->name);
    }

    public function test_undo_after_the_window_explains_what_happened_and_what_to_do(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->postJson(route('ui.undo', 'a-token-that-expired'))
            ->assertStatus(422)
            ->assertJson(['ok' => false])
            ->assertJsonFragment(['message' => 'مهلة التراجع خلصت — تقدر تعدّل من الشاشة عادي.']);
    }

    public function test_undo_window_is_a_setting_not_a_hard_coded_number(): void
    {
        Setting::where('key', 'ux.undo.seconds')->update(['value' => '9']);
        Cache::forget('settings');

        $this->assertSame(9, app(UndoStack::class)->seconds());
    }
}
