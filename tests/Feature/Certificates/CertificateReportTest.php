<?php

namespace Tests\Feature\Certificates;

use App\Models\CertificateReport;
use App\Models\CertificateType;
use App\Models\Permission;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use App\Support\Access\AccessEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Exams\ExamTestCase;

/**
 * ⭐ **«أبلغ عن شهادة مشبوهة»** (12.5-هـ · 24.1).
 *
 * المرصود قبل الإصلاح: زرٌّ في صفحة التحقّق **بلا جدولٍ ولا شاشة مراجعة** —
 * البلاغ يذهب إلى حيث لا يقرؤه أحد. والنصّ يوجب الاثنين:
 * «وأسفلها **جدول البلاغات:** الكود · المبلِّغ · السبب · التاريخ · الحالة ·
 * [مراجعة]» · «`pop-box` «مراجعة بلاغ» [التفاصيل + إجراء: تجاهل/إلغاء الشهادة/
 * تصعيد]» (24.1).
 */
class CertificateReportTest extends ExamTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CertificateType::query()->update(['is_active' => true]);
    }

    // -------------------------------------------------- 1) البلاغ يصل فعلًا

    /** ⭐ صفحة التحقّق **عامّة بلا حساب** (21.2-ز) — فالبلاغ منها يُحفَظ صفًّا بلا تسجيل دخول */
    public function test_an_anonymous_report_lands_as_a_row(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee('ياسمين فؤاد'), 'course');

        $this->assertGuest();

        $this->post(route('verify.certificate.report'), [
            'code' => $certificate->code,
            'note' => 'الورقة اللي وصلتني اسمها مختلف عن اللي على الموقع.',
            'contact' => 'hr@example.com',
        ])->assertRedirect(route('verify.certificate', ['code' => $certificate->code]));

        $this->assertDatabaseHas('certificate_reports', [
            'certificate_id' => $certificate->id,
            'code' => $certificate->code,
            'reporter_id' => null,
            'reporter_contact' => 'hr@example.com',
            'status' => CertificateReport::NEW,
        ]);
    }

    /** ⭐ والبلاغ عن **كودٍ لا صفّ له** بلاغٌ حقيقيّ لا خطأ إدخال — ورقةٌ بكودٍ مخترَع هي عين التزوير */
    public function test_a_report_about_a_code_we_never_issued_is_kept_too(): void
    {
        $this->post(route('verify.certificate.report'), [
            'code' => 'HC-FAKE-999',
            'note' => 'حد وراني ورقة بالكود ده وقال إنّها منكم.',
        ])->assertRedirect();

        $report = CertificateReport::query()->firstOrFail();

        $this->assertSame('HC-FAKE-999', $report->code);
        $this->assertNull($report->certificate_id);
    }

    // -------------------------------------------------- 2) شاشة المراجعة

    /** جدول البلاغات يظهر للمخوَّل: الكود والمبلِّغ والسبب (24.1) */
    public function test_the_review_screen_lists_the_reports(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee('نادر سليم'), 'course');

        $this->post(route('verify.certificate.report'), [
            'code' => $certificate->code,
            'note' => 'الختم اللي على الورقة مش زيّ اللي عندكم.',
        ]);

        $this->actingAs($this->reviewer())
            ->get(route('admin.certificates.index', ['tab' => 'verification']))
            ->assertOk()
            ->assertSee($certificate->code, false)
            ->assertSee('الختم اللي على الورقة مش زيّ اللي عندكم.', false)
            ->assertSee(setting('certificates.reports.review_button'), false);
    }

    /** وبلا صلاحيّة لا يُفتَح الباب (12.2.1) */
    public function test_reviewing_needs_the_verification_permission(): void
    {
        $report = $this->report();

        $this->actingAs($this->trainee('مستخدم عاديّ'))
            ->post(route('admin.certificates.reports.review', $report), ['action' => CertificateReport::DISMISSED])
            ->assertForbidden();

        $this->assertSame(CertificateReport::NEW, $report->fresh()->status);
    }

    /** «تجاهل» يقفل البلاغ ويسجّل مَن راجعه ومتى — ولا يمسّ الشهادة */
    public function test_dismiss_closes_the_report_and_leaves_the_certificate_alone(): void
    {
        $report = $this->report();
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)
            ->post(route('admin.certificates.reports.review', $report), [
                'action' => CertificateReport::DISMISSED,
                'note' => 'راجعنا الورقة وطلعت سليمة.',
            ])->assertRedirect();

        $report = $report->fresh();

        $this->assertSame(CertificateReport::DISMISSED, $report->status);
        $this->assertSame($reviewer->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
        $this->assertSame('valid', $report->certificate->fresh()->status);
    }

    /** و«تصعيد» كذلك: يوسم البلاغ ولا يُلغي شيئًا */
    public function test_escalate_marks_the_report_without_revoking(): void
    {
        $report = $this->report();

        $this->actingAs($this->reviewer())
            ->post(route('admin.certificates.reports.review', $report), ['action' => CertificateReport::ESCALATED])
            ->assertRedirect();

        $this->assertSame(CertificateReport::ESCALATED, $report->fresh()->status);
        $this->assertSame('valid', $report->fresh()->certificate->status);
    }

    /**
     * ⭐ **«صلاحيّة الإلغاء منفصلة عن الإصدار»** (24.1): مراجعُ البلاغات لا يُلغي
     * شهادةً بمجرّد أنّه يراجع — والإلغاء لا يقع إلّا على تزويرٍ مثبَت (13.4-ق).
     */
    public function test_revoking_from_a_report_needs_the_separate_revoke_permission(): void
    {
        $report = $this->report();

        $this->actingAs($this->reviewer())
            ->post(route('admin.certificates.reports.review', $report), ['action' => CertificateReport::REVOKED])
            ->assertRedirect();

        $this->assertSame('valid', $report->fresh()->certificate->status, 'اتلغت شهادة بلا صلاحيّة الإلغاء.');
        $this->assertSame(CertificateReport::NEW, $report->fresh()->status);
    }

    /** ومن يملكها يُلغي — بسببٍ موثّق ينعكس في التحقّق */
    public function test_a_reviewer_who_may_revoke_revokes_with_a_documented_reason(): void
    {
        $report = $this->report();

        $this->actingAs($this->reviewer(withRevoke: true))
            ->post(route('admin.certificates.reports.review', $report), [
                'action' => CertificateReport::REVOKED,
                'reason' => 'تزوير مثبَت',
            ])->assertRedirect();

        $certificate = $report->fresh()->certificate;

        $this->assertSame('revoked', $certificate->status);
        $this->assertSame('تزوير مثبَت', $certificate->revoked_reason);
        $this->assertSame(CertificateReport::REVOKED, $report->fresh()->status);

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee(setting('certificates.verify.revoked_text'), false);
    }

    /** ولا إجراءَ رابعًا: النصّ يعدّ ثلاثة، فما عداها مرفوض */
    public function test_no_fourth_action_exists(): void
    {
        $report = $this->report();

        $this->actingAs($this->reviewer())
            ->post(route('admin.certificates.reports.review', $report), ['action' => 'archived'])
            ->assertSessionHasErrors('action');

        $this->assertSame(CertificateReport::NEW, $report->fresh()->status);
    }

    // ------------------------------------------------------------ مساعدات

    private function report(): CertificateReport
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee('صاحب الشهادة'), 'course');

        return CertificateReport::create([
            'certificate_id' => $certificate->id,
            'code' => $certificate->code,
            'reason' => 'الورقة مريبة.',
            'status' => CertificateReport::NEW,
        ]);
    }

    private function reviewer(bool $withRevoke = false): User
    {
        $user = $this->trainee('مراجع البلاغات');

        $keys = ['certificate_verification.edit', 'certificate_ledger.view'];

        if ($withRevoke) {
            $keys[] = 'certificates.delete';
        }

        foreach ($keys as $key) {
            [$resource, $action] = explode('.', $key);

            $permission = Permission::firstOrCreate(['key' => $key], [
                'resource' => $resource,
                'action' => $action,
                'group' => 'التعلّم والمحتوى والشهادات',
                'label_ar' => $key,
                'allowed_scopes' => ['ALL'],
            ]);

            $user->permissionOverrides()->attach($permission->id, ['scope' => 'ALL', 'effect' => 'allow']);
        }

        app(AccessEngine::class)->forget($user);

        return $user->fresh();
    }
}
