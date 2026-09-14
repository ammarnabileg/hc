<?php

namespace Tests\Feature\Account;

use App\Models\HelpArticle;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * دليل المستخدم (الدستور 24.5): إجابة سريعة بلا تذكرة.
 */
class HelpGuideTest extends AccountTestCase
{
    private function article(array $attributes = []): HelpArticle
    {
        return HelpArticle::create([
            'slug' => $attributes['slug'] ?? 'how-to-download-certificate',
            'category' => $attributes['category'] ?? 'الشهادات',
            'title' => $attributes['title'] ?? 'إزاي أحمّل شهادتي؟',
            'body' => $attributes['body'] ?? 'افتح «تعلّمي ← شهاداتي» واضغط تحميل.',
            'status' => $attributes['status'] ?? 'published',
        ]);
    }

    /** يكتب قيمة إعداد مباشرةً — بلا المرور بشاشة الأدمن (2.13). */
    private function setSetting(string $key, string $value, string $type = 'bool'): void
    {
        Setting::updateOrCreate(['key' => $key], ['group' => 'help', 'label_ar' => $key, 'type' => $type, 'value' => $value]);
        Cache::forget('settings');
    }

    public function test_search_and_categories_show_published_articles_only(): void
    {
        $user = $this->trainee();
        $this->article();
        $this->article(['slug' => 'draft-one', 'title' => 'مقال مسودّة', 'status' => 'draft']);

        $this->actingAs($user)->get(route('help.index'))
            ->assertOk()
            ->assertSee('دليل المستخدم')
            ->assertSee('إزاي أحمّل شهادتي؟')
            ->assertDontSee('مقال مسودّة');

        $this->actingAs($user)->get(route('help.index', ['q' => 'شهادتي']))
            ->assertOk()
            ->assertSee('إزاي أحمّل شهادتي؟');
    }

    public function test_helpful_vote_increments_the_counter_once_per_session(): void
    {
        $user = $this->trainee();
        $article = $this->article();

        $this->actingAs($user)->post(route('help.feedback', $article->slug), ['helpful' => 'yes'])->assertRedirect();
        $this->assertSame(1, $article->fresh()->helpful_yes);

        // التصويت مرّة واحدة لكلّ جلسة على نفس المقال
        $this->actingAs($user)->post(route('help.feedback', $article->slug), ['helpful' => 'yes']);
        $this->assertSame(1, $article->fresh()->helpful_yes);
    }

    public function test_not_helpful_vote_increments_the_other_counter(): void
    {
        $user = $this->trainee();
        $article = $this->article();

        $this->actingAs($user)->post(route('help.feedback', $article->slug), ['helpful' => 'no'])->assertRedirect();
        $this->assertSame(1, $article->fresh()->helpful_no);
    }

    public function test_i_did_not_find_my_answer_opens_a_prefilled_ticket(): void
    {
        $user = $this->trainee();
        $article = $this->article();

        // المقال يحمل رابط «لم أجد إجابتي» بعنوان مملوء مسبقًا (24.5)
        $this->actingAs($user)->get(route('help.show', $article->slug))
            ->assertOk()
            ->assertSee('هل كان مفيدًا؟')
            ->assertSee('لم أجد إجابتي');

        $this->actingAs($user)
            ->get(route('complaints.index', ['new' => 1, 'title' => 'استفسار حول: '.$article->title]))
            ->assertOk()
            ->assertViewHas('prefillTitle', 'استفسار حول: '.$article->title)
            ->assertViewHas('openNew', true)
            // البوب-أب يفتح تلقائيًّا والعنوان جاهز في الحقل
            ->assertSee("getElementById('new-ticket')", false)
            ->assertSee('value="استفسار حول: '.$article->title.'"', false);
    }

    // ============================================================== بلوك الإعدادات (12.6-ج سطر 5087)

    /** ⭐ Toggle البحث موقوفًا ⟵ صندوق البحث يختفي كلّه — لا يتعطّل فقط (2.15-أ-7). */
    public function test_search_box_is_hidden_when_the_search_toggle_is_off(): void
    {
        $user = $this->trainee();
        $this->article();

        // ON (الافتراضيّ): الصندوق ظاهر
        $this->actingAs($user)->get(route('help.index'))
            ->assertOk()
            ->assertSee('تدوّر على إيه؟');

        $this->setSetting('help.search_enabled', '0');

        $response = $this->actingAs($user)->get(route('help.index'))->assertOk();
        $response->assertDontSee('تدوّر على إيه؟');
        // والمقالات ما زالت تُعرَض — الموقوف هو البحث وحده لا الصفحة كلّها
        $response->assertSee('إزاي أحمّل شهادتي؟');
    }

