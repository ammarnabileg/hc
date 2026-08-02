<?php

namespace Tests\Feature\Ui;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Cv;

/**
 * الرابط العامّ للسيرة والإفادة (الدستور 9 · 9.1 · 10.0-ج).
 */
class CvPublicAndAttestationTest extends UiTestCase
{
    /**
     * ⚠️ العطل المُثبَت: `$cv->user` تُرجِع null للمحذوف Soft فينفجر البناء
     * بـ500 — وكلّ الواجهات الأخرى ترجّع 404 نظيفًا. والـ500 نفسه **يُثبت
     * وجود الرابط** بدل أن يخفيه.
     */
    public function test_public_cv_of_a_deleted_user_is_a_clean_404(): void
    {
        $user = $this->trainee(['name' => 'هالة سمير']);

        Cv::updateOrCreate(['user_id' => $user->id], [
            'data' => ['profile' => ['job_title' => 'مهندسة']],
            'is_public' => true,
            'public_slug' => 'haladeleted1',
        ]);

        $this->get(route('cv.public', ['slug' => 'haladeleted1']))->assertOk();

        $user->delete();

        $this->get(route('cv.public', ['slug' => 'haladeleted1']))->assertNotFound();
    }

    /**
     * ⚠️ العطل المُثبَت: رقم الشهادة كان يُقرأ من `serial` وهو عمودٌ لا وجود
     * له — والعمود اسمه `code` — فخرج الرقم فارغًا دائمًا.
     */
    public function test_certificate_number_appears_on_the_public_cv(): void
    {
        $user = $this->trainee(['name' => 'كريم عادل']);

        $type = CertificateType::create(['name_ar' => 'شهادة إتمام', 'name_en' => 'Completion', 'key' => 'ui-cv-cert']);

        Certificate::create([
            'user_id' => $user->id,
            'certificate_type_id' => $type->id,
            'code' => 'CERT-77991',
            'status' => 'valid',
            'issued_at' => now()->subMonth(),
        ]);

        Cv::updateOrCreate(['user_id' => $user->id], [
            'data' => ['profile' => ['job_title' => 'مطوّر']],
            'is_public' => true,
            'public_slug' => 'kareempublic',
        ]);

        $this->get(route('cv.public', ['slug' => 'kareempublic']))
            ->assertOk()
            ->assertSee('CERT-77991', false);
    }

    /**
     * ⚠️ العطل المُثبَت: `/attestation/{code}` كان `firstOrFail()` على كود
     * المستخدم بلا أيّ فحص موافقة — فيرى الزائر اسمًا وXP وتدريبات وشهادات
     * لمن لم يوافق، والأكواد متسلسلة فالتعداد ممكن.
     */
    public function test_attestation_link_respects_consent_and_closes_on_demand(): void
    {
        $user = $this->trainee(['name' => 'نورهان جمال']);

        // بلا موافقة: لا رابط أصلًا — ولا حتّى بكود المستخدم
        $this->get('/attestation/'.$user->code)->assertNotFound();

        $response = $this->actingAs($user)->postJson(route('attestations.public.toggle'), ['enabled' => true]);
        $response->assertOk()->assertJson(['enabled' => true]);

        $slug = Cv::where('user_id', $user->id)->value('attestation_slug');
        $this->assertNotEmpty($slug);

        // الرابط عشوائيّ لا متسلسل — فلا يُخمَّن ما بعده
        $this->assertNotSame($user->code, $slug);

        $this->get(route('attestations.public', $slug))->assertOk()->assertSee('نورهان جمال', false);

        // والإغلاق فوريّ (9.1)
        $this->actingAs($user)->postJson(route('attestations.public.toggle'), ['enabled' => false])->assertOk();

        $this->get(route('attestations.public', $slug))->assertNotFound();
    }
}
