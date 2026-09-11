<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\CertificateAccreditation;
use App\Models\CertificateType;
use App\Models\HelpArticle;
use Illuminate\Testing\TestResponse;

/**
 * ⭐ [2026-09-11] **الفرز بالأعمدة** في جداول 12.6-أ (التعليمات) و12.6-ج
 * (دليل المستخدم) و12.5-أ (الاعتمادات).
 *
 * الجداول نفسها كانت موجودةً بأعمدتها المنصوصة — الناقص أنّ رؤوسها كانت
 * **نصًّا ميّتًا**: لا يُرتَّب بها شيء، فالأدمن أمام قائمةٍ ترتيبُها الوحيد
 * «الأحدث» مهما كبرت. وهنا نثبت ثلاثة أشياء لكلّ شاشة:
 *
 * 1. كلّ عمودٍ منصوصٍ **رأسُه قابلٌ للفرز** (`data-sort-key` + `aria-sort`)،
 * 2. والفرز **يقلب الترتيب فعلًا** لا أن يعيد الصفحة كما هي،
 * 3. و**إجراءات الصفّ كلّها باقية** بعد التحويل — لا إجراء ضاع.
 *
 * والفرز على **الخادم** بسلسلة الاستعلام، فيشمل كلّ الصفوف لا الصفحة الظاهرة
 * وحدها، ويعمل بلا جافاسكربت.
 */
class AdminTableColumnSortingTest extends AdminContentTestCase
{
    /** موضع أوّل ظهورٍ لنصٍّ في جسم الاستجابة — به نقيس «مَن قبل مَن». */
    private function positionOf(TestResponse $response, string $needle): int
    {
        $at = strpos($response->getContent(), $needle);

        $this->assertNotFalse($at, "النصّ «{$needle}» غير موجودٍ في الصفحة أصلًا.");

        return (int) $at;
    }

    private function assertComesBefore(TestResponse $response, string $first, string $second): void
    {
        $this->assertLessThan(
            $this->positionOf($response, $second),
            $this->positionOf($response, $first),
            "المتوقَّع أن يسبق «{$first}» «{$second}» في ترتيب الجدول.",
        );
    }

    /** كلّ رأسٍ في القائمة موجودٌ برمز عموده وبحالة فرزٍ معلنة (a11y). */
    private function assertSortableHeaders(TestResponse $response, array $keys): void
    {
        foreach ($keys as $key) {
            $response->assertSee('data-sort-key="'.$key.'"', false);
            $response->assertSee('sort='.$key, false);
        }

        $response->assertSee('aria-sort=', false);
    }

    // ======================================================= 12.6-أ التعليمات

