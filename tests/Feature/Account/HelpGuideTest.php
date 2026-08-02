<?php

namespace Tests\Feature\Account;

use App\Models\HelpArticle;

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
}
