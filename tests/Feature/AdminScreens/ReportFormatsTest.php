<?php

namespace Tests\Feature\AdminScreens;

use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleRun;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * صيغتا Excel وPDF ورابط التنزيل المؤقّت (24.3-خامسًا).
 *
 * الاختبار هنا لا يكتفي بأنّ «المرفق موجود»: يفكّ حزمة الـXLSX ويقرأ نصوصها،
 * ويستخرج نصّ الـPDF من طبقة `ToUnicode` كما يفعل أيّ قارئ — لأنّ الوعد كان
 * **صيغةً حقيقيّة بعربيّة سليمة**، ووعدٌ لا يُختبَر ليس منفَّذًا.
 */
class ReportFormatsTest extends ScreensTestCase
{
    /** Excel حقيقيّ: حزمة ZIP فيها XML، والعربيّة سليمة في نصوصه المشتركة */
    public function test_excel_format_builds_a_real_workbook_with_arabic(): void
    {
        Mail::fake();

        $schedule = $this->scheduleWith(['format' => 'xlsx']);

        $this->actingAs($this->owner())->post(route('admin.report-schedules.run', $schedule))->assertRedirect();

        $file = $this->sentFile();

        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $file['mime']);
        $this->assertStringEndsWith('.xlsx', $file['name']);

        $parts = $this->unzip($file['content']);

