<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\ConsentRequest;
use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\Interview;
use App\Models\LearningPath;
use App\Models\Offboarding;
use App\Models\Position;
use App\Models\RecruitmentCandidate;
use App\Models\Setting;
use App\Models\User;
use App\Models\VolunteerCard;
use App\Services\Admin\Volunteer\OffboardingService;
use App\Services\Volunteer\People\JourneyService;
use App\Services\Volunteer\People\PlacementService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * رحلة المتطوّع كاملةً — الأعطال التي أثبتها التدقيق بالتشغيل (13.4-أ · ب · ج · هـ · ر · س).
 *
 * كلّ اختبار هنا يقابل عطلًا كان يعبره النظام صامتًا:
 * صفحةٌ تقرأ مفاتيح لا يكتبها أحد · تأهيليّ يكتمل بلا مرشّح ولا XP ·
 * متقدّمٌ بلا شاشة حالة · تسكينٌ بلا بطاقة · خروجٌ بلا سحب موافقات ولا شهادة.
 */
class VolunteerJourneyTest extends PeopleTestCase
{
    private function setting(string $key, string $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => 'recruitment',
            'label_ar' => $key,
            'type' => $type,
            'default_value' => $value,
            'value' => $value,
        ]);

        Cache::forget('settings');
    }

    /** بنود التصفية الإلزاميّة — بدونها لا يكتمل أيّ إنهاء (13.4-س-هـ) */
    private function seedClearanceItems(): void
    {
        $this->setting('volunteer.offboarding.clearance_items',
            json_encode(['نقل المهامّ المفتوحة', 'تفويض الداونلاين'], JSON_UNESCAPED_UNICODE), 'json');
    }

    /** مسار تأهيليّ بكورس واحد — وإكماله يعني إكمال المسار */
    private function qualifyingPath(): array
    {
        $path = LearningPath::create([
            'slug' => 'qualifying-test-'.str()->random(6),
            'name_ar' => 'المسار التأهيليّ',
            'status' => 'published',
        ]);

        $course = Course::create([
            'slug' => 'qualifying-course-'.str()->random(6),
            'name_ar' => 'أساسيّات التطوّع',
            'status' => 'published',
        ]);

        DB::table('course_learning_path')->insert([
            'course_id' => $course->id,
            'learning_path_id' => $path->id,
            'sort_order' => 1,
        ]);

        $this->setting('volunteer.qualifying.path_id', (string) $path->id, 'number');

        return [$path, $course];
    }

    private function completePath(User $user, Course $course): void
    {
        CourseCompletion::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'completed_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- 13.4-أ

    /** ⭐ الصفحة تعرض **ما كتبه الأدمن فعلًا**: العنوان والميثاق والكتل */
    public function test_landing_renders_exactly_what_the_admin_wrote(): void
    {
        $this->setting('volunteer_page.hero_title', 'اتحرّك معانا');
        $this->setting('volunteer_page.hero_subtitle', 'ساعتك بتفرق');
        $this->setting('volunteer_page.charter_text', 'أتعهّد بالالتزام بمواعيدي', 'text');
        $this->setting('volunteer_page.blocks', json_encode([
            ['type' => 'faq', 'title' => 'محتاج وقت قدّ إيه؟', 'body' => 'الالتزام مرن.'],
            ['type' => 'story', 'title' => 'قصّة مريم', 'body' => 'بدأت كوردنيتور.'],
            ['type' => 'impact', 'title' => 'أثرك بيتقاس', 'body' => 'كلّ ساعة بتوصل.'],
        ], JSON_UNESCAPED_UNICODE), 'json');

        $user = $this->makeUser('زائر الصفحة');

        $this->actingAs($user)->get(route('volunteering.landing'))
            ->assertOk()
            ->assertSee('اتحرّك معانا')
            ->assertSee('ساعتك بتفرق')
            // الميثاق يُعرَض قبل بدء التأهيليّ (13.4-أ)
            ->assertSee('أتعهّد بالالتزام بمواعيدي')
            // الكتل تخرج للمستخدم لا للأدمن وحده — كانت حلقةً مغلقة
            ->assertSee('محتاج وقت قدّ إيه؟')
            ->assertSee('قصّة مريم')
            ->assertSee('أثرك بيتقاس')
            ->assertDontSee('محتوى الصفحة بيتجهّز');
    }

    /** الإحصائيّات حيّة، والإزاحة إعدادٌ يضبطه الأدمن (13.4-أ) */
    public function test_live_stats_apply_the_admin_offsets(): void
    {
        $this->setting('volunteer_page.stats_enabled', '1', 'bool');
        $this->setting('volunteer_page.stats_offset_volunteers', '0', 'number');
        $this->setting('volunteer_page.stats_trainees_label', 'متدرّب مستفيد');

        $content = app(\App\Services\Volunteer\People\LandingContent::class);
        $base = $content->stats()['volunteers'];

        $member = $this->makeUser('متطوّع مُسكَّن');
        $this->makeMembership($member, $this->makeEntity('قسم الإحصاء'));

        // العدّاد **حيّ**: العضويّة الجديدة تظهر فورًا
        $this->assertSame($base + 1, $content->stats()['volunteers']);

        // …والإزاحة تُضاف فوق الرقم الحقيقيّ لا بدلًا منه
        $this->setting('volunteer_page.stats_offset_volunteers', '40', 'number');

        $stats = $content->stats();
        $this->assertSame($base + 41, $stats['volunteers'], 'العدّاد الحيّ + الإزاحة');
        $this->assertGreaterThan(0, $stats['trainees']);
    }

    /** ⭐ الميثاق شرطٌ **قبل** بدء التأهيليّ — والزرّ يوجّه إليه لا إلى المسار */
    public function test_charter_must_be_accepted_before_the_qualifying_path(): void
    {
        $this->setting('volunteer_page.charter_text', 'ميثاق تجريبيّ', 'text');
        $user = $this->makeUser('صاحب الميثاق');

        $this->assertFalse(app(JourneyService::class)->startGate($user)['open']);

        $this->actingAs($user)->post(route('volunteering.charter'), ['agree' => '1'])
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->volunteer_charter_accepted_at);
        $this->assertTrue(app(JourneyService::class)->startGate($user->fresh())['open']);
    }

    // ---------------------------------------------------------------- 13.4-ب

    /** ⭐ إتمام التأهيليّ **ينشئ مرشّحًا ويمنح 1000 XP** — الفجوة التي كان لا يعبرها إلّا السيدر */
    public function test_completing_the_qualifying_path_creates_a_candidate_and_grants_the_xp(): void
    {
        [$path, $course] = $this->qualifyingPath();

        $user = $this->makeUser('متقدّم جديد');
        $user->forceFill(['volunteer_charter_accepted_at' => now()])->save();
        $this->completePath($user, $course);

        $xpBefore = (int) $user->fresh()->xp;

        $this->actingAs($user)->post(route('volunteering.next'))->assertRedirect();

        $candidate = RecruitmentCandidate::where('user_id', $user->id)->first();

        $this->assertNotNull($candidate, 'إتمام التأهيليّ لازم يُنشئ مرشّحًا');
        $this->assertSame('applied', $candidate->stage);
        $this->assertNotNull($candidate->applied_at);

        $this->assertSame(
            $xpBefore + 1000,
            (int) $user->fresh()->xp,
            'مكافأة 1000 XP لإتمام المسار التأهيليّ (13.4-ب)',
        );

        $this->assertDatabaseHas('volunteer_path_awards', [
            'user_id' => $user->id,
            'learning_path_id' => $path->id,
            'kind' => 'qualifying_xp',
        ]);
    }

    /** المكافأة **مرّة واحدة** — والقيد الفريد هو الحارس الأخير */
    public function test_the_qualifying_reward_is_granted_only_once(): void
    {
        [, $course] = $this->qualifyingPath();

        $user = $this->makeUser('متقدّم مكرّر');
        $this->completePath($user, $course);

        $journey = app(JourneyService::class);

        $this->assertSame(1000, $journey->completeIfDue($user));
        $this->assertSame(0, $journey->completeIfDue($user->fresh()));
        $this->assertSame(1000, (int) $user->fresh()->xp);
    }

    /** التأهيليّ غير المكتمل لا يفتح المرحلة التالية */
    public function test_incomplete_path_cannot_enter_the_pipeline(): void
    {
        $this->qualifyingPath();

        $user = $this->makeUser('لسّه بيذاكر');
        $user->forceFill(['volunteer_charter_accepted_at' => now()])->save();

        $this->actingAs($user)->post(route('volunteering.next'))->assertRedirect();

        $this->assertSame(0, RecruitmentCandidate::where('user_id', $user->id)->count());
    }

    // ---------------------------------------------------------------- 13.4-ج

    /** ⭐ المتقدّم يرى حالته: Stepper و«حالتي» ورابط المقابلة وعدّادها */
    public function test_applicant_sees_his_status_stepper_and_interview(): void
    {
        $user = $this->makeUser('مرشّح في المقابلة');

        $candidate = RecruitmentCandidate::create([
            'user_id' => $user->id,
            'stage' => 'interview',
            'applied_at' => now()->subDays(10),
            'stage_changed_at' => now()->subDay(),
        ]);

        Interview::create([
            'recruitment_candidate_id' => $candidate->id,
            'interviewer_id' => $this->makeUser('مُقابِل')->id,
            'scheduled_at' => now()->addDays(2),
            'external_link' => 'https://meet.example.com/vol-1',
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)->get(route('volunteering.landing'))
            ->assertOk()
            ->assertSee('حالتي')
            ->assertSee('قائمة مبدئيّة')
            ->assertSee('قائمة نهائيّة')
            ->assertSee('https://meet.example.com/vol-1', false)
            ->assertSee('data-countdown-to', false);
    }

    /** ⭐ «جدّد استعدادك»: زرّ وكاتب ومسار وتبريد — لا شارةً بلا كاتب (13.4-هـ) */
    public function test_renew_readiness_has_a_writer_and_a_cooldown(): void
    {
        $this->setting('volunteer.journey.renew_cooldown_days', '14', 'number');

        $user = $this->makeUser('مرشّح متحمّس');

        $candidate = RecruitmentCandidate::create([
            'user_id' => $user->id,
            'stage' => 'final_list',
            'applied_at' => now()->subDays(40),
        ]);

        $this->actingAs($user)->post(route('volunteering.renew'))->assertRedirect();

        $candidate->refresh();
        $this->assertTrue((bool) $candidate->renewed_readiness);
        $this->assertNotNull($candidate->readiness_renewed_at);
        // مدّة الانتظار الحقيقيّة لا تُمحى بالتجديد
        $this->assertTrue($candidate->applied_at->lessThan(now()->subDays(39)));

        // التبريد يمنع التكرار المتلاحق
        $this->assertFalse(app(JourneyService::class)->canRenew($candidate));
    }

    // ---------------------------------------------------------------- 13.4-ر

    /** ⭐ التسكين **يُصدر بطاقة** لحظتَه — كانت البطاقات صفرًا قبله وصفرًا بعده */
    public function test_placement_issues_the_digital_card_at_that_very_moment(): void
    {
        $service = app(PlacementService::class);

        $user = $this->makeUser('مرشّح التسكين');
        $candidate = RecruitmentCandidate::create([
            'user_id' => $user->id,
            'stage' => 'final_list',
            'applied_at' => now()->subDays(3),
        ]);

        $entity = $this->makeEntity('قسم البطاقة');
        $position = Position::firstWhere('key', 'coordinator');

        $this->assertSame(0, VolunteerCard::count());

        $request = $service->request($candidate, $entity, $position, $this->makeUser('مشرف التوظيف'));
        $service->respond($request, 'accepted', $user);

        $card = VolunteerCard::where('user_id', $user->id)->first();

        $this->assertNotNull($card, 'البطاقة تُصدَر لحظة التسكين (13.4-ر-ج)');
        $this->assertSame('valid', $card->status);
        // ⛔ ولا بيانات تواصل إطلاقًا على البطاقة
        $this->assertArrayNotHasKey('phone', (array) $card->data_snapshot);
        $this->assertArrayNotHasKey('email', (array) $card->data_snapshot);
    }

    // ---------------------------------------------------------------- 13.4-س

    /** ⭐ الخروج **يسحب الموافقات ويُصدر شهادة الخبرة** ويُنهي البطاقة لحظتَه */
    public function test_offboarding_revokes_consents_and_issues_the_experience_certificate(): void
    {
        $user = $this->makeUser('خارج بشرف');
        $colleague = $this->makeUser('زميل');

        $membership = $this->makeMembership($user, $this->makeEntity('قسم الخروج'));
        $membership->forceFill(['started_at' => now()->subMonths(8)])->save();

        app(\App\Services\Volunteer\Org\CardIssuer::class)->issueFor($membership->fresh());

        // موافقتان ساريتان: ما منحه وما مُنِح له
        ConsentRequest::create([
            'requester_id' => $colleague->id, 'owner_id' => $user->id, 'field' => 'phone',
            'status' => 'granted', 'request_expires_at' => now()->addDay(),
            'granted_at' => now(), 'consent_expires_at' => now()->addDays(30),
        ]);
        ConsentRequest::create([
            'requester_id' => $user->id, 'owner_id' => $colleague->id, 'field' => 'email',
            'status' => 'granted', 'request_expires_at' => now()->addDay(),
            'granted_at' => now(), 'consent_expires_at' => now()->addDays(30),
        ]);

        $actor = $this->makeUser('دايركتور');
        $this->seedClearanceItems();

        $record = OffboardingService::open($user, 'resignation', 'ظروف دراسة', $actor, []);
        $record->forceFill([
            'clearance_checklist' => collect(OffboardingService::clearanceItems())
                ->map(fn ($label) => ['label' => $label, 'done' => true])->all(),
        ])->save();

        OffboardingService::complete($record->fresh(), $actor);

        // 1) لا موافقة سارية بعد الخروج — لا فيما منح ولا فيما مُنِح له
        $this->assertSame(0, ConsentRequest::query()
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhere('requester_id', $user->id))
            ->where('status', 'granted')->count(), 'كلّ موافقات إظهار التواصل تُلغى تلقائيًّا (13.4-س-ز)');

        // 2) شهادة خبرة التطوّع صدرت فعلًا — لا علَمًا بلا سجلّ
        $typeId = CertificateType::where('key', 'volunteer_experience')->value('id');
        $this->assertSame(1, Certificate::where('user_id', $user->id)
            ->where('certificate_type_id', $typeId)->count(), 'شهادة خبرة تطوّع عند الخروج المشرَّف (13.4-س-ط)');
        $this->assertTrue((bool) $record->fresh()->honorable_certificate_issued);

        // 3) البطاقة «منتهية» لحظة الخروج لا كسولًا
        $this->assertSame('expired', VolunteerCard::where('user_id', $user->id)->value('status'));
    }

    /** الإقصاء لا شهادة خبرة فيه — والخارج بجفاءٍ خصم لا سفير (13.4-س-ط) */
    public function test_exclusion_never_issues_an_experience_certificate(): void
    {
        $user = $this->makeUser('مُقصًى');
        $this->makeMembership($user, $this->makeEntity('قسم الإقصاء'));

        $actor = $this->makeUser('مشرف عام');
        $this->seedClearanceItems();

        $record = Offboarding::create([
            'user_id' => $user->id,
            'type' => 'exclusion',
            'initiated_by' => $actor->id,
            'notice_until' => now(),
            'clearance_checklist' => collect(OffboardingService::clearanceItems())
                ->map(fn ($label) => ['label' => $label, 'done' => true])->all(),
        ]);

        OffboardingService::complete($record, $actor);

        $typeId = CertificateType::where('key', 'volunteer_experience')->value('id');

        $this->assertSame(0, Certificate::where('user_id', $user->id)
            ->where('certificate_type_id', $typeId)->count());
        $this->assertFalse((bool) $record->fresh()->honorable_certificate_issued);
    }
}
