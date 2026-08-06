<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\ConsentRequest;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Track;
use App\Models\User;
use App\Services\Admin\Volunteer\CertificateEligibility;
use App\Services\Admin\Volunteer\OffboardingService;
use Illuminate\Support\Facades\Cache;

/**
 * إغلاق الملفّ المؤقّت يُقفل عضويّاته هو وحده تلقائيًّا (§1518 · §3054 · §3813)
 * ويُصدر شهادة «مشاركة في ملفّ» (13.4-ع-3) — بلا مسّ عضويّات أخرى للمستخدم.
 */
class CaseFileCertificateTest extends AdminVolunteerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(['key' => 'volunteer.offboarding.clearance_items'], [
            'group' => 'offboarding', 'label_ar' => 'clearance', 'type' => 'json',
            'default_value' => json_encode(['نقل المهامّ المفتوحة'], JSON_UNESCAPED_UNICODE),
            'value' => json_encode(['نقل المهامّ المفتوحة'], JSON_UNESCAPED_UNICODE),
        ]);

        Cache::forget('settings');
    }

    private function caseFileEntity(): Entity
    {
        return Entity::create([
            'track_id' => Track::where('key', 'case_file')->value('id'),
            'name_ar' => 'ملفّ اختباريّ',
            'status' => 'active',
            'opened_at' => now()->subDays(200),
        ]);
    }

    private function departmentEntity(): Entity
    {
        return Entity::query()->where('name_ar', 'فريق المونتاج')->firstOrFail();
    }

    private function membership(User $user, Entity $entity, int $daysAgo = 40, string $positionKey = 'coordinator'): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::where('key', $positionKey)->value('id'),
            'is_primary' => true,
            'started_at' => now()->subDays($daysAgo),
            'status' => 'active',
        ]);
    }

    private function gm(): User
    {
        return $this->grant($this->makeUser('مشرف عام'), 'org_chart.view', 'org_chart.edit', 'case_files.archive');
    }

    // ------------------------------------------------------------ الحصر بالكيان

    /** ⭐ إغلاق الملفّ لا يمسّ عضويّة القسم الأخرى لنفس الشخص */
    public function test_archiving_a_case_file_closes_only_its_own_membership(): void
    {
        $user = $this->makeUser('عضو مزدوج');
        $caseFile = $this->caseFileEntity();
        $caseFileM = $this->membership($user, $caseFile, 40);
        $departmentM = $this->membership($user, $this->departmentEntity(), 400);

        $this->actingAs($this->gm())
            ->post(route('admin.volunteer.org.entity.archive', $caseFile))
            ->assertRedirect();

        $this->assertSame('ended', $caseFileM->fresh()->status);
        $this->assertSame('active', $departmentM->fresh()->status, 'عضويّة القسم الأخرى لا تُقفَل بإنهاء ملفٍّ مختلف.');
    }

    /** أرشفة كيانٍ دائم (لا ملفّ مؤقّت) لا تُشغّل أيّ إغلاقٍ آليّ للعضويّات */
    public function test_archiving_a_permanent_entity_does_not_cascade_offboarding(): void
    {
        $user = $this->makeUser('عضو قسم');
        $department = $this->departmentEntity();
        $membership = $this->membership($user, $department, 400);

        $admin = $this->grant($this->makeUser(), 'org_chart.view', 'org_chart.edit');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.org.entity.archive', $department))
            ->assertRedirect();

        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame(0, Offboarding::query()->count());
    }

    // ------------------------------------------------------------ الشهادة

    /** ⭐ عضويّة مستوفية الشرطين ⟵ شهادة «مشاركة في ملفّ» فعليّة */
    public function test_eligible_membership_receives_the_case_file_certificate(): void
    {
        $user = $this->makeUser('مشارك مستحقّ');
        $caseFile = $this->caseFileEntity();
        $this->membership($user, $caseFile, 40);

        $this->actingAs($this->gm())
            ->post(route('admin.volunteer.org.entity.archive', $caseFile))
            ->assertRedirect();

        $typeId = CertificateType::where('key', 'volunteer_case_file')->value('id');
        $this->assertSame(1, Certificate::query()->where('user_id', $user->id)->where('certificate_type_id', $typeId)->count());
    }

    /** ⭐ أقلّ من الحدّ الأدنى ⟵ بلا شهادة، لكنّ العضويّة تُقفَل كما هي */
    public function test_membership_below_minimum_days_gets_no_certificate(): void
    {
        $user = $this->makeUser('مشارك سريع');
        $caseFile = $this->caseFileEntity();
        $membership = $this->membership($user, $caseFile, 5);

        $this->actingAs($this->gm())
            ->post(route('admin.volunteer.org.entity.archive', $caseFile))
            ->assertRedirect();

        $typeId = CertificateType::where('key', 'volunteer_case_file')->value('id');
        $this->assertSame(0, Certificate::query()->where('user_id', $user->id)->where('certificate_type_id', $typeId)->count());
        $this->assertSame('ended', $membership->fresh()->status, 'العضويّة تُقفَل رغم عدم استحقاق الشهادة.');
    }

    public function test_issue_case_file_directly_reports_the_reason_when_ineligible(): void
    {
        $user = $this->makeUser('مشارك سريع مباشر');
        $caseFile = $this->caseFileEntity();
        $membership = $this->membership($user, $caseFile, 2);

        $result = CertificateEligibility::issueCaseFile($membership);

        $this->assertFalse($result['issued']);
        $this->assertStringContainsString('يومًا', (string) $result['reason']);
    }

    // ------------------------------------------------------------ صحّة الحصر البرمجيّ

    /** ⭐ الحصر بالكيان يعمل عبر OffboardingService مباشرةً — لا فقط عبر مسار الأرشفة */
    public function test_entity_scoped_offboarding_only_closes_that_entitys_membership(): void
    {
        $user = $this->makeUser('عضو مزدوج مباشر');
        $caseFile = $this->caseFileEntity();
        $caseFileM = $this->membership($user, $caseFile, 40);
        $departmentM = $this->membership($user, $this->departmentEntity(), 400);

        $actor = $this->gm();
        $record = OffboardingService::open($user, 'entity_ended', null, $actor, [true], $caseFile);
        OffboardingService::complete($record->fresh(), $actor);

        $this->assertSame('ended', $caseFileM->fresh()->status);
        $this->assertSame('active', $departmentM->fresh()->status);
        $this->assertSame($caseFile->id, $record->fresh()->entity_id);
    }

    /** ⭐ الإغلاق المحصور بكيانٍ واحد لا يسحب موافقات التواصل ولا ينهي البطاقة — تلك آثارٌ لخروجٍ كامل */
    public function test_entity_scoped_offboarding_does_not_revoke_whole_account_consents(): void
    {
        $user = $this->makeUser('صاحب موافقة');
        $other = $this->makeUser('طرف الموافقة');
        $caseFile = $this->caseFileEntity();
        $this->membership($user, $caseFile, 40);

        $consent = ConsentRequest::create([
            'owner_id' => $user->id,
            'requester_id' => $other->id,
            'field' => 'phone',
            'status' => 'granted',
            'request_expires_at' => now()->addDays(7),
            'granted_at' => now(),
        ]);

        $actor = $this->gm();
        $record = OffboardingService::open($user, 'entity_ended', null, $actor, [true], $caseFile);
        OffboardingService::complete($record->fresh(), $actor);

        $this->assertSame('granted', $consent->fresh()->status, 'خروجٌ من ملفٍّ واحد لا يسحب موافقات التواصل — تلك أثر خروجٍ كامل.');
    }
}
