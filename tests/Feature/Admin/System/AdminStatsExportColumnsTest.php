<?php

namespace Tests\Feature\Admin\System;

use App\Models\User;
use App\Services\Admin\System\StatsService;
use Illuminate\Support\Facades\DB;

/**
 * ⭐⭐ بوب-أب **[تصدير]** — «**[تصدير]** الصيغة + **الأعمدة المختارة** + الفترة +
 * Toggle «ضمّ المقارنة»» (24.3-خامسًا).
 *
 * كانت الشاشة ثلاثة **روابط تصديرٍ مباشرة** بلا أيّ خطوة اختيار أعمدة: بندٌ من
 * أربعةٍ يقع (الصيغة) وثلاثةٌ لا. والاختبار هنا لا يقف عند ظهور مربّعات الاختيار
 * في الصفحة — **يفتح الملفّ ويقرأ صفّ عناوينه**: العمود غير المعلَّم يجب ألّا
 * يكون فيه، وToggle المقارنة يجب أن **يضيف عمودًا بقيمه** لا علامةً في الرابط.
 */
class AdminStatsExportColumnsTest extends SystemTestCase
{
    private const STATS_ADMIN = ['reports_users.view', 'reports_users.export', 'acquisition_sources.view'];

    private function statsAdmin(): User
    {
        return $this->admin(self::STATS_ADMIN, 'أدمن التقارير');
    }

    /**
     * صفّ عناوين ملفّ الـCSV — بعد نزع الـBOM.
     *
     * @return array<int, string>
     */
    private function csvHeader(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $first = strtok($content, "\n");

        return $first === false ? [] : array_map('trim', str_getcsv($first, ',', '"', '\\'));
    }

