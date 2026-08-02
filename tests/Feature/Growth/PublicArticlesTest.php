<?php

namespace Tests\Feature\Growth;

use App\Models\Article;
use App\Models\User;
use App\Services\Admin\System\ArticleWorkflow;
use Illuminate\Support\Str;

/**
 * الواجهة العامّة لمركز المقالات (21.2-أ) — أخطر فجوة كانت في المجال:
 * دورة تحريريّة كاملة تنتج محتوًى لا يراه أحد.
 */
class PublicArticlesTest extends GrowthTestCase
{
    private function article(string $status, string $slug = 'maqal-mansor'): Article
    {
        $author = User::create([
            'name' => 'كاتب المقال',
            'email' => Str::random(8).'@test.local',
            'password' => 'secret-password',
            'code' => 'U'.Str::upper(Str::random(7)),
            'status' => 'active',
        ]);

        return Article::create([
            'slug' => $slug,
            'author_id' => $author->id,
            'title' => 'إزّاي تبدأ تتعلّم',
            'excerpt' => 'مقتطف قصير للاختبار.',
            'body' => '<p>محتوى المقال.</p><script>alert(1)</script>',
            'status' => $status,
            'published_at' => $status === ArticleWorkflow::PUBLISHED ? now()->subDay() : null,
        ]);
    }

    public function test_published_article_opens_publicly_without_login(): void
    {
        $article = $this->article(ArticleWorkflow::PUBLISHED);

        $this->get(route('growth.articles.show', $article->slug))
            ->assertOk()
            ->assertSee('إزّاي تبدأ تتعلّم')
            ->assertSee('كاتب المقال')
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"Article"', false)
            ->assertSee(route('growth.og.article', $article->slug), false);
    }

    /** ⛔ المسودّة غير موجودة أصلًا بالنسبة للزائر — 404 لا 403 */
    public function test_draft_article_is_404_for_a_visitor(): void
    {
        $article = $this->article(ArticleWorkflow::DRAFT, 'mosawada');

        $this->get(route('growth.articles.show', $article->slug))->assertNotFound();
    }

    public function test_in_review_and_archived_articles_are_404(): void
    {
        $this->get(route('growth.articles.show', $this->article(ArticleWorkflow::IN_REVIEW, 'qeid-morag3a')->slug))
            ->assertNotFound();

        $this->get(route('growth.articles.show', $this->article(ArticleWorkflow::ARCHIVED, 'mo2arshaf')->slug))
            ->assertNotFound();
    }

    /** محتوى المقال منقّى قبل العرض — والصفحة عامّة فالوسم التنفيذيّ ثغرة على كلّ زائر */
    public function test_article_body_is_sanitised(): void
    {
        $article = $this->article(ArticleWorkflow::PUBLISHED, 'maqal-nazif');

        $this->get(route('growth.articles.show', $article->slug))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_index_lists_published_only(): void
    {
        $this->article(ArticleWorkflow::PUBLISHED, 'mansor-1');
        $this->article(ArticleWorkflow::DRAFT, 'draft-1');

        $response = $this->get(route('growth.articles.index'))->assertOk();

        $this->assertStringContainsString('mansor-1', $response->getContent());
        $this->assertStringNotContainsString('draft-1', $response->getContent());
    }

    /** صورة OG لكلّ رابط مقال (21.1-أ) */
    public function test_article_og_card_renders_svg_for_published_only(): void
    {
        $published = $this->article(ArticleWorkflow::PUBLISHED, 'og-mansor');
        $draft = $this->article(ArticleWorkflow::DRAFT, 'og-mosawada');

        $this->get(route('growth.og.article', $published->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');

        $this->get(route('growth.og.article', $draft->slug))->assertNotFound();
    }
}
