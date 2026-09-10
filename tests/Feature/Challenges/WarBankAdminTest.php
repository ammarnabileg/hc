<?php

namespace Tests\Feature\Challenges;

use App\Http\Controllers\Admin\GamificationController;
use App\Models\Permission;
use App\Models\WarQuestion;
use App\Services\Admin\Volunteer\SettingsCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

/**
 * بنك أسئلة الحروب (12.10-ب · 24.2).
 *
 * ⛔ ومعه حارس **إلغاء الألعاب** (7.5 — قرار المالك، الدستور v5.3): البند
 * ملغًى فلا تابّ له ولا مسار ولا مفتاح ولا جدول. والحارس يقيس **غياب الباب**
 * لا سلوك بابٍ موجود — فعودةُ أيّ منها تُسقِطه.
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

    /** ⭐ 24.2: بحثٌ بلا نتائج يقول كده صراحةً بدل «البنك فارغ — الحروب لن تعمل». */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        $this->assertGreaterThan(0, WarQuestion::query()->count(), 'لازم يكون في أسئلة فعليّة قبل الاختبار');
        $admin = $this->warAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.wars.bank.index', ['q' => 'zzzznotexist']))
            ->assertOk();

        $response->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $response->assertDontSee(
            setting('admin.wars.bank.index.albnk_fargh_alhrwb_ln_taml', 'البنك فارغ — الحروب لن تعمل.'),
            false,
        );
    }

    /** وبنك الأسئلة الفارغ فعليًّا (بلا فلتر ولا أسئلة) يفضل يعرض رسالة البداية الأصليّة. */
    public function test_actually_empty_without_filters_keeps_the_original_start_message(): void
    {
        WarQuestion::query()->delete();
        $admin = $this->warAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.wars.bank.index'))
            ->assertOk();

        $response->assertSee(
            setting('admin.wars.bank.index.albnk_fargh_alhrwb_ln_taml', 'البنك فارغ — الحروب لن تعمل.'),
            false,
        );
        $response->assertDontSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
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

    /**
     * ⭐ الأعمدة والفلاتر الزائدة عن حدّ 2.15 (5-7 عمودًا · 3 فلاتر ظاهرة)
     * تظهر فقط في «وضع متقدّم» — في المبسّط الجدول محدود بحدّه الافتراضيّ
     * والباقي خلف «فلاتر متقدّمة» لا حذفًا (2.15-أ-4 · 2.15-أ-5).
     */
    public function test_extra_columns_and_filters_are_gated_behind_advanced_mode(): void
    {
        $admin = $this->warAdmin();

        $simple = $this->actingAs($admin)->get(route('admin.wars.bank.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-columns-cap="', $simple, 'حدّ الأعمدة غائب في المبسّط.');
        $this->assertStringContainsString('data-filters-cap="', $simple, 'حدّ الفلاتر غائب في المبسّط.');
        $this->assertStringContainsString(setting('ux.filters.text_1', 'فلاتر متقدّمة'), $simple, 'إفصاح «فلاتر متقدّمة» غائب.');

        $admin->forceFill(['simple_mode' => false, 'advanced_mode' => true])->save();

        $advanced = $this->actingAs($admin->fresh())->get(route('admin.wars.bank.index'))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('data-columns-cap="', $advanced, 'حدّ الأعمدة باقٍ رغم الوضع المتقدّم.');
        $this->assertStringNotContainsString('data-filters-cap="', $advanced, 'حدّ الفلاتر باقٍ رغم الوضع المتقدّم.');
        $this->assertStringContainsString('data-filters-mode="advanced"', $advanced);
    }

    /** بلا صلاحيّة البنك: المسار محظور تمامًا (12.2.1). */
    public function test_bank_requires_permission(): void
    {
        $trainee = $this->trainee();

        $this->actingAs($trainee)->get(route('admin.wars.bank.index'))->assertForbidden();
        $this->actingAs($trainee)->post(route('admin.wars.bank.save'), ['text' => 'x'])->assertForbidden();
        $this->actingAs($trainee)->get(route('admin.wars.bank.export'))->assertForbidden();
    }

    // -------------------------------------------------- ⛔ الألعاب ملغاة (7.5)

    /**
     * ⭐ الإلغاء يُقاس بغياب الباب لا بإغلاقه: لا تابّ في لوحة التلعيب، ولا
     * مسار، ولا مفتاح صلاحيّة، ولا جدول. وأيّ عودةٍ لواحدٍ منها تُسقِط الحارس.
     */
    public function test_the_cancelled_games_section_left_no_door_behind(): void
    {
        // (١) لا تابّ — لا في القائمة ولا بالمحاولة المباشرة
        $this->assertNotContains('games', GamificationController::TAB_KEYS);

        $this->actingAs($this->warAdmin())
            ->get(route('admin.gamification.index', ['tab' => 'games']))
            ->assertNotFound();

        // والحروب باقيةٌ — الملغى قسم الألعاب وحده لا التلعيب كلّه
        $this->assertContains('wars', GamificationController::TAB_KEYS);

        // (٢) لا مسار يحمل الاسم — لا للمتدرّب ولا للأدمن
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($route) => (string) $route->getName())
            ->filter()
            ->values();

        foreach (['achievements.games', 'admin.gamification.games.save'] as $name) {
            $this->assertNotContains($name, $routes);
        }

        $this->assertSame([], $routes->filter(fn ($n) => str_contains($n, 'games'))->all());

        // (٣) لا مفتاح صلاحيّة بالمورد الملغى
        $this->assertSame(0, Permission::query()->where('resource', 'games')->count());

        // (٤) ولا جدول — الهجرة أسقطته على كلّ تنصيب
        $this->assertFalse(Schema::hasTable('games'));
        $this->assertFalse(Schema::hasTable('game_sessions'));
    }

    /**
     * ولا وجهَ صرفٍ باسم «دخول لعبة» في اقتصاد التذاكر (7.1 بعد الإلغاء).
     *
     * ويُقاس على **الافتراضيّ المزروع في مسار الإنتاج** (كتالوج الإعدادات) لا
     * على صفٍّ في القاعدة: الصفّ قد يكون معدَّلًا بيد المالك، أمّا الكتالوج
     * فهو ما يُشحَن ويُرجِعه زرّ الـReset — فهو موضع القاعدة لا الأثر.
     */
    public function test_the_ticket_economy_no_longer_sells_a_game_entry(): void
    {
        $default = SettingsCatalog::defaultOf('xp_rules.spend');

        $keys = array_column(json_decode((string) $default, true) ?: [], 'key');

        $this->assertNotEmpty($keys, 'كتالوج أوجه الصرف فاضي — الاختبار مايقيسش حاجة');
        $this->assertNotContains('game.enter', $keys);

        // وحروب التركيز باقيةٌ في أوجه الصرف — الإلغاء لم يمسّها
        $this->assertContains('war.focus.create', $keys);
        $this->assertContains('war.join', $keys);
    }
}