        // الملفّات الإلزاميّة في مواصفة OOXML — غياب أيّها يجعل إكسل يرفض الملفّ
        foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/worksheets/sheet1.xml', 'xl/sharedStrings.xml'] as $part) {
            $this->assertArrayHasKey($part, $parts);
        }

        $this->assertStringContainsString('<sst', $parts['xl/sharedStrings.xml']);
        $this->assertStringContainsString('اليوم', $parts['xl/sharedStrings.xml']);
        // الورقة عربيّة الاتّجاه — تفصيلة صغيرة تفرق كثيرًا عند الفتح
        $this->assertStringContainsString('rightToLeft="1"', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('تقرير أسبوعيّ', $parts['xl/workbook.xml']);
    }

    /** PDF بطبقة نصّ حقيقيّة: العربيّة تُستخرَج سليمةً لا كصورة ولا كمربّعات */
    public function test_pdf_format_builds_extractable_arabic_text(): void
    {
        Mail::fake();

        $schedule = $this->scheduleWith(['format' => 'pdf']);

        $this->actingAs($this->owner())->post(route('admin.report-schedules.run', $schedule))->assertRedirect();

        $file = $this->sentFile();

        $this->assertSame('application/pdf', $file['mime']);
        $this->assertStringStartsWith('%PDF-', $file['content']);

        $text = $this->pdfText($file['content']);

        $this->assertStringContainsString('تقرير أسبوعيّ', $text);
        $this->assertStringContainsString('اليوم', $text);
        // ولا مربّعات: كلّ شكلٍ رُسِم له مقابلٌ منطقيّ في `ToUnicode`
        $this->assertDoesNotMatchRegularExpression('/\x{FFFD}/u', $text);
    }

    /** الصيغة غير المعروفة ترجع CSV بلا انفجار — والمرفق يصل مهما حدث */
    public function test_unknown_format_falls_back_to_csv(): void
    {
        Mail::fake();

        $schedule = $this->scheduleWith(['format' => 'csv']);

        $this->actingAs($this->owner())->post(route('admin.report-schedules.run', $schedule))->assertRedirect();

        $file = $this->sentFile();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $file['content']);
        $this->assertStringContainsString('اليوم', $file['content']);
    }

    // ------------------------------------------------------ رابط التنزيل المؤقّت

    /** فوق حدّ المرفق: بريد بلا مرفق **ومعه رابط موقَّع يشتغل فعلًا** */
    public function test_oversized_report_is_sent_as_a_signed_temporary_link(): void
    {
        Mail::fake();

        $schedule = $this->oversizedSchedule();

        $this->actingAs($this->owner())->post(route('admin.report-schedules.run', $schedule))->assertRedirect();

        $mail = $this->sentMail();

        $this->assertNull($mail->file, 'الملفّ فوق الحدّ لازم يخرج كرابط لا كمرفق.');

        $run = ReportScheduleRun::query()->latest('id')->firstOrFail();

        $this->assertNotNull($run->download_token);
        $this->assertTrue($run->hasLiveDownload());
        $this->assertStringContainsString($run->download_token, $mail->bodyText);
        $this->assertStringContainsString('signature=', $mail->bodyText);

        // والرابط الذي وصل في البريد يفتح الملفّ فعلًا
        $this->get($this->linkIn($mail->bodyText))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** التوقيع هو الصلاحيّة: بلا توقيع لا تنزيل، ورمزٌ مخترع لا يفتح شيئًا */
    public function test_download_needs_a_valid_signature_and_a_live_token(): void
    {
        Mail::fake();

        $this->actingAs($this->owner())->post(route('admin.report-schedules.run', $this->oversizedSchedule()))->assertRedirect();

        $run = ReportScheduleRun::query()->latest('id')->firstOrFail();

        // بلا توقيع
        $this->get(route('reports.download', ['token' => $run->download_token]))->assertForbidden();

        // توقيع لرمزٍ لا وجود له
        $this->get(URL::temporarySignedRoute('reports.download', now()->addHour(), ['token' => 'not-a-real-token']))
            ->assertNotFound();

        // ومدّة انتهت: الرابط الموقَّع نفسه يُرفَض بعد وقته
        $expired = URL::temporarySignedRoute('reports.download', now()->addHour(), ['token' => $run->download_token]);

        $this->travel(2)->hours();

        $this->get($expired)->assertForbidden();
    }

    /** الرابط المنتهي = ملفٌّ يُمسَح لا رابطٌ يُرفَض فقط */
    public function test_expired_links_are_pruned_with_their_files(): void
    {
        Mail::fake();

        $this->actingAs($this->owner())->post(route('admin.report-schedules.run', $this->oversizedSchedule()))->assertRedirect();

        $run = ReportScheduleRun::query()->latest('id')->firstOrFail();
        $path = $run->download_path;

        $this->assertTrue(Storage::disk('local')->exists($path));

        $run->forceFill(['download_expires_at' => now()->subMinute()])->save();

        $this->artisan('reports:dispatch')->assertSuccessful();

        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertNull($run->refresh()->download_token);
    }

    /** 🔒 التقرير الماليّ لا يفتحه إلّا مالك المنصّة — والتوقيع وحده لا يكفي */
    public function test_financial_download_is_platform_owner_only(): void
    {
        Mail::fake();

        $schedule = $this->oversizedSchedule([
            'name' => 'تقرير المبيعات',
            'report_tab' => 'sales',
            'is_financial' => true,
        ]);

        $this->actingAs($this->owner())->post(route('admin.report-schedules.run', $schedule))->assertRedirect();

        $url = $this->linkIn($this->sentMail()->bodyText);

        // زائر بالرابط الصحيح — ممنوع
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get($url)->assertForbidden();

        // وأدمن كامل الصلاحيّات لكنّه ليس مالك المنصّة — ممنوع كذلك
        $admin = $this->admin(['report_schedules.list', 'report_schedules.view', 'report_schedules.manage']);
        $this->actingAs($admin)->get($url)->assertForbidden();

        $this->actingAs($this->owner())->get($url)->assertOk();
    }

    // ------------------------------------------------------------------ أدوات

    private function scheduleWith(array $attributes): ReportSchedule
    {
        $schedule = ReportSchedule::query()->where('report_tab', 'users')->firstOrFail();

        $schedule->forceFill($attributes + [
            'name' => 'تقرير أسبوعيّ',
            'skip_when_empty' => false,
            'recipient_emails' => ['boss@test.local'],
        ])->save();

        return $schedule->refresh();
    }

    /** جدولة يتجاوز ملفّها حدّ المرفق: مدى طويل + حدّ صغير */
    private function oversizedSchedule(array $attributes = []): ReportSchedule
    {
        $this->setSetting('report_schedules.max_attachment_kb', '1', 'number');

        return $this->scheduleWith($attributes + ['format' => 'csv', 'period_days' => 300]);
    }

    private function sentMail(): ScheduledReportMail
    {
        $captured = null;

        Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail) use (&$captured) {
            $captured = $mail;

            return true;
        });

        return $captured;
    }

    /** @return array{name:string,mime:string,content:string} */
    private function sentFile(): array
    {
        $file = $this->sentMail()->file;

        $this->assertNotNull($file, 'المفروض المرفق يتبعت مع الرسالة.');

        return $file;
    }

    private function linkIn(string $body): string
    {
        preg_match('#https?://\S+#', $body, $matches);

        $this->assertNotEmpty($matches, 'نصّ الرسالة لازم يحمل رابط التنزيل.');

        return rtrim($matches[0], '.');
    }

    private function setSetting(string $key, string $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => 'stats',
            'label_ar' => $key,
            'type' => $type,
            'default_value' => $value,
            'value' => $value,
        ]);

        Cache::forget('settings');
    }

    /**
     * فكّ حزمة ZIP بالمواصفة مباشرةً — بلا `ZipArchive` كي لا يعتمد الاختبار
     * على امتدادٍ قد يغيب، ولأنّ ما نختبره هو الحاوية التي كتبناها بأيدينا.
     *
     * @return array<string,string>
     */
    private function unzip(string $binary): array
    {
        $files = [];
        $offset = 0;

        while (substr($binary, $offset, 4) === "PK\x03\x04") {
            $header = unpack(
                'vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vplain/vnamelen/vextralen',
                substr($binary, $offset + 4, 26),
            );

            $name = substr($binary, $offset + 30, $header['namelen']);
            $start = $offset + 30 + $header['namelen'] + $header['extralen'];
            $data = substr($binary, $start, $header['compressed']);

            $plain = $header['method'] === 8 ? (string) gzinflate($data) : $data;

            $this->assertSame($header['crc'], crc32($plain), 'بصمة CRC لازم تطابق المحتوى في '.$name);

            $files[$name] = $plain;
            $offset = $start + $header['compressed'];
        }

        return $files;
    }

    /**
     * استخراج نصّ الـPDF كما يفعل قارئٌ أو محلّل ATS: نقرأ خريطة `ToUnicode`
     * (شكل ⟵ حرف منطقيّ) ثمّ نمرّ على أوامر الرسم `<gid> Tj` بترتيب التدفّق.
     */
    private function pdfText(string $pdf): string
    {
        $map = [];

        if (preg_match_all('/<([0-9A-Fa-f]{4})>\s*<([0-9A-Fa-f]+)>/', $pdf, $entries, PREG_SET_ORDER)) {
            foreach ($entries as $entry) {
                $chars = '';

                foreach (str_split($entry[2], 4) as $unit) {
                    $chars .= mb_chr((int) hexdec($unit), 'UTF-8');
                }

                $map[strtoupper($entry[1])] = $chars;
            }
        }

        $text = '';

        if (preg_match_all('/<([0-9A-Fa-f]{4})>\s*Tj/', $pdf, $glyphs, PREG_SET_ORDER)) {
            foreach ($glyphs as $glyph) {
                $text .= $map[strtoupper($glyph[1])] ?? '';
            }
        }

        return $text;
    }
}
