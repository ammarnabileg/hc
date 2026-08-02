<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Announcement;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\Complaint;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Section;

/**
 * كلّ شاشة رئيسيّة في المجال تفتح بلا كسر — شرط الجودة في دليل البناء §6.
 */
class AdminContentScreensTest extends AdminContentTestCase
{
    public function test_training_screens_render(): void
    {
        $admin = $this->admin();
        $path = LearningPath::query()->firstOrFail();
        $course = Course::query()->firstOrFail();
        $section = Section::query()->where('course_id', $course->id)->firstOrFail();
        $lesson = Lesson::query()->where('section_id', $section->id)->firstOrFail();

        $screens = [
            route('admin.paths.index'),
            route('admin.paths.courses', $path),
            route('admin.courses.index'),
            route('admin.courses.create'),
            route('admin.courses.edit', $course),
            route('admin.courses.enrollees', $course),
            route('admin.courses.stats', $course),
            route('admin.courses.preview', $course),
            route('admin.courses.audit', $course),
            route('admin.lessons.show', $lesson),
            route('admin.media.index'),
            route('admin.media.picker'),
        ];

        foreach ($screens as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_certificate_screens_render(): void
    {
        $admin = $this->admin();
        $type = CertificateType::query()->firstOrFail();
        $template = CertificateTemplate::query()->where('certificate_type_id', $type->id)->firstOrFail();

        $this->actingAs($admin)->get(route('admin.certificates.designer', $type))->assertOk();
        $this->actingAs($admin)->get(route('admin.certificates.designer', [$type, 'lang' => 'en']))->assertOk();
        $this->actingAs($admin)->get(route('admin.certificates.designer.preview', $template))->assertOk();

        $holder = $this->makeUser(['name' => 'صاحب المعاينة']);

        // التحقّق من الأكواد ⟵ المعاينة قبل الإصدار (12.5-ج)
        $this->actingAs($admin)->post(route('admin.certificates.verify-codes'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code.' KOD-MISH-MAWGOOD',
        ])->assertOk()->assertSee($holder->name);

        $this->actingAs($admin)->post(route('admin.certificates.preview'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code,
        ])->assertOk();
    }

    public function test_certificate_export_streams_csv(): void
    {
        $admin = $this->admin();
        $type = CertificateType::query()->where('key', 'course')->firstOrFail();
        $holder = $this->makeUser();

        $this->actingAs($admin)->post(route('admin.certificates.issue'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code,
        ]);

        $this->assertSame(1, Certificate::query()->count());

        $this->actingAs($admin)
            ->get(route('admin.certificates.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_guidance_detail_screens_render(): void
    {
        $admin = $this->admin();

        $announcement = Announcement::query()->firstOrFail();
        $this->actingAs($admin)->get(route('admin.guidance.analytics', $announcement))->assertOk();

        $complaint = Complaint::query()->first() ?? Complaint::create([
            'number' => 'CMP-SCREEN-1',
            'user_id' => $this->makeUser()->id,
            'type' => 'complaint',
            'title' => 'استفسار',
            'body' => 'محتاج مساعدة في التسجيل.',
            'status' => 'open',
        ]);

        $this->actingAs($admin)->get(route('admin.guidance.complaints.show', $complaint))->assertOk();
    }
}
