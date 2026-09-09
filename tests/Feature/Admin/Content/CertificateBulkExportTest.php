<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Event;
use App\Models\Setting;
use App\Services\Certificates\CertificateIssuer;
use Illuminate\Support\Facades\Cache;
use ZipArchive;

/**
 * سجلّ الصادر — «تصدير/طباعة جماعيّة» (24.1 سطر 4676): كانت وصلة CSV مباشرة
 * بفلاتر الصفحة فقط — لا بوب-أب، ولا اختيار «بأكواد الأشخاص»، ولا **الصيغة**،
 * وتصدّر **صفحة العرض (20) لا كلّ ما طابق** لأنّها كانت تستهلك السجلّ المُصفَّح.
 */
class CertificateBulkExportTest extends AdminContentTestCase
{
    private function issueCertificate(string $userName, ?Event $event = null): Certificate
    {
        $user = $this->makeUser(['name' => $userName]);

        return app(CertificateIssuer::class)->issue($user, 'course', $event);
    }

    // ------------------------------------------------------------ الصيغة والحدّ الحقيقيّ

    /** ⭐ التصدير كان يستهلك السجلّ المُصفَّح (20/صفحة) — الآن كلّ ما طابق الفلاتر. */
    public function test_export_is_not_capped_by_the_ledgers_page_size(): void
    {
        Setting::updateOrCreate(['key' => 'certificates.ledger.per_page'], ['group' => 'certificates', 'label_ar' => 'x', 'type' => 'number', 'value' => '2']);
        Cache::forget('settings');

        foreach (range(1, 3) as $i) {
            $this->issueCertificate('صاحب '.$i);
        }

        $csv = $this->actingAs($this->admin())->get(route('admin.certificates.export'))->assertOk()->streamedContent();

        $this->assertSame(3, substr_count($csv, 'صاحب '));
    }

    public function test_row_limit_caps_the_export(): void
    {
        Setting::updateOrCreate(['key' => 'certificates.export.row_limit'], ['group' => 'certificates', 'label_ar' => 'x', 'type' => 'number', 'value' => '2']);
        Cache::forget('settings');

        foreach (range(1, 4) as $i) {
            $this->issueCertificate('محدود '.$i);
        }

        $csv = $this->actingAs($this->admin())->get(route('admin.certificates.export'))->assertOk()->streamedContent();

        $this->assertSame(2, substr_count($csv, 'محدود '));
    }

    public function test_images_format_returns_a_zip_with_one_png_per_certificate(): void
    {
        $one = $this->issueCertificate('هالة');
        $two = $this->issueCertificate('كريم');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.export', ['format' => 'images']))
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');

        $path = tempnam(sys_get_temp_dir(), 'zip-test-');
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive;
        $zip->open($path);

        $this->assertSame(2, $zip->numFiles);
        $this->assertNotFalse($zip->locateName($one->code.'.png'));
        $this->assertNotFalse($zip->locateName($two->code.'.png'));

        $zip->close();
        unlink($path);
    }

    // ------------------------------------------------------------ بالفعاليّة / بأكواد الأشخاص («أو» حرفيّة)

    public function test_filtering_by_event_only_exports_that_events_certificates(): void
    {
        $event = Event::create(['slug' => 'event-'.str()->random(6), 'title_ar' => 'فعاليّة الاختبار', 'starts_at' => now()]);
        $matching = $this->issueCertificate('حضر الفعاليّة', $event);
        $this->issueCertificate('حضر تدريبًا آخر');

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.certificates.export', ['mode' => 'filters', 'event_id' => $event->id]))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString($matching->code, $csv);
        $this->assertStringNotContainsString('حضر تدريبًا آخر', $csv);
    }

    public function test_filtering_by_person_codes_ignores_the_type_and_event_filters(): void
    {
        $wantedUser = $this->makeUser(['name' => 'مطلوب']);
        $wanted = app(CertificateIssuer::class)->issue($wantedUser, 'course');
        $this->issueCertificate('غير مطلوب');

        // النوع مُرسَلٌ أيضًا في نفس الطلب — ويجب أن يُتجاهَل لأنّ mode=codes
        $csv = $this->actingAs($this->admin())
            ->get(route('admin.certificates.export', [
                'mode' => 'codes',
                'codes' => $wantedUser->code,
                'type' => CertificateType::query()->where('key', 'course')->value('id') + 999999,
            ]))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString($wanted->code, $csv);
        $this->assertStringNotContainsString('غير مطلوب', $csv);
    }

    // ------------------------------------------------------------ Toggle «إتاحة التصدير الجماعيّ» (2.15-أ-7)

    public function test_the_toggle_hides_the_button_and_blocks_the_route_when_off(): void
    {
        Setting::updateOrCreate(['key' => 'certificates.export.bulk_enabled'], ['group' => 'certificates', 'label_ar' => 'x', 'type' => 'bool', 'value' => '0']);
        Cache::forget('settings');

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.certificates.index', ['tab' => 'ledger']))
            ->assertOk()
            ->assertDontSee(route('admin.certificates.export'), false);

        $this->actingAs($admin)->get(route('admin.certificates.export'))->assertForbidden();
    }

    public function test_the_button_shows_when_the_toggle_is_on(): void
    {
        $this->actingAs($this->admin())->get(route('admin.certificates.index', ['tab' => 'ledger']))
            ->assertOk()
            ->assertSee(route('admin.certificates.export'), false);
    }
}
