<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Services\Admin\Volunteer\CertificateEligibility;
use App\Services\Certificates\CertificateSignature;
use Tests\Feature\Admin\Volunteer\AdminVolunteerTestCase;

/**
 * 🎖️ **لا إصدار خارج المحرّك** (13.4-ع · 12.5 · 8.1).
 *
 * كان لشهادات التطوّع مسارٌ احتياطيّ يكتب صفَّ شهادةٍ بيده كلّما تعثّر المُصدِر:
 * كودٌ عشوائيّ (`VPS-XXXXXXXXXX`) خارج الترقيم المتسلسل (12.5-ب)، وبلا
 * `template_snapshot` (12.5-ج)، وبتوقيعٍ **بلا مفتاح** — `sha256(code|user|
 * position|entity)` — أربعة حقولٍ من يعرفها يُنتج التوقيع بنفسه فيكتب صفًّا
 * تعلنه صفحة التحقّق «ساريًا ومطابقًا». وهو نفس العطب الذي أُزيل من
 * `Services/Events/CertificateBridge`، وأُزيل هنا بالمعالجة نفسها.
 */
class VolunteerCertificateIssuePathTest extends AdminVolunteerTestCase
{
    /** ⭐ الشهادة الصادرة: كودٌ في الترقيم المعتمَد + لقطة قالب + توقيعٌ مطابق */
    public function test_a_volunteer_certificate_follows_the_authorised_issue_path(): void
    {
        $result = CertificateEligibility::issueForMembership($this->membership(days: 90));

        $this->assertTrue($result['issued']);

        $certificate = $result['certificate']->fresh();

        // 12.5-ب: بادئة النوع + السنة + تسلسل مصفوف — لا عشوائيّ
        $this->assertMatchesRegularExpression(
            '/^VPS\-'.now()->year.'\-\d{6}$/u',
            (string) $certificate->code,
            'كود شهادة التطوّع خارج الترقيم المتسلسل.',
        );

        // 12.5-ج: لقطة التصميم مجمَّدة — وإلّا رُسمت الصورة من قالبٍ متغيّر
        $this->assertNotNull($certificate->template_snapshot);

        // 12.5-هـ: توقيعٌ بمفتاح التطبيق يشتقّه المتحقِّق من بياناتها
        $this->assertTrue(app(CertificateSignature::class)->matches($certificate));
    }

    /** وصفحة التحقّق العامّة تُعلنها ساريةً بتوقيعٍ مطابق (8.1) */
    public function test_the_public_page_confirms_the_volunteer_certificate(): void
    {
        $certificate = CertificateEligibility::issueForMembership($this->membership(days: 90))['certificate'];

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee(setting('certificates.status.valid_label', 'سارية'), false)
            ->assertSee(setting('certificates.verify.signature_ok', 'مطابق — البيانات دي هي اللي صدرت'), false);
    }

    /**
     * ⭐ تعثّر مسار الإصدار ⟵ **لا شهادة تُكتَب** بل سببٌ يُعلَن.
     *
     * البديل القديم كان يبتلع التعثّر ويُخرج وثيقةً أضعف — والفشل الصامت الذي
     * يُنتج شهادةً قابلة للتزوير أسوأ من الفشل الظاهر.
     */
    public function test_no_forgeable_row_is_written_when_the_issuer_cannot_issue(): void
    {
        CertificateType::query()->where('key', 'volunteer_position')->update(['is_active' => false]);

        $result = CertificateEligibility::issueForMembership($this->membership(days: 90));

        $this->assertFalse($result['issued']);
        $this->assertNull($result['certificate']);
        $this->assertStringContainsString('خارج المحرّك', (string) $result['reason']);

        $this->assertSame(0, Certificate::query()->count());
    }

    /** وشهادة الخبرة كذلك: مصدرٌ واحد، ولا إنهاءَ لشهادةٍ سارية قبل نجاح البديلة */
    public function test_experience_certificate_keeps_the_old_one_valid_when_issuing_fails(): void
    {
        $membership = $this->membership(days: 400);

        $first = CertificateEligibility::issueExperience($membership->user);
        $this->assertTrue($first['issued']);

        CertificateType::query()->where('key', 'volunteer_experience')->update(['is_active' => false]);

        $second = CertificateEligibility::issueExperience($membership->user->fresh());

        $this->assertFalse($second['issued']);

        // الشهادة القديمة ما زالت سارية — لم تُنهَ لأجل إصدارٍ لم يقع
        $this->assertSame('valid', $first['certificate']->fresh()->status);
    }

    private function membership(int $days): Membership
    {
        $entity = Entity::query()->where('name_ar', 'فريق المونتاج')->firstOrFail();
        $position = Position::query()->where('key', 'coordinator')->firstOrFail();

        return Membership::create([
            'user_id' => $this->makeUser('مرشّح شهادة')->id,
            'entity_id' => $entity->id,
            'position_id' => $position->id,
            'started_at' => now()->subDays($days),
            'status' => 'active',
        ])->load(['user', 'entity', 'position']);
    }
}
