<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Course;
use App\Models\LearningPath;
use App\Services\Admin\System\ArticleWorkflow;
use App\Services\Growth\SafeHtml;
use App\Services\Growth\UtmBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ⭐ **الواجهة العامّة لمركز المقالات** (21.2-أ).
 *
 * كانت دورة النشر كاملةً ومختبَرة — مسودّة ⟵ مراجعة ⟵ نشرٌ بشخصٍ ثانٍ — لكنّ
 * مسارات المقالات كلّها تحت `admin/`، **فالمحتوى الذي أنتجته الدورة لا يراه أحد**.
 * وهذه الصفحة هي الباب: **مفهرسة**، بصورة OG، وأزرار مشاركة، واسم الكاتب ورابط
 * بروفايله، وتاريخ النشر، و`Schema.org Article`.
 *
 * والقاعدة الحاكمة: **المنشور وحده يُفتَح** — والمسودّة والمراجعة والمؤرشف **404**،
 * لأنّ الحاجز في الخادم لا في إخفاء الرابط.
 */
class ArticleController extends Controller
{
    public function __construct(
        private readonly SafeHtml $safe,
        private readonly UtmBuilder $utm,
    ) {}

    public function index(Request $request): View
    {
        $categoryId = $request->integer('category') ?: null;
        $term = $request->string('q')->trim()->value();

        $articles = $this->published()
            ->with(['author:id,code,name,avatar_path', 'article_category:id,name_ar'])
            ->when($categoryId, fn ($q) => $q->where('article_category_id', $categoryId))
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")))
            ->orderByDesc('published_at')
            ->paginate((int) setting('growth.articles.per_page', 12))
            ->withQueryString();

        return view('growth.articles.index', [
            'articles' => $articles,
            'categories' => ArticleCategory::query()->orderBy('sort_order')->get(['id', 'name_ar']),
            'category' => $categoryId,
            'term' => $term,
            'indexable' => (bool) setting('growth.seo.index_articles', true),
            'ogImage' => route('growth.og.articles'),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        // ⛔ المنشور وحده — وغيره غير موجود من أصله بالنسبة للزائر
        $article = $this->published()
            ->with(['author:id,code,name,avatar_path', 'article_category:id,name_ar', 'related'])
            ->where('slug', $slug)
            ->firstOrFail();

        $url = route('growth.articles.show', $article->slug);

        return view('growth.articles.show', [
            'article' => $article,
            'body' => $this->safe->clean($article->body),
            'url' => $url,
            'ogImage' => route('growth.og.article', $article->slug),
            'indexable' => (bool) setting('growth.seo.index_articles', true),
            'showAuthor' => (bool) setting('growth.articles.show_author', true),
            // ⭐ UTM موحّد على كلّ رابط تولّده المنصّة (21.2-ح)
            'shareUrl' => $this->utm->tag($url, 'article', 'article_share', $article->slug),
            'schema' => $this->schema($article, $url),
            'related' => $this->relatedUrl($article),
            'more' => $this->published()
                ->where('id', '!=', $article->id)
                ->when($article->article_category_id, fn ($q) => $q->where('article_category_id', $article->article_category_id))
                ->orderByDesc('published_at')
                ->limit((int) setting('growth.articles.related_count', 3))
                ->get(['id', 'slug', 'title', 'excerpt', 'published_at']),
        ]);
    }

    /** الاستعلام الوحيد الذي يُسمَح للعامّة برؤيته */
    private function published()
    {
        return Article::query()->where('status', ArticleWorkflow::PUBLISHED);
    }

    /** ⭐ `Schema.org Article` لتظهر نتيجةً غنيّة في محرّكات البحث (21.2-ب) */
    private function schema(Article $article, string $url): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->meta_title ?: $article->title,
            'description' => $article->meta_description ?: $article->excerpt,
            'mainEntityOfPage' => $url,
            'url' => $url,
            'image' => route('growth.og.article', $article->slug),
            'datePublished' => $article->published_at?->toAtomString(),
            'dateModified' => $article->updated_at?->toAtomString(),
            'inLanguage' => 'ar',
            'author' => (bool) setting('growth.articles.show_author', true) && $article->author ? [
                '@type' => 'Person',
                'name' => $article->author->name,
                'url' => $article->author->profileUrl(),
            ] : null,
            'publisher' => [
                '@type' => 'Organization',
                'name' => (string) config('app.name'),
            ],
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** التدريب أو المسار المرتبط — يظهر في نهاية المقال (21.2-أ) */
    private function relatedUrl(Article $article): ?array
    {
        $related = $article->related;

        if (! $related) {
            return null;
        }

        $type = match ($related::class) {
            Course::class => 'course',
            LearningPath::class => 'path',
            default => null,
        };

        if (! $type || ! $related->slug) {
            return null;
        }

        return [
            'title' => (string) ($related->name_ar ?? $related->title_ar ?? ''),
            'url' => $this->utm->tag(
                route('store.product', ['type' => $type, 'slug' => $related->slug]),
                'article',
                'article_cta',
                $article->slug,
            ),
        ];
    }
}
