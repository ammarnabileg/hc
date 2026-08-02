<?php

namespace Tests\Feature\Ui;

use App\Models\Cv;
use App\Services\Library\ArabicShaper;
use App\Services\Library\CvImporter;
use Illuminate\Http\UploadedFile;

/**
 * ما نقص من السيرة الذاتيّة (الدستور 9):
 * استيراد وتحليل CV · تصدير PDF متوافق مع ATS · رابط سيرة عامّ.
 */
class CvExtrasTest extends UiTestCase
{
    private const SAMPLE = <<<'TXT'
    محمد عبد الله
    mohamed@test.local
    +201234567890

    نبذة
    مطوّر واجهات بخبرة خمس سنوات في بناء منصّات تعليميّة.

    الخبرة العملية
    مطوّر واجهات — شركة نور — القاهرة — 2020 حتى الآن
    مطوّر مبتدئ — شركة أمل — الإسكندرية — 2018 — 2020

    التعليم
    بكالوريوس حاسبات — جامعة القاهرة — علوم حاسب — 2014 — 2018

    المهارات
    PHP، Laravel، تحليل بيانات

    اللغات
    العربية — الأم
    الإنجليزية — متقدّم
    TXT;

    // ------------------------------------------------------------ الاستيراد والتحليل

    public function test_uploaded_cv_is_parsed_into_structured_data(): void
    {
        $parsed = app(CvImporter::class)->parse(self::SAMPLE);

        $this->assertSame('mohamed@test.local', $parsed['profile']['email']);
        $this->assertStringContainsString('مطوّر واجهات', $parsed['profile']['summary'] ?? $parsed['profile']['job_title']);
        $this->assertCount(2, $parsed['experience']);
        $this->assertSame('2020', $parsed['experience'][0]['from']);
        $this->assertTrue($parsed['experience'][0]['current']);
        $this->assertCount(1, $parsed['education']);
        $this->assertStringContainsString('Laravel', $parsed['skills']);
        $this->assertCount(2, $parsed['languages']);
    }

    public function test_import_previews_first_and_writes_nothing_before_the_answer(): void
    {
        $user = $this->trainee();

        $response = $this->actingAs($user)->post(route('cv.import'), [
            'file' => UploadedFile::fake()->createWithContent('cv.txt', self::SAMPLE),
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('نبدّل بياناتك بالملفّ ولا نضيف عليها؟', $response->json('question'));

        // ⭐ لا كتابة قبل «تبديل ولا إضافة؟» (9)
        $cv = Cv::where('user_id', $user->id)->first();
        $this->assertTrue($cv === null || empty($cv->data['experience']));
    }

    public function test_replace_mode_overwrites_and_append_mode_adds(): void
    {
        $user = $this->trainee();
        $parsed = app(CvImporter::class)->parse(self::SAMPLE);

        $this->actingAs($user)
            ->postJson(route('cv.import.apply'), ['mode' => 'replace', 'preview' => $parsed])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertCount(2, Cv::where('user_id', $user->id)->first()->data['experience']);

        // الإضافة لا تكرّر الصفوف نفسها
        $this->actingAs($user)
            ->postJson(route('cv.import.apply'), ['mode' => 'append', 'preview' => $parsed])
            ->assertOk();

        $this->assertCount(2, Cv::where('user_id', $user->id)->first()->data['experience']);
    }

    public function test_unsupported_file_says_what_happened_and_what_to_do(): void
    {
        $this->actingAs($this->trainee())
            ->post(route('cv.import'), ['file' => UploadedFile::fake()->create('cv.exe', 4)])
            ->assertStatus(422)
            ->assertJsonFragment(['ok' => false]);
    }

    // ------------------------------------------------------------ الرابط العامّ

    public function test_public_cv_link_opens_without_login_and_closes_on_demand(): void
    {
        $user = $this->trainee(['name' => 'سلمى محمود']);

        $response = $this->actingAs($user)->postJson(route('cv.public.toggle'), ['enabled' => true]);
        $response->assertOk()->assertJson(['enabled' => true]);

        $slug = Cv::where('user_id', $user->id)->first()->public_slug;
        $this->assertNotEmpty($slug);

        // بلا تسجيل دخول — زيّ صفحة الشهادة (9)
        $this->get(route('cv.public', ['slug' => $slug]))->assertOk()->assertSee('سلمى محمود');

        $this->actingAs($user)->postJson(route('cv.public.toggle'), ['enabled' => false])->assertOk();

        $this->get(route('cv.public', ['slug' => $slug]))->assertNotFound();
    }

    public function test_public_cv_never_exposes_phone_or_email(): void
    {
        $user = $this->trainee(['name' => 'سلمى محمود', 'email' => 'salma.cv@test.local', 'phone' => '+201112223334']);

        Cv::updateOrCreate(['user_id' => $user->id], [
            'data' => ['profile' => ['email' => 'salma.cv@test.local', 'phone' => '+201112223334', 'job_title' => 'مصمّمة']],
            'is_public' => true,
            'public_slug' => 'salmapublic1',
        ]);

        $this->get(route('cv.public', ['slug' => 'salmapublic1']))
            ->assertOk()
            ->assertSee('مصمّمة')
            ->assertDontSee('salma.cv@test.local')
            ->assertDontSee('+201112223334');
    }

    // ------------------------------------------------------------ استخراج ATS

    public function test_ats_export_produces_a_pdf_or_explains_the_missing_font(): void
    {
        $user = $this->trainee();

        Cv::updateOrCreate(['user_id' => $user->id], [
            'data' => ['profile' => ['job_title' => 'مطوّر واجهات'], 'skills' => 'PHP، Laravel'],
        ]);

        $response = $this->actingAs($user)->get(route('cv.ats'));

        if (is_file(public_path((string) setting('cv.ats.font_path', 'fonts/Cairo-Regular.ttf')))) {
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF-1.4', $response->getContent());

            return;
        }

        // بلا خطّ مضمَّن: نحوّل للنسخة القابلة للطباعة بسطر يشرح ماذا يفعل (2.17-ب)
        $response->assertRedirect(route('cv.download'));
        $response->assertSessionHas('status');
    }

    public function test_arabic_shaper_keeps_the_logical_text_for_ats_extraction(): void
    {
        $shaped = app(ArabicShaper::class)->shape('محمد');

        // الأشكال المرسومة من كتلة الأشكال العرضيّة…
        $this->assertTrue(collect($shaped)->every(fn ($g) => $g['form'] >= 0xFE70 && $g['form'] <= 0xFEFF));

        // …والحروف المنطقيّة محفوظة كما هي، وهي ما يقرؤه الـATS
        $logical = collect($shaped)->flatMap(fn ($g) => $g['logical'])->all();
        sort($logical);

        $expected = [0x0645, 0x062D, 0x0645, 0x062F];
        sort($expected);

        $this->assertSame($expected, $logical);
    }

    public function test_lam_alef_becomes_one_ligature(): void
    {
        // «لا» تركيبة إلزاميّة في العربيّة: شكل واحد يحمل حرفين
        $shaped = app(ArabicShaper::class)->shape('لا');

        $this->assertCount(1, $shaped);
        $this->assertSame([0x0644, 0x0627], $shaped[0]['logical']);
    }
}
