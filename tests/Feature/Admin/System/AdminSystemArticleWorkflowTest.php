<?php

namespace Tests\Feature\Admin\System;

use App\Models\Article;
use App\Models\User;
use App\Services\Admin\System\ArticleWorkflow;
use RuntimeException;

/**
 * دورة نشر المقالات (21.2-أ): مسودّة ⟵ قيد المراجعة ⟵ منشورة.
 * ⭐ **والكاتب لا ينشر مقاله بنفسه** — والتحقّق في الخادم لا في الواجهة.
 */
class AdminSystemArticleWorkflowTest extends SystemTestCase
{
    private const WRITER = ['articles.list', 'articles.view', 'articles.create', 'articles.edit'];

    private const EDITOR = ['articles.list', 'articles.view', 'articles.edit', 'articles.review', 'articles.publish', 'articles.archive'];

    public function test_author_cannot_publish_their_own_article_even_with_publish_permission(): void
    {
        // كاتب يملك النشر أيضًا — ومع ذلك لا ينشر مقاله هو
        $author = $this->admin(array_merge(self::WRITER, ['articles.publish']), 'كاتب له صلاحيّة نشر');
        $article = $this->article($author, ArticleWorkflow::IN_REVIEW);

        $this->actingAs($author)->post(route('admin.articles.publish', $article))
            ->assertSessionHasErrors('status');

        $this->assertSame(ArticleWorkflow::IN_REVIEW, $article->refresh()->status);
        $this->assertNull($article->published_at);
    }

    public function test_another_person_with_publish_permission_publishes_the_article(): void
    {
        $author = $this->admin(self::WRITER, 'كاتب');
        $editor = $this->admin(self::EDITOR, 'مراجع وناشر');
        $article = $this->article($author, ArticleWorkflow::IN_REVIEW);

        $this->actingAs($editor)->post(route('admin.articles.publish', $article))->assertRedirect();

        $article->refresh();

        $this->assertSame(ArticleWorkflow::PUBLISHED, $article->status);
        $this->assertSame($editor->id, $article->published_by);
        $this->assertNotNull($article->published_at);
    }

    public function test_writer_without_publish_permission_is_blocked_at_the_route(): void
    {
        $author = $this->admin(self::WRITER, 'كاتب بلا نشر');
        $article = $this->article($author, ArticleWorkflow::IN_REVIEW);

        $this->actingAs($author)->post(route('admin.articles.publish', $article))->assertForbidden();
        $this->assertSame(ArticleWorkflow::IN_REVIEW, $article->refresh()->status);
    }

    public function test_publication_cycle_runs_draft_then_review_then_published(): void
    {
        $author = $this->admin(self::WRITER, 'كاتب');
        $editor = $this->admin(self::EDITOR, 'مراجع');
        $article = $this->article($author, ArticleWorkflow::DRAFT);

        $this->actingAs($author)->post(route('admin.articles.submit', $article))->assertRedirect();
        $this->assertSame(ArticleWorkflow::IN_REVIEW, $article->refresh()->status);

        // ملاحظات مراجعة مكتوبة تُرجع المقال مسودّةً ليعدّل الكاتب
        $this->actingAs($editor)->post(route('admin.articles.review', $article), ['notes' => 'وسّع المقدّمة شويّة'])
            ->assertRedirect();

        $article->refresh();
        $this->assertSame(ArticleWorkflow::DRAFT, $article->status);
        $this->assertSame('وسّع المقدّمة شويّة', $article->review_notes);

        $this->actingAs($author)->post(route('admin.articles.submit', $article));
        $this->actingAs($editor)->post(route('admin.articles.publish', $article));

        $this->assertSame(ArticleWorkflow::PUBLISHED, $article->refresh()->status);
    }

    public function test_review_notes_are_mandatory(): void
    {
        $author = $this->admin(self::WRITER, 'كاتب');
        $editor = $this->admin(self::EDITOR, 'مراجع');
        $article = $this->article($author, ArticleWorkflow::IN_REVIEW);

        $this->actingAs($editor)->post(route('admin.articles.review', $article), ['notes' => ''])
            ->assertSessionHasErrors('notes');
    }

    /** ⭐ أرشفة لا حذف — والصفّ يبقى في قاعدة البيانات */
    public function test_articles_are_archived_never_deleted(): void
    {
        $author = $this->admin(self::WRITER, 'كاتب');
        $editor = $this->admin(self::EDITOR, 'مراجع');
        $article = $this->article($author, ArticleWorkflow::PUBLISHED);

        $this->actingAs($editor)->post(route('admin.articles.archive', $article))->assertRedirect();

        $this->assertSame(ArticleWorkflow::ARCHIVED, $article->refresh()->status);
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
    }