    private function announcement(string $title, array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'title' => $title,
            'status' => 'published',
            'audience' => ['type' => 'all'],
        ]);
    }

    public function test_announcements_table_headers_are_all_sortable(): void
    {
        $this->announcement('منشور الفرز');

        $response = $this->actingAs($this->admin())->get(route('admin.guidance.index'))->assertOk();

        $this->assertSortableHeaders($response, ['title', 'type', 'audience', 'status', 'rate', 'acks', 'pinned']);

        // و«إجراءات» عمودٌ مسمًّى لا رأسٌ فارغ — 12.6-أ ينصّ عليه بالاسم.
        $response->assertSee(setting('admin.guidance.index.ijraat', 'إجراءات'), false);
    }

    public function test_sorting_announcements_by_title_flips_the_order(): void
    {
        $this->announcement('ياسمين');
        $this->announcement('ألف');

        $admin = $this->admin();

        $asc = $this->actingAs($admin)->get(route('admin.guidance.index', ['sort' => 'title', 'dir' => 'asc']))->assertOk();
        $this->assertComesBefore($asc, 'ألف', 'ياسمين');

        $desc = $this->actingAs($admin)->get(route('admin.guidance.index', ['sort' => 'title', 'dir' => 'desc']))->assertOk();
        $this->assertComesBefore($desc, 'ياسمين', 'ألف');
    }

    /** «نسبة القراءة» تُرتَّب بعدد القراءات الحقيقيّ لا بترتيب الإنشاء (12.6-أ). */
    public function test_sorting_announcements_by_read_rate_uses_real_reads(): void
    {
        $quiet = $this->announcement('منشور شبه مقروء');
        $popular = $this->announcement('منشور مقروء كتير');

        foreach (range(1, 3) as $i) {
            AnnouncementRead::create([
                'announcement_id' => $popular->id,
                'user_id' => $this->makeUser()->id,
                'read_at' => now(),
            ]);
        }

        AnnouncementRead::create([
            'announcement_id' => $quiet->id,
            'user_id' => $this->makeUser()->id,
            'read_at' => now(),
        ]);

        $admin = $this->admin();

        $desc = $this->actingAs($admin)->get(route('admin.guidance.index', ['sort' => 'rate', 'dir' => 'desc']))->assertOk();
        $this->assertComesBefore($desc, 'منشور مقروء كتير', 'منشور شبه مقروء');

        $asc = $this->actingAs($admin)->get(route('admin.guidance.index', ['sort' => 'rate', 'dir' => 'asc']))->assertOk();
        $this->assertComesBefore($asc, 'منشور شبه مقروء', 'منشور مقروء كتير');
    }

    /** ⚠️ حارس: سلسلة الاستعلام مدخلُ مستخدم — عمودٌ مجهول يُهمَل ولا يصل SQL. */
    public function test_an_unknown_sort_column_is_ignored_not_executed(): void
    {
        $this->announcement('منشور آمن');
        $before = Announcement::query()->count();

        $this->actingAs($this->admin())
            ->get(route('admin.guidance.index', ['sort' => 'title) --', 'dir' => 'asc; drop table announcements']))
            ->assertOk()
            ->assertSee('منشور آمن');

        // الجدول قائمٌ وصفوفُه كما هي — لا استعلامَ بُني من نصّ المستخدم.
        $this->assertSame($before, Announcement::query()->count());
    }

    /** كلّ إجراءات الصفّ باقيةٌ بعد جعل الرؤوس قابلةً للفرز — لا إجراء ضاع. */
    public function test_announcement_row_actions_survive_the_sortable_headers(): void
    {
        $announcement = $this->announcement('منشور بإجراءاته');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.guidance.index', ['sort' => 'title', 'dir' => 'asc']))
            ->assertOk();

        $response->assertSee(route('admin.guidance.analytics', $announcement), false);
        $response->assertSee(route('admin.guidance.preview', $announcement), false);
        $response->assertSee(route('admin.guidance.announcements.duplicate', $announcement), false);
        $response->assertSee(route('admin.guidance.announcements.archive', $announcement), false);
    }

    // ================================================== 12.6-ج دليل المستخدم

    private function article(string $title, array $attributes = []): HelpArticle
    {
        return HelpArticle::create($attributes + [
            'title' => $title,
            'slug' => str()->random(12),
            'status' => 'published',
        ]);
    }

    public function test_help_articles_table_headers_are_all_sortable(): void
    {
        $this->article('دليل الفرز');

        $response = $this->actingAs($this->admin())->get(route('admin.guidance.help'))->assertOk();

        $this->assertSortableHeaders($response, ['title', 'category', 'status']);

        $response->assertSee(setting('admin.guidance.help.ijraat', 'إجراءات'), false);
    }

    public function test_sorting_help_articles_by_category_flips_the_order(): void
    {
        $this->article('دليل الياء', ['category' => 'يوميّات']);
        $this->article('دليل الألف', ['category' => 'ابتداء']);

        $admin = $this->admin();

        $asc = $this->actingAs($admin)->get(route('admin.guidance.help', ['sort' => 'category', 'dir' => 'asc']))->assertOk();
        $this->assertComesBefore($asc, 'دليل الألف', 'دليل الياء');

        $desc = $this->actingAs($admin)->get(route('admin.guidance.help', ['sort' => 'category', 'dir' => 'desc']))->assertOk();
        $this->assertComesBefore($desc, 'دليل الياء', 'دليل الألف');
    }

    public function test_help_article_row_actions_survive_the_sortable_headers(): void
    {
        $article = $this->article('دليل بإجراءاته');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.guidance.help', ['sort' => 'title', 'dir' => 'asc']))
            ->assertOk();

        // «تعديل» يفتح البوب-أب بحمولة الصفّ، و«حذف» فورمٌ على مسارها.
        $response->assertSee('data-modal-open="article-form"', false);
        $response->assertSee(route('admin.guidance.help.update', $article), false);
        $response->assertSee(route('admin.guidance.help.destroy', $article), false);
    }

    // ==================================================== 12.5-أ الاعتمادات

    public function test_accreditations_table_headers_are_all_sortable(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'accreditations']))
            ->assertOk();

        $this->assertSortableHeaders($response, ['logo', 'name', 'types', 'issued', 'status']);

        $response->assertSee(setting('admin.certificates.partials.accreditations.ijraat', 'إجراءات'), false);
    }

    public function test_sorting_accreditations_by_name_flips_the_order(): void
    {
        CertificateAccreditation::create(['name_ar' => 'يمنى للاعتماد', 'name_en' => 'Yumna Body', 'is_active' => true]);
        CertificateAccreditation::create(['name_ar' => 'ابتدائيّة للاعتماد', 'name_en' => 'Alpha Body', 'is_active' => true]);

        $admin = $this->admin();

        $asc = $this->actingAs($admin)
            ->get(route('admin.certificates.index', ['tab' => 'accreditations', 'sort' => 'name', 'dir' => 'asc']))
            ->assertOk();
        $this->assertComesBefore($asc, 'ابتدائيّة للاعتماد', 'يمنى للاعتماد');

        $desc = $this->actingAs($admin)
            ->get(route('admin.certificates.index', ['tab' => 'accreditations', 'sort' => 'name', 'dir' => 'desc']))
            ->assertOk();
        $this->assertComesBefore($desc, 'يمنى للاعتماد', 'ابتدائيّة للاعتماد');
    }

    /** عمود «كم نوع» عدٌّ محسوبٌ خارج الجدول — ومع ذلك يُفرَز به فعلًا. */
    public function test_sorting_accreditations_by_type_count_orders_by_the_real_count(): void
    {
        $busy = CertificateAccreditation::create(['name_ar' => 'جهة مشغولة', 'name_en' => 'Busy Body', 'is_active' => true]);
        $idle = CertificateAccreditation::create(['name_ar' => 'جهة فاضية', 'name_en' => 'Idle Body', 'is_active' => true]);

        CertificateType::query()->update(['accreditation_id' => $busy->id]);

        $this->assertSame(0, CertificateType::query()->where('accreditation_id', $idle->id)->count());
        $this->assertGreaterThan(0, CertificateType::query()->where('accreditation_id', $busy->id)->count());

        $admin = $this->admin();

        $desc = $this->actingAs($admin)
            ->get(route('admin.certificates.index', ['tab' => 'accreditations', 'sort' => 'types', 'dir' => 'desc']))
            ->assertOk();
        $this->assertComesBefore($desc, 'جهة مشغولة', 'جهة فاضية');

        $asc = $this->actingAs($admin)
            ->get(route('admin.certificates.index', ['tab' => 'accreditations', 'sort' => 'types', 'dir' => 'asc']))
            ->assertOk();
        $this->assertComesBefore($asc, 'جهة فاضية', 'جهة مشغولة');
    }

    public function test_accreditation_row_actions_survive_the_sortable_headers(): void
    {
        $accreditation = CertificateAccreditation::create(['name_ar' => 'جهة بإجراءاتها', 'name_en' => 'Actions Body', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'accreditations', 'sort' => 'name', 'dir' => 'asc']))
            ->assertOk();

        $response->assertSee('data-modal-open="accreditation-edit-'.$accreditation->id.'"', false);
        $response->assertSee(route('admin.certificates.accreditations.update', $accreditation), false);
        $response->assertSee(route('admin.certificates.accreditations.destroy', $accreditation), false);
    }

    /** الفرز لا يبتلع الفلتر: البحث يبقى في الرابط بعد النقر على رأس العمود. */
    public function test_a_sort_link_keeps_the_active_filter(): void
    {
        $this->announcement('منشور مفلتَر');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.guidance.index', ['q' => 'مفلتَر', 'sort' => 'status', 'dir' => 'asc']))
            ->assertOk();

        $response->assertSee('q=', false);
        $response->assertSee('sort=title', false);
    }
}
