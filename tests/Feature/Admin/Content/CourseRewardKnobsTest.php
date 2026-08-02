<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Learning\XpCalculator;
use Illuminate\Support\Facades\Schema;

/**
 * مفاتيح مكافأة التدريب في فورم الأدمن (7 · 7.1 · 2.13).
 *
 * القاعدة الذهبيّة تقول: **لكلّ ميزة إعدادات كاملة في لوحة الإدارة** — ومعناها
 * العمليّ أنّ الرقم الذي يكتبه الأدمن **يغيّر السلوك فعلًا**. وكان «أقصى XP
 * للدرس» يُكتَب في عمودٍ لا يقرؤه أحد بينما الحاسبة تقرأ عمودًا آخر: يضبط
 * الأدمن 999 ولا يتغيّر شيء. فهذه الاختبارات تقفل الدائرة من الفورم إلى
 * الحاسبة — لا من الفورم إلى العمود وحده.
 */
class CourseRewardKnobsTest extends AdminContentTestCase
{
    /** ⭐ «أقصى XP للدرس» من الفورم يغيّر ما يكسبه المتدرّب فعلًا (7 · 2.13). */
    public function test_max_lesson_xp_saved_from_the_form_changes_the_reward(): void
    {
        $course = Course::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), $this->payload($course, ['xp_max' => 999]))
            ->assertRedirect();

        $this->assertSame(999, (int) $course->refresh()->xp_max, 'الفورم لازم يكتب في `xp_max` — وهو ما تقرؤه الحاسبة');

        $this->assertSame(
            999,
            app(XpCalculator::class)->lessonXp($course, $this->enrollment($course)),
            'الرقم اللي ضبطه الأدمن لازم يوصل للمكافأة — إعدادٌ بلا أثر ممنوع (2.13)',
        );
    }

    /** ولا عمود يتيم يقرؤه أحد: `max_lesson_xp` القديم لا يقرّر شيئًا. */
    public function test_the_legacy_column_no_longer_decides_anything(): void
    {
        $course = Course::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), $this->payload($course, ['xp_max' => 120]))
            ->assertRedirect();

        if (Schema::hasColumn('courses', 'max_lesson_xp')) {
            $course->forceFill(['max_lesson_xp' => 7])->saveQuietly();
        }

        $this->assertSame(
            120,
            app(XpCalculator::class)->lessonXp($course->refresh(), $this->enrollment($course)),
            'العمود القديم لازم يفضل بلا أثر — مصدرٌ واحد للقيمة الواحدة',
        );
    }

    /**
     * ⭐ تذاكر الدرس في الفورم **Override قابل للتفريغ** (7 · 7.1):
     * الفراغ = اتبع الإعداد العامّ · الصفر = اختيارٌ صريح بلا تذاكر.
     */
    public function test_lesson_tickets_are_an_emptiable_override(): void
    {
        $course = Course::query()->firstOrFail();
        $xp = app(XpCalculator::class);

        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), $this->payload($course, ['tickets_before_half' => 5]))
            ->assertRedirect();

        $this->assertSame(5, (int) $course->refresh()->tickets_before_half);
        $this->assertSame(5, $xp->lessonTickets($course, $this->enrollment($course)));

        // الفراغ يُخزَّن NULL لا صفرًا — فيعود التدريب لاتّباع الإعداد العامّ
        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), $this->payload($course, ['tickets_before_half' => '']))
            ->assertRedirect();

        $this->assertNull($course->refresh()->tickets_before_half);
        $this->assertSame(
            (int) setting('tickets.before_half_deadline', 2),
            $xp->lessonTickets($course, $this->enrollment($course)),
        );

        // والصفر اختيارٌ صريح لا «فراغ»
        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), $this->payload($course, ['tickets_before_half' => 0]))
            ->assertRedirect();

        $this->assertSame(0, (int) $course->refresh()->tickets_before_half);
        $this->assertSame(0, $xp->lessonTickets($course, $this->enrollment($course)));
    }

    // ------------------------------------------------------------ مساعدات

    /** الحد الأدنى الذي يقبله الفورم + الحقل موضع الاختبار */
    private function payload(Course $course, array $overrides): array
    {
        return array_merge([
            'name_ar' => $course->name_ar,
            'status' => $course->status,
        ], $overrides);
    }

    /** تسجيلٌ بلا ديدلاين — فلا تناقص، والقيمة المقروءة هي القيمة القصوى نفسها */
    private function enrollment(Course $course): Enrollment
    {
        return new Enrollment([
            'user_id' => $this->makeUser()->id,
            'course_id' => $course->id,
            'started_at' => now(),
            'deadline_at' => null,
        ]);
    }
}