    /** والبحث الموقوف مُهمَلٌ خادميًّا أيضًا — مش بس مخفيّ بصريًّا. */
    public function test_search_query_is_ignored_server_side_when_search_is_off(): void
    {
        $user = $this->trainee();
        $this->article();
        $this->article(['slug' => 'other', 'title' => 'مقال تاني تمامًا']);

        $this->setSetting('help.search_enabled', '0');

        // لو البحث كان شغّالًا فعلًا لظهر مقال واحد بس — وهنا يظهر الاثنان
        $this->actingAs($user)->get(route('help.index', ['q' => 'شهادتي']))
            ->assertOk()
            ->assertSee('إزاي أحمّل شهادتي؟')
            ->assertSee('مقال تاني تمامًا');
    }

    /** ⭐ Toggle «هل كان مفيدًا؟» موقوفًا ⟵ القسم يختفي كلّه (2.15-أ-7). */
    public function test_helpful_prompt_is_hidden_when_the_feedback_toggle_is_off(): void
    {
        $user = $this->trainee();
        $article = $this->article();

        $this->actingAs($user)->get(route('help.show', $article->slug))
            ->assertOk()
            ->assertSee('هل كان مفيدًا؟');

        $this->setSetting('help.feedback_enabled', '0');

        $this->actingAs($user)->get(route('help.show', $article->slug))
            ->assertOk()
            ->assertDontSee('هل كان مفيدًا؟')
            // و«لم أجد إجابتي» يبقى ظاهرًا — قسمٌ مستقلّ لا يتأثّر بتوجّل التقييم
            ->assertSee('لم أجد إجابتي');
    }

    /** والتصويت الموقوف مرفوضٌ خادميًّا أيضًا — لا يُسجَّل من طلبٍ يدويّ. */
    public function test_feedback_vote_is_rejected_server_side_when_feedback_is_off(): void
    {
        $user = $this->trainee();
        $article = $this->article();

        $this->setSetting('help.feedback_enabled', '0');

        $this->actingAs($user)
            ->post(route('help.feedback', $article->slug), ['helpful' => 'yes'])
            ->assertNotFound();

        $this->assertSame(0, $article->fresh()->helpful_yes);
    }

    /**
     * ⭐ Toggle عرض التصنيفات في الشريط موقوفًا ⟵ شريط الرقائق نفسه يختفي
     * (12.6-ج سطر 5087) — لا لافتة التصنيف المطبوعة على كارت كلّ مقال، فتلك
     * جزءٌ من بيانات المقال نفسه لا من شريط التصفية.
     */
    public function test_category_chips_bar_is_hidden_when_the_sidebar_categories_toggle_is_off(): void
    {
        $user = $this->trainee();
        $this->article();

        // ON (الافتراضيّ): شريط «كلّ التصنيفات» ظاهر
        $this->actingAs($user)->get(route('help.index'))
            ->assertOk()
            ->assertSee('كلّ التصنيفات');

        $this->setSetting('help.sidebar_categories_enabled', '0');

        $response = $this->actingAs($user)->get(route('help.index'))->assertOk();
        $response->assertDontSee('كلّ التصنيفات');
        // والمقال نفسه يبقى ظاهرًا بتصنيفه — الموقوف شريط التصفية وحده
        $response->assertSee('إزاي أحمّل شهادتي؟');
    }

    /** عدد المقالات/صفحة (افتراضيّ 12) يضبطه الأدمن — والصفحة الثانية تحمل الباقي فعلًا. */
    public function test_page_size_setting_controls_pagination(): void
    {
        $user = $this->trainee();

        for ($i = 1; $i <= 3; $i++) {
            $this->article(['slug' => 'art-'.$i, 'title' => 'مقال رقم '.$i]);
        }

        $this->setSetting('account.help.page_size', '2', 'number');

        $page1 = $this->actingAs($user)->get(route('help.index'))->assertOk();
        $titlesPage1 = collect($page1->viewData('articles')->items())->pluck('title')->all();
        $this->assertCount(2, $titlesPage1);

        $page2 = $this->actingAs($user)->get(route('help.index', ['page' => 2]))->assertOk();
        $titlesPage2 = collect($page2->viewData('articles')->items())->pluck('title')->all();
        $this->assertCount(1, $titlesPage2);

        // كلّ المقالات الثلاثة ظهرت — موزَّعةً على صفحتين بحدّ 2/صفحة، بلا تكرار ولا فقد
        $this->assertEqualsCanonicalizing(
            ['مقال رقم 1', 'مقال رقم 2', 'مقال رقم 3'],
            array_merge($titlesPage1, $titlesPage2),
        );
    }
}