    /** @return array<int, array<int, string>> */
    private function csvRows(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $content)), fn ($l) => trim($l) !== ''));

        return array_map(fn ($line) => array_map('trim', str_getcsv($line, ',', '"', '\\')), array_slice($lines, 1));
    }

    // ------------------------------------------------------------------ الشاشة

    /** ⭐ البوب-أب موجود ببنوده الأربعة — لا ثلاثة روابط تصديرٍ عارية */
    public function test_the_export_popup_offers_format_columns_period_and_compare(): void
    {
        $html = $this->actingAs($this->statsAdmin())
            ->get(route('admin.stats.index', ['tab' => 'users']))
            ->assertOk()
            ->getContent();

        // الصيغة
        $this->assertStringContainsString('name="format"', $html);
        // الأعمدة المختارة — مربّع لكلّ عمود بمفتاحه
        $this->assertStringContainsString('الأعمدة المختارة', $html);
        $this->assertStringContainsString('name="columns[]" value="day"', $html);
        $this->assertStringContainsString('name="columns[]" value="registered"', $html);
        // الفترة + Toggle «ضمّ المقارنة»
        $this->assertStringContainsString('name="from"', $html);
        $this->assertStringContainsString('name="to"', $html);
        $this->assertStringContainsString('ضمّ المقارنة', $html);
    }

    /** قائمة أعمدة البوب-أب هي **قائمة التصدير نفسها** — لا نسخة ثانية تتقادم */
    public function test_the_popup_column_list_comes_from_the_exporter_itself(): void
    {
        $columns = app(StatsService::class)->exportColumns('acquisition');

        $html = $this->actingAs($this->statsAdmin())
            ->get(route('admin.stats.index', ['tab' => 'acquisition']))
            ->assertOk()
            ->getContent();

        foreach (array_keys($columns) as $key) {
            $this->assertStringContainsString('name="columns[]" value="'.$key.'"', $html);
        }
    }

    // ------------------------------------------------------------------ الملفّ

    /** ⭐⭐ العمود غير المعلَّم **لا يخرج في الملفّ** — والدليل صفّ العناوين */
    public function test_the_file_carries_only_the_selected_columns(): void
    {
        $response = $this->actingAs($this->statsAdmin())->get(route('admin.stats.export', [
            'tab' => 'users',
            'format' => 'csv',
            'columns' => ['registered'],
        ]));

        $response->assertOk();
        $header = $this->csvHeader($response->getContent());

        $this->assertSame(['تسجيلات'], $header, 'العمود المختار وحده في صفّ العناوين');
        $this->assertNotContains('اليوم', $header, 'العمود غير المعلَّم لا يخرج في الملفّ');
    }

    /** ترتيب الأعمدة ترتيبُ القائمة لا ترتيبُ ما وصل في الرابط */
    public function test_column_order_follows_the_catalogue_not_the_request(): void
    {
        $response = $this->actingAs($this->statsAdmin())->get(route('admin.stats.export', [
            'tab' => 'users',
            'format' => 'csv',
            'columns' => ['registered', 'day'],
        ]));

        $response->assertOk();

        $this->assertSame(['اليوم', 'تسجيلات'], $this->csvHeader($response->getContent()));
    }

    /** رابطٌ بلا `columns[]` (قديمٌ أو مجدول) يظلّ يُخرِج الجدول كاملًا */
    public function test_an_export_without_a_selection_keeps_every_column(): void
    {
        $response = $this->actingAs($this->statsAdmin())
            ->get(route('admin.stats.export', ['tab' => 'users', 'format' => 'csv']));

        $response->assertOk();

        $this->assertSame(['اليوم', 'تسجيلات'], $this->csvHeader($response->getContent()));
    }

    /** مفتاحٌ ليس من القائمة يُهمَل — ولا يُهرَّب عمودًا لم يُعرَض للاختيار */
    public function test_a_column_key_outside_the_catalogue_is_ignored(): void
    {
        $response = $this->actingAs($this->statsAdmin())->get(route('admin.stats.export', [
            'tab' => 'users',
            'format' => 'csv',
            'columns' => ['registered', 'password', 'email'],
        ]));

        $response->assertOk();

        $this->assertSame(['تسجيلات'], $this->csvHeader($response->getContent()));
    }

    /**
     * ⭐⭐ Toggle «**ضمّ المقارنة**» — عمودٌ **بقيمه** من الفترة السابقة.
     *
     * عشرة أيّام (11–20 يناير) فترتُها السابقة (1–10 يناير): مستخدمٌ واحد في
     * أوّل يومٍ من الحاليّة، وثلاثةٌ في أوّل يومٍ من السابقة — فالصفّ الأوّل
     * يجب أن يقرأ 1 و**3**، لا 1 وفراغًا.
     */
    public function test_the_compare_toggle_adds_a_previous_period_column_with_real_values(): void
    {
        $admin = $this->statsAdmin();

        $this->registerUsersOn('2026-01-11', 1);
        $this->registerUsersOn('2026-01-01', 3);

        $response = $this->actingAs($admin)->get(route('admin.stats.export', [
            'tab' => 'users',
            'format' => 'csv',
            'from' => '2026-01-11',
            'to' => '2026-01-20',
            'compare' => 1,
        ]));

        $response->assertOk();
        $content = $response->getContent();

        $header = $this->csvHeader($content);
        $this->assertSame(['اليوم', 'تسجيلات', 'الفترة السابقة'], $header);

        $first = $this->csvRows($content)[0] ?? [];
        $this->assertSame('01/11', $first[0] ?? null);
        $this->assertSame(1.0, (float) ($first[1] ?? 0), 'تسجيلات اليوم الأوّل من الفترة');
        $this->assertSame(3.0, (float) ($first[2] ?? 0), 'تسجيلات اليوم المقابل من الفترة السابقة');
    }

    /** بلا Toggle المقارنة لا يظهر العمود أصلًا — الملفّ ما طُلِب لا أكثر */
    public function test_without_the_toggle_there_is_no_comparison_column(): void
    {
        $response = $this->actingAs($this->statsAdmin())->get(route('admin.stats.export', [
            'tab' => 'users',
            'format' => 'csv',
            'from' => '2026-01-11',
            'to' => '2026-01-20',
        ]));

        $response->assertOk();

        $this->assertNotContains('الفترة السابقة', $this->csvHeader($response->getContent()));
    }

    /** المقارنة تُضاف فوق الاختيار — لا تلغيه ولا تُلغى به */
    public function test_the_comparison_column_rides_on_top_of_the_selection(): void
    {
        $response = $this->actingAs($this->statsAdmin())->get(route('admin.stats.export', [
            'tab' => 'users',
            'format' => 'csv',
            'columns' => ['registered'],
            'compare' => 1,
        ]));

        $response->assertOk();

        $this->assertSame(['تسجيلات', 'الفترة السابقة'], $this->csvHeader($response->getContent()));
    }

    /**
     * ⭐ التصفية تقع في **بناء الصفوف** لا في صيغةٍ بعينها — فتصل الصيغ الثلاث
     * كلَّها. والدليل: الصفوف نفسها تخرج مصفّاةً قبل أن يراها المصدِّر، وملفّ
     * Excel لا يحمل لافتة العمود المستبعَد في نصوصه المشتركة.
     */
    public function test_the_selection_is_applied_before_the_writer_so_every_format_gets_it(): void
    {
        $stats = app(StatsService::class);
        $rows = $stats->exportRows('users', $stats->period('2026-01-01', '2026-01-10', false), ['registered']);

        $this->assertNotSame([], $rows);

        foreach ($rows as $row) {
            $this->assertSame(['تسجيلات'], array_keys($row));
        }

        $xlsx = $this->actingAs($this->statsAdmin())->get(route('admin.stats.export', [
            'tab' => 'users',
            'format' => 'xlsx',
            'from' => '2026-01-01',
            'to' => '2026-01-10',
            'columns' => ['registered'],
        ]));

        $xlsx->assertOk();
        $xlsx->assertHeader('X-Export-Format', 'xlsx');

        $path = tempnam(sys_get_temp_dir(), 'stats').'.xlsx';
        file_put_contents($path, $xlsx->getContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $shared = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('تسجيلات', $shared);
        $this->assertStringNotContainsString('اليوم', $shared, 'العمود المستبعَد لا يدخل حزمة Excel أصلًا');
    }

    private function registerUsersOn(string $date, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = $this->makeUser('مسجّل '.$date.'-'.$i);

            DB::table('users')->where('id', $user->id)->update([
                'created_at' => $date.' 09:00:00',
                'updated_at' => $date.' 09:00:00',
            ]);
        }
    }
}
