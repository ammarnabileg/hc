<?php

namespace Tests\Feature\Admin\System;

use App\Models\Setting;
use App\Services\Export\TabularExport;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ «**تصدير CSV/Excel/PDF**» (24.3-خامسًا · 12.8) — والزرّ الذي يَعِد بصيغةٍ
 * **يجب أن يُخرِجها**. كان الهيدر يعرض «تصدير CSV» وحده والنصّ يوجب ثلاثًا؛
 * فالاختبار هنا لا يقف عند الامتداد، بل **يفتح الملفّ ويقرأ بنيته**:
 *  · XLSX ⟵ حزمة ZIP بمواصفة OOXML فيها `xl/workbook.xml` و`sharedStrings`.
 *  · PDF  ⟵ ترويسة `%PDF` وخطّ `CIDFontType2` بطبقة `ToUnicode` (نصّ لا صورة).
 */
class AdminStatsExportFormatsTest extends SystemTestCase
{
    private function statsAdmin()
    {
        return $this->admin(['reports_users.view', 'reports_users.export'], 'أدمن التقارير');
    }

    private function download(string $format)
    {
        return $this->actingAs($this->statsAdmin())
            ->get(route('admin.stats.export', ['tab' => 'users', 'format' => $format]));
    }

    public function test_csv_export_is_utf8_with_bom(): void
    {
        $response = $this->download('csv');

        $response->assertOk();
        $response->assertHeader('X-Export-Format', 'csv');
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->getContent());
    }

    /** ⭐ ملفّ Excel **حقيقيّ**: نفتحه حزمةً ونقرأ أجزاء OOXML بداخله */
    public function test_excel_export_is_a_real_xlsx_package(): void
    {
        $response = $this->download('xlsx');

        $response->assertOk();
        $response->assertHeader('X-Export-Format', 'xlsx');
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );

        $content = $response->getContent();
        $this->assertStringStartsWith('PK', $content, 'XLSX حزمة ZIP — وبادئتها PK');

        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($path, $content);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'الحزمة تُفتَح بقارئ ZIP قياسيّ');

        foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/worksheets/sheet1.xml', 'xl/sharedStrings.xml'] as $part) {
            $this->assertNotFalse($zip->locateName($part), "الجزء {$part} موجود في الحزمة");
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('<worksheet', $sheet);
        // ورقة عربيّة: الاتّجاه من اليمين
        $this->assertStringContainsString('rightToLeft="1"', $sheet);

        $zip->close();
        @unlink($path);
    }

    /** ⭐ ملفّ PDF **حقيقيّ** بطبقة نصّ عربيّ تُستخرَج — لا صورة ولا `window.print()` */
    public function test_pdf_export_is_a_real_pdf_with_an_extractable_text_layer(): void
    {
        $response = $this->download('pdf');

        $response->assertOk();
        $response->assertHeader('X-Export-Format', 'pdf');
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('%%EOF', $content);
        // الخطّ العربيّ مضمَّن، والحروف مربوطة بحروفها المنطقيّة
        $this->assertStringContainsString('CIDFontType2', $content);
        $this->assertStringContainsString('/ToUnicode', $content);
        $this->assertStringContainsString('/FontFile2', $content);
    }

    /** صيغةٌ لا يعرفها المصدِّر لا تُخرِج ملفًّا مكسورًا — ترجع CSV بصراحة */
    public function test_unknown_format_falls_back_to_csv(): void
    {
        $response = $this->download('docx');

        $response->assertOk();
        $response->assertHeader('X-Export-Format', 'csv');
    }

    /** الوعد لا يُكسَر بصمت: تعذّر الصيغة يخرج CSV **ومعه سببه** (2.17-ب) */
    public function test_pdf_without_the_embedded_font_says_why_it_fell_back(): void
    {
        $this->setSettingValue('cv.ats.font_path', 'fonts/does-not-exist.ttf');
        $this->setSettingValue('images.font.path', 'fonts/does-not-exist.ttf');

        $file = app(TabularExport::class)->build([['أ' => 1]], 'pdf', 'تجربة');

        $this->assertSame('csv', $file['format']);
        $this->assertNotSame('', $file['note']);
    }

    private function setSettingValue(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['group' => 'cv', 'label_ar' => $key, 'type' => 'string', 'value' => $value],
        );

        Cache::forget('settings');
    }
}
