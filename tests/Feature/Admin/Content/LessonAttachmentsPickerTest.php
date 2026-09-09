<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\MediaItem;
use App\Models\Section;
use Illuminate\Support\Collection;

/**
 * ⭐ **مرفقات الدرس تُختار من بوب-أب المكتبة في وضعه المتعدّد** (12.4-ج · 12.4-د).
 *
 * النصّ الحاكم حرفيًّا (12.4-د — مكتبة الوسائط):
 *  > «**العرض:** شبكة بطاقات بمعاينة … مع **بحث بالاسم** و**فلتر**…»
 *  > «**إعادة الاستخدام:** أيّ حقل رفع (غلاف · **مرفق** · صورة سؤال) يفتح
 *  >  **«اختَر من المكتبة»** أو **«ارفع جديد»** — يترفع مرّة ويُعاد استخدامه.»
 * و(12.4-ج): «**الدرس:** إمّا **فيديو** … + **مرفقات: صور/PDF/Word/صوت/MP3**».
 *
 * **العطبان اللذان أُغلِقا:**
 *  1) المرفقات كانت **قائمة Checkbox مقصوصة بـ`media.picker.limit`** (12 عنصرًا)
 *     **وبلا بحث** — فالملفّ الثالث عشر في المكتبة **لا يمكن إرفاقه أصلًا**.
 *  2) وسيط `$multiple` الذي يمرّره `MediaController@picker` كان **بلا قارئ
 *     واحد** — وسيطٌ ميّت يَعِد بوضعٍ لا وجود له.
 *
 * والطفرات التي يمسكها هذا الملفّ:
 *  - إعادةُ قصّ المكتبة إلى 12 في الشاشة ⟵ يسقط `the_thirteenth_file_can_be_attached`.
 *  - إسقاطُ `$multiple` من قالب المنتقي ⟵ يسقط `the_picker_reads_the_multiple_flag`.
 *  - جعلُ الغياب ≠ تفريغ ⟵ يسقط `clearing_every_attachment_actually_clears_them`.
 */
class LessonAttachmentsPickerTest extends AdminContentTestCase
{
    /** ⭐ الوسيط `$multiple` صار **مقروءًا**: الوضع يغيّر مخرَج المنتقي فعلًا. */
    public function test_the_picker_reads_the_multiple_flag(): void
    {
        $this->seedLibrary(3);
        $admin = $this->admin();

        $multi = $this->actingAs($admin)
            ->get(route('admin.media.picker', ['fragment' => 1, 'multiple' => 1, 'target' => 'attachment_ids']))
            ->assertOk()->getContent();

        $single = $this->actingAs($admin)
            ->get(route('admin.media.picker', ['fragment' => 1, 'target' => 'cover_path']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-picker-multiple', $multi,
            'الوضع المتعدّد لا يغيّر شيئًا في المخرَج — فـ`$multiple` وسيطٌ بلا قارئ.');
        $this->assertStringContainsString('data-pick-mark', $multi);
        $this->assertStringContainsString('name="multiple" value="1"', $multi,
            'الوضع لا يسافر مع البحث والترقيم — فالمتعدّد ينقلب مفردًا في الصفحة الثانية.');

        $this->assertStringNotContainsString('data-picker-multiple', $single,
            'الوضع المفرد يرسم علامات الاختيار المتعدّد — فالوسيط لا أثر له.');
    }

    /** ⭐ شاشة الدرس تفتح البوب-أب المتعدّد ولا تطبع قائمةً مقصوصة. */
    public function test_the_lesson_screen_opens_the_shared_popup(): void
    {
        $this->seedLibrary(30);
        $lesson = $this->anyLesson();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.lessons.show', $lesson))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-media-pick-multiple="attachment_ids"', $html);
        $this->assertStringContainsString('data-media-multi="attachment_ids"', $html);
        $this->assertStringContainsString('data-picker-modal', $html,
            'البوب-أب المشترك غائبٌ عن الشاشة — فالزرّ يضغط على لا شيء (2.14-ب).');

        // ⛔ ولا قائمة Checkbox مطبوعة للمكتبة كلّها: 30 ملفًّا لا تُطبَع في صفحة (2.7)
        $this->assertLessThanOrEqual(1, substr_count($html, 'name="attachment_ids[]"'),
            'المكتبة ما زالت تُطبَع قائمةً في الصفحة — وهو ما قصّها على 12 وحرم الثالث عشر.');
    }

    /**
     * ⭐ **الملفّ الثالث عشر يمكن إرفاقه** — والحدّ القديم يبقى نافذًا لغيره،
     * فالاختبار يثبت أنّ الشاشة لم تعد رهينته.
     */
    public function test_the_thirteenth_file_can_be_attached(): void
    {
        $items = $this->seedLibrary(30);
        $limit = (int) setting('media.picker.limit', 12);

        $beyond = $items->skip($limit)->take(2)->pluck('id')->all();
        $this->assertCount(2, $beyond, 'المكتبة أصغر من الحدّ — فالاختبار لا يقيس الفجوة.');

        $lesson = $this->anyLesson();

        // ١) البحث داخل البوب-أب يصل إليه — وهو الطريق الوحيد لملفٍّ خارج الحدّ
        $wanted = MediaItem::query()->whereKey($beyond[0])->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.media.picker', ['fragment' => 1, 'multiple' => 1, 'q' => $wanted->name]))
            ->assertOk()
            ->assertSee('data-pick-id="'.$wanted->id.'"', false);

        // ٢) وحفظُ الدرس يُثبِته مرفقًا فعلًا
        $this->actingAs($this->admin())
            ->put(route('admin.lessons.update', $lesson), $this->lessonPayload($lesson, [
                'attachment_ids' => $beyond,
            ]))->assertRedirect();

        $this->assertSame(
            $beyond,
            LessonAttachment::query()->where('lesson_id', $lesson->id)->orderBy('media_item_id')->pluck('media_item_id')->sort()->values()->all(),
            'ملفٌّ خارج حدّ الاثني عشر لم يُرفَق — وهي الفجوة نفسها.',
        );
    }