    /** لا نشر لمقال ليس «قيد المراجعة» — الدورة إلزاميّة لا اختياريّة */
    public function test_a_draft_cannot_jump_straight_to_published(): void
    {
        $author = $this->admin(self::WRITER, 'كاتب');
        $editor = $this->admin(self::EDITOR, 'مراجع');
        $article = $this->article($author, ArticleWorkflow::DRAFT);

        $this->expectException(RuntimeException::class);

        app(ArticleWorkflow::class)->publish($article, $editor);
    }

    public function test_publish_button_is_hidden_from_the_author_on_the_index_screen(): void
    {
        $author = $this->admin(array_merge(self::WRITER, ['articles.publish']), 'كاتب');
        $article = $this->article($author, ArticleWorkflow::IN_REVIEW);

        $this->actingAs($author)->get(route('admin.articles.index'))
            ->assertOk()
            ->assertDontSee(route('admin.articles.publish', $article));
    }

    /**
     * ⭐ 12.2.2 عن `articles.create` حرفيًّا: «إنشاء مقال جديد وتصنيفه **وربطه
     * بوسومه**»، و21.2-أ: «العنوان · الرابط (Slug) · **التصنيف والوسوم** …».
     * وكان العمود مُصادَقًا عليه بلا حقلٍ في المحرّر — والصفحة العامّة تعرض
     * كتلة وسومٍ فارغةً أبدًا.
     */
    public function test_article_tags_are_saved_read_back_and_filter_the_list(): void
    {
        $author = $this->admin(self::WRITER, 'كاتب');

        $this->actingAs($author)->post(route('admin.articles.store'), [
            'title' => 'مقال بوسوم',
            'body' => '<p>نصّ</p>',
            'tags' => 'تطوّع, شهادات ,تطوّع',
        ])->assertRedirect();

        $article = Article::query()->where('title', 'مقال بوسوم')->firstOrFail();

        // المكرّر لا يتكرّر والفراغ يُقصّ — نفس منظّف مكتبة الوسائط
        $this->assertSame(['تطوّع', 'شهادات'], $article->tags);

        // وتُقرَأ عند إعادة الفتح
        $this->actingAs($author)->get(route('admin.articles.edit', $article))
            ->assertOk()
            ->assertSee('name="tags"', false)
            ->assertSee('تطوّع,شهادات', false);

        $this->actingAs($author)->get(route('admin.articles.index', ['tag' => 'تطوّع']))
            ->assertOk()->assertSee('مقال بوسوم', false);

        $this->actingAs($author)->get(route('admin.articles.index', ['tag' => 'وسم-مش-موجود']))
            ->assertOk()->assertDontSee('مقال بوسوم', false);
    }

    /** ومحرّر المقال يفتح **نفس** بوب-أب مكتبة الوسائط للغلاف — لا منتقٍ ثانٍ (2.14-ب) */
    public function test_cover_field_opens_the_shared_media_picker(): void
    {
        $author = $this->admin(self::WRITER, 'كاتب');
        $article = $this->article($author, ArticleWorkflow::DRAFT);

        $this->actingAs($author)->get(route('admin.articles.edit', $article))
            ->assertOk()
            ->assertSee('data-media-pick="cover_path"', false)
            ->assertSee('data-picker-modal', false);
    }

    /**
     * مسار `admin.articles.categories.store` كان بتحقّقٍ على `name_ar` **وبلا
     * فورمٍ واحد في المشروع**، و`sort_order` يُقرأ في أربعة `orderBy` ولا يُكتَب.
     */
    public function test_category_form_exists_and_writes_the_sort_order(): void
    {
        $author = $this->admin(self::WRITER, 'كاتب');

        $this->actingAs($author)->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee(route('admin.articles.categories.store'), false)
            ->assertSee('name="sort_order"', false);

        $this->actingAs($author)->post(route('admin.articles.categories.store'), [
            'name_ar' => 'مسارات التعلّم',
            'sort_order' => 7,
        ])->assertRedirect();

        $this->assertDatabaseHas('article_categories', ['name_ar' => 'مسارات التعلّم', 'sort_order' => 7]);
    }

    private function article(User $author, string $status): Article
    {
        return Article::create([
            'slug' => 'maqal-'.str()->lower(str()->random(6)),
            'author_id' => $author->id,
            'title' => 'مقال تجريبيّ',
            'body' => '<p>نصّ</p>',
            'status' => $status,
        ]);
    }
}
