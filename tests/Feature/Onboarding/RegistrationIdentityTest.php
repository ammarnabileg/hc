<?php

namespace Tests\Feature\Onboarding;

use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Library\AttestationBuilder;
use App\Services\Library\CvBuilder;
use App\Services\Security\OtpService;
use App\Services\Security\RequireVerifiedEmail;
use Illuminate\Support\Facades\DB;

/**
 * 2.5-ج — صفحة المعلومات = **«بيانات الشهادات والإفادات»**.
 *
 * الاختبارات هنا تحرس المعنى لا الشكل: البيانات التي تُجمَع عند الباب هي
 * **بعينها** ما يُطبَع على الشهادة والسيرة والإفادة. لو انقطع هذا الخيط
 * صار الحقل زينةَ بروفايل، وخرجت الشهادة باسمٍ ناقص — بلا رجعة لأنّها تُجمَّد.
 */
class RegistrationIdentityTest extends OnboardingTestCase
{
    /** بيانات فورم التسجيل كاملةً كما في 2.5-ج */
    private function form(array $overrides = []): array
    {
        return [
            'title' => 'المهندس',
            'name_ar' => 'محمّد أحمد علي',
            'name_en' => 'Mohamed Ahmed Ali',
            'gender' => 'male',
            'country_id' => $this->egypt()->id,
            'governorate_id' => $this->cairo()->id,
            'address_line' => 'شارع النيل، المعادي',
            'email' => 'm@test.local',
            'phone' => '+201000000001',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            ...$overrides,
        ];
    }

    /** يمرّ ببوّابة الـOTP كما هي — فهي سليمة ولا تُكسَر (2.5-ب) */
    private function registerThroughOtp(array $form): void
    {
        $this->post('/register', $form)->assertRedirect(route('register.verify'));
        $this->post(route('register.verify.send'));

        $code = decrypt(DB::table('security_otp_codes')
            ->where('email', $form['email'])
            ->where('purpose', OtpService::PURPOSE_REGISTER)
            ->value('code'), false);

        $this->post(route('register.verify.confirm'), ['code' => $code]);
    }

    public function test_registration_stores_the_certificate_identity_fields(): void
    {
        $this->registerThroughOtp($this->form());

        $user = User::where('email', 'm@test.local')->firstOrFail();

        // اللقب · الاسم بالعربيّ · الاسم بالإنجليزيّ · النوع · الدولة · المحافظة · العنوان الفرعيّ
        $this->assertSame('المهندس', $user->title);
        $this->assertSame('محمّد أحمد علي', $user->name_ar);
        $this->assertSame('Mohamed Ahmed Ali', $user->name_en);
        $this->assertSame('male', $user->gender);
        $this->assertSame($this->egypt()->id, $user->country_id);
        $this->assertSame($this->cairo()->id, $user->governorate_id);
        $this->assertSame('شارع النيل، المعادي', $user->address_line);

        // والاسم المعروض في المنصّة = الاسم بالعربيّ، فلا حقلٌ ثالث يتناقض معهما
        $this->assertSame('محمّد أحمد علي', $user->name);
    }

    public function test_arabic_name_must_be_arabic_and_english_name_must_be_english(): void
    {
        // البريد مؤكَّد سلفًا كي نصل لتحقّقات صفحة المعلومات نفسها لا لبوّابة الـOTP
        $verified = fn () => $this->withSession([RequireVerifiedEmail::SESSION_VERIFIED => 'm@test.local']);

        $verified()->post('/register', $this->form(['name_ar' => 'Mohamed Ahmed Ali']))
            ->assertSessionHasErrors('name_ar');

        $verified()->post('/register', $this->form(['name_en' => 'محمّد أحمد علي']))
            ->assertSessionHasErrors('name_en');

        // «ثلاثيّ» شرطٌ منصوص — والعدد إعدادٌ لا رقم محروق
        $verified()->post('/register', $this->form(['name_ar' => 'محمّد أحمد']))
            ->assertSessionHasErrors('name_ar');

        $this->assertDatabaseMissing('users', ['email' => 'm@test.local']);
    }

    public function test_certificate_snapshot_carries_the_title_and_both_names(): void
    {
        $user = $this->member([
            'title' => 'الأستاذ الدكتور',
            'name_ar' => 'سعيد كامل حسن',
            'name_en' => 'Saeed Kamel Hassan',
        ]);

        $certificate = app(CertificateIssuer::class)->issue($user, 'course');

        $this->assertNotNull($certificate);

        $data = $certificate->data_snapshot;

        // ⭐ الشهادة العربيّة تُطبَع باللقب + الاسم بالعربيّ
        $this->assertSame('الأستاذ الدكتور سعيد كامل حسن', $data['holder_name']);
        // والنسختان محفوظتان في اللقطة، فالنسخة الإنجليزيّة لا تخرج بالاسم العربيّ
        $this->assertSame('سعيد كامل حسن', $data['holder_name_ar']);
        $this->assertSame('Saeed Kamel Hassan', $data['holder_name_en']);
        $this->assertSame('الأستاذ الدكتور', $data['holder_title']);
        $this->assertStringContainsString('شارع الجمهوريّة', (string) $data['holder_address']);
    }

    public function test_cv_and_attestation_read_the_same_identity(): void
    {
        $user = $this->member([
            'title' => 'المدرب',
            'name_ar' => 'ليلى سمير فؤاد',
            'name_en' => 'Laila Samir Fouad',
        ]);

        $profile = app(CvBuilder::class)->pulled($user, [])['profile'];

        $this->assertSame('ليلى سمير فؤاد', $profile['name']);
        $this->assertSame('Laila Samir Fouad', $profile['name_en']);
        $this->assertSame('المدرب', $profile['title']);

        $holder = app(AttestationBuilder::class)->platformRecord($user)['holder'];

        $this->assertSame('المدرب ليلى سمير فؤاد', $holder['holder_name']);
    }
}