    /**
     * ⭐ فجوة `settings:coverage --dead`: `media.picker.attachments_empty` كان
     * مزروعًا بلا قارئ — درسٌ بلا مرفقات كان يطبع صفًّا فارغًا بلا أيّ نصّ.
     */
    public function test_the_empty_state_text_shows_when_the_lesson_has_no_attachments(): void
    {
        $lesson = $this->anyLesson();
        $this->assertSame(0, LessonAttachment::query()->where('lesson_id', $lesson->id)->count(),
            'الدرس عنده مرفقات فعلًا — فالاختبار لا يقيس الحالة الفارغة.');

        $html = $this->actingAs($this->admin())
            ->get(route('admin.lessons.show', $lesson))
            ->assertOk()->getContent();

        $this->assertStringContainsString(setting('media.picker.attachments_empty'), $html,
            'نصّ «لا مرفقات» مزروعٌ في الإعدادات لكنّه لا يُطبَع — الإعداد بلا قارئ.');

        // والنصّ مطبوعٌ ظاهرًا لا بصنف `hidden` — فالحالة الفارغة فعلًا فارغة
        $this->assertStringNotContainsString('hidden', $this->emptyStateTag($html),
            'النصّ مطبوعٌ لكنّه مخفيٌّ بصنف hidden رغم عدم وجود مرفقات.');
    }

    /** ⭐ وحين توجد مرفقات، نصّ الحالة الفارغة موجودٌ في الشجرة لكن مخفيّ — لا يظهر مكرَّرًا بجوار الرقائق. */
    public function test_the_empty_state_text_stays_hidden_when_the_lesson_has_attachments(): void
    {
        $items = $this->seedLibrary(1);
        $lesson = $this->anyLesson();

        $this->actingAs($this->admin())
            ->put(route('admin.lessons.update', $lesson), $this->lessonPayload($lesson, [
                'attachment_ids' => [$items->first()->id],
            ]))->assertRedirect();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.lessons.show', $lesson))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-multi-id="'.$items->first()->id.'"', $html);
        $this->assertStringContainsString('hidden', $this->emptyStateTag($html),
            'النصّ الفارغ ظاهرٌ رغم وجود مرفق فعليّ — فسيظهر مكرَّرًا بجوار الرقاقة.');
    }

    /** ⚠️ **الغياب تفريغٌ لا سهو**: إزالة آخر مرفق تصل الخادم فعلًا. */
    public function test_clearing_every_attachment_actually_clears_them(): void
    {
        $items = $this->seedLibrary(3);
        $lesson = $this->anyLesson();

        $this->actingAs($this->admin())
            ->put(route('admin.lessons.update', $lesson), $this->lessonPayload($lesson, [
                'attachment_ids' => [$items->first()->id],
            ]))->assertRedirect();

        $this->assertSame(1, LessonAttachment::query()->where('lesson_id', $lesson->id)->count());

        // الفورم يُرسَل بلا `attachment_ids` إطلاقًا — كما يفعل HTML بالمصفوفة الفارغة
        $this->actingAs($this->admin())
            ->put(route('admin.lessons.update', $lesson), $this->lessonPayload($lesson))
            ->assertRedirect();

        $this->assertSame(0, LessonAttachment::query()->where('lesson_id', $lesson->id)->count(),
            'المرفق نجا من الإزالة — فالأدمن شال ولا شيء حدث (2.17-ب).');
    }

    // ------------------------------------------------------------------ أدوات

    /** @return Collection<int, MediaItem> */
    private function seedLibrary(int $count): Collection
    {
        return collect(range(1, $count))->map(fn (int $i) => MediaItem::create([
            'name' => 'ملفّ المكتبة رقم '.$i,
            'disk' => 'public',
            'path' => 'media/library-'.$i.'.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'hash' => hash('sha256', 'library-'.$i),
        ]));
    }

    /** يعزل وسم نصّ الحالة الفارغة `data-multi-empty` من صفحة الدرس لفحص صنفه. */
    private function emptyStateTag(string $html): string
    {
        $this->assertMatchesRegularExpression('/<span[^>]*data-multi-empty[^>]*>/', $html,
            'وسم `data-multi-empty` غائبٌ عن الصفحة إطلاقًا.');

        preg_match('/<span[^>]*data-multi-empty[^>]*>/', $html, $matches);

        return $matches[0];
    }

    private function anyLesson(): Lesson
    {
        $course = Course::query()->firstOrFail();
        $section = Section::query()->where('course_id', $course->id)->firstOrFail();

        return Lesson::query()->where('section_id', $section->id)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function lessonPayload(Lesson $lesson, array $overrides = []): array
    {
        return [
            'title_ar' => $lesson->title_ar,
            'type' => $lesson->type,
            // علَم «هذا الفورم يدير المرفقات» — يفرّق بين «لم يُرسَل» و«فُرِّغ»
            'attachments_managed' => 1,
            ...$overrides,
        ];
    }
}
