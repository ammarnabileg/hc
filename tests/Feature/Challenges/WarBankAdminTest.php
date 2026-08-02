<?php

namespace Tests\Feature\Challenges;

use App\Http\Controllers\Admin\GamificationController;
use App\Models\Game;
use App\Models\User;
use App\Models\WarQuestion;
use Illuminate\Http\UploadedFile;

/**
 * بنك أسئلة الحروب (12.10-ب · 24.2) وتاب الألعاب (24.2).
 */
class WarBankAdminTest extends ChallengeTestCase
{
    public function test_bank_screen_lists_questions_with_filters_and_counters(): void
    {
        $admin = $this->warAdmin();

        $this->actingAs($admin)
            ->get(route('admin.wars.bank.index', ['q' => 'كم دقيقة في اليوم']))
            ->assertOk()
            ->assertSee('بنك أسئلة الحروب', false)
            ->assertSee('الرقميّة المفعّلة', false)
            ->assertSee('كم دقيقة في اليوم الواحد؟', false);
    }

    /** ⭐ الإجابة مخفيّة افتراضيًّا — والكشف مؤقّت ومسجَّل في Audit (24.2). */
    public function test_answers_are_hidden_until_a_logged_reveal(): void
    {
        $admin = $this->warAdmin();

        $this->actingAs($admin)
            ->get(route('admin.wars.bank.index', ['q' => 'كم دقيقة في اليوم']))
            ->assertOk()
            ->assertSee('اكشف', false)
            ->assertDontSee('>1440<', false);

        $question = WarQuestion::query()->where('text', 'كم دقيقة في اليوم الواحد؟')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.wars.bank.index'))
            ->post(route('admin.wars.bank.reveal', $question))
            ->assertSessionHas('reveal_answer', $question->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'wars_bank.reveal', 'user_id' => $admin->id]);
    }

    /** إضافة سؤال — و«رقميّ» تُشتَقّ من الإجابة لا من إدخال يدويّ. */
    public function test_saving_a_question_derives_the_numeric_flag(): void
    {
        $admin = $this->warAdmin();

        $this->actingAs($admin)->post(route('admin.wars.bank.save'), [
            'text' => 'كم عدد أيّام الأسبوع؟',
            'answer' => '7',
            'difficulty' => 'easy',
            'source' => 'arena',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('war_questions', [
            'text' => 'كم عدد أيّام الأسبوع؟',
            'is_numeric' => true,
            'status' => 'active',
        ]);
    }

    public function test_bulk_archive_moves_selected_questions(): void
    {
        $admin = $this->warAdmin();
        $ids = WarQuestion::query()->limit(3)->pluck('id')->all();

        $this->actingAs($admin)
            ->post(route('admin.wars.bank.bulk'), ['ids' => $ids, 'action' => 'archive'])
            ->assertRedirect();

        $this->assertSame(3, WarQuestion::query()->whereIn('id', $ids)->where('status', 'archived')->count());
    }

    /** الاستيراد يعرض **معاينة الصفوف** قبل الاعتماد، ثمّ يعتمدها (24.2). */
    public function test_csv_import_previews_before_confirming(): void
    {
        $admin = $this->warAdmin();
        $before = WarQuestion::count();

        $csv = "السؤال,الإجابة,الصعوبة,المصدر,الاختيارات\n"
            ."كم عدد أشهر السنة؟,12,easy,arena,\n"
            ."عاصمة مصر؟,0,easy,arena,القاهرة|الإسكندرية\n";

        $file = UploadedFile::fake()->createWithContent('bank.csv', $csv);

        $this->actingAs($admin)
            ->post(route('admin.wars.bank.import'), ['file' => $file])
            ->assertSessionHas('import_preview');

        $this->assertSame($before, WarQuestion::count());

        $this->actingAs($admin)->post(route('admin.wars.bank.import'), [
            'file' => UploadedFile::fake()->createWithContent('bank.csv', $csv),
            'confirm' => 1,
        ])->assertRedirect();

        $this->assertSame($before + 2, WarQuestion::count());
        $this->assertDatabaseHas('war_questions', ['text' => 'كم عدد أشهر السنة؟', 'is_numeric' => true]);
    }

    /** صفّ به خطأ ⟵ **لا يُضاف شيء** ورسالة تحدّد رقم الصفّ (24.2). */
    public function test_a_bad_row_aborts_the_whole_import(): void
    {
        $admin = $this->warAdmin();
        $before = WarQuestion::count();

        $file = UploadedFile::fake()->createWithContent('bank.csv', "سؤال سليم,5,easy,arena,\n,9,easy,arena,\n");

        $this->actingAs($admin)
            ->post(route('admin.wars.bank.import'), ['file' => $file, 'confirm' => 1])
            ->assertRedirect();

        $this->assertSame($before, WarQuestion::count());
    }

    public function test_export_streams_a_csv(): void
    {
        $admin = $this->warAdmin();

        $this->actingAs($admin)
            ->get(route('admin.wars.bank.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    /** بلا صلاحيّة البنك: المسار محظور تمامًا (12.2.1). */
    public function test_bank_requires_permission(): void
    {
        $trainee = $this->trainee();

        $this->actingAs($trainee)->get(route('admin.wars.bank.index'))->assertForbidden();
        $this->actingAs($trainee)->post(route('admin.wars.bank.save'), ['text' => 'x'])->assertForbidden();
        $this->actingAs($trainee)->get(route('admin.wars.bank.export'))->assertForbidden();
    }

    // ---------------------------------------------------------------- الألعاب

    /** ⭐ تاب «الألعاب» موجود في لوحة التلعيب بمحتواه (24.2). */
    public function test_games_tab_exists_in_the_gamification_panel(): void
    {
        $admin = $this->warAdmin();

        $this->actingAs($admin)
            ->get(route('admin.gamification.index', ['tab' => 'games']))
            ->assertOk()
            ->assertSee('كتالوج الألعاب', false)
            ->assertSee('سجلّ الجلسات', false)
            ->assertSee('مطابقة الذاكرة', false)
            ->assertSee('إعدادات قسم الألعاب', false);
    }

    public function test_gamification_panel_lists_the_games_tab_among_its_tabs(): void
    {
        $tabs = GamificationController::TABS;

        $this->assertArrayHasKey('games', $tabs);
        $this->assertSame('الألعاب', $tabs['games']);
        // التاب الجديد يُضاف بجوار الحروب لا بدلًا منها
        $this->assertArrayHasKey('wars', $tabs);
    }

    public function test_admin_can_add_a_game_and_change_its_settings(): void
    {
        $admin = $this->warAdmin();

        $this->actingAs($admin)->post(route('admin.gamification.games.save'), [
            'key' => 'sudoku',
            'name_ar' => 'سودوكو',
            'ticket_cost' => 2,
            'xp_reward' => 150,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('games', ['key' => 'sudoku', 'ticket_cost' => 2, 'xp_reward' => 150]);

        $this->actingAs($admin)->post(route('admin.gamification.games.settings.save'), [
            'settings' => ['games.daily_xp_cap' => '500'],
        ])->assertRedirect();

        $this->assertSame(500, (int) setting('games.daily_xp_cap'));

        // Reset يرجّع الافتراضيّ المعتمَد من كتالوج المجال نفسه (2.13)
        $this->actingAs($admin)->post(route('admin.gamification.games.reset'))->assertRedirect();

        $this->assertSame(300, (int) setting('games.daily_xp_cap'));
    }

    public function test_games_admin_actions_require_permission(): void
    {
        $stranger = User::create([
            'name' => 'زائر', 'email' => 'nogames@test.local', 'password' => 'secret-password',
            'code' => 'NOGAMES1', 'status' => 'active',
        ]);

        $this->actingAs($stranger)
            ->post(route('admin.gamification.games.save'), ['key' => 'x', 'name_ar' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseMissing('games', ['key' => 'x']);
        $this->assertSame(3, Game::query()->count());
    }
}
