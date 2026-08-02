<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Services\Admin\System\ArticleWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * مركز المقالات (21.2-أ) — نظام تحريريّ كامل بدورة نشر إلزاميّة.
 *
 * ⭐ **الكاتب لا ينشر مقاله بنفسه**: `articles.create` مفصولة عن `articles.publish`،
 *    والتحقّق في الخادم لا في الواجهة — فإخفاء الزرّ وحده ليس أمانًا.
 * ⭐ ويكتبها **الإدارة والمتطوّعون بصلاحيّة** لا الجميع.
 */
class ArticleAdminController extends Controller
{
    public function __construct(private readonly ArticleWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $articles = Article::query()
            ->with(['author', 'article_category'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->when($status && array_key_exists($status, ArticleWorkflow::statuses()), fn ($q) => $q->where('status', $status))
            ->when($request->integer('category'), fn ($q, $id) => $q->where('article_category_id', $id))
            ->latest('id')
            ->paginate((int) setting('articles.admin.per_page', 20))
            ->withQueryString();

        return view('admin.articles.index', [
            'articles' => $articles,
            'statuses' => ArticleWorkflow::statuses(),
            'status' => $status,
            'categories' => ArticleCategory::query()->orderBy('sort_order')->get(),
            'workflow' => $this->workflow,
            'counts' => Article::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('admin.articles.edit', [
            'article' => new Article(['status' => ArticleWorkflow::DRAFT]),
            'categories' => ArticleCategory::query()->orderBy('sort_order')->get(),
            'workflow' => $this->workflow,
        ]);
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.edit', [
            'article' => $article,
            'categories' => ArticleCategory::query()->orderBy('sort_order')->get(),
            'workflow' => $this->workflow,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $article = Article::create($data + [
            'author_id' => $request->user()->id,
            'status' => ArticleWorkflow::DRAFT,
        ]);

        return redirect()->route('admin.articles.edit', $article)->with('status', 'المسودّة اتحفظت ✓');
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        // المقال المنشور لا يُحرَّر مباشرةً — يرجع للمراجعة أوّلًا فلا يتغيّر المنشور بلا أثر
        abort_if($article->status === ArticleWorkflow::PUBLISHED && ! $request->user()->allows('articles.publish'), 403);

        $article->update($this->validated($request));

        return back()->with('status', 'التعديل اتحفظ ✓');
    }

    public function submit(Request $request, Article $article): RedirectResponse
    {
        return $this->run(fn () => $this->workflow->submitForReview($article, $request->user()), 'المقال راح للمراجعة ✓');
    }

    /** ملاحظات مراجعة مكتوبة — والمقال يرجع مسودّة فيعدّل الكاتب ويعيد الإرسال */
    public function review(Request $request, Article $article): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        return $this->run(
            fn () => $this->workflow->requestChanges($article, $request->user(), $data['notes']),
            'الملاحظات اتبعتت للكاتب ✓',
        );
    }

    public function publish(Request $request, Article $article): RedirectResponse
    {
        return $this->run(fn () => $this->workflow->publish($article, $request->user()), 'المقال اتنشر ✓');
    }

    /** ⭐ أرشفة لا حذف — والرابط المنشور لا يتحوّل إلى 404 فجأةً */
    public function archive(Request $request, Article $article): RedirectResponse
    {
        return $this->run(fn () => $this->workflow->archive($article, $request->user()), 'المقال اتأرشف ✓');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
        ]);

        ArticleCategory::create([
            'name_ar' => $data['name_ar'],
            'slug' => Str::slug($data['name_ar']).'-'.Str::lower(Str::random(4)),
        ]);

        return back()->with('status', 'التصنيف اتضاف ✓');
    }

    private function run(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'article_category_id' => ['nullable', 'integer', 'exists:article_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'cover_path' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            // بيانات SEO — عنوان ووصف الميتا (21.2-أ)
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            // ربط بتدريب أو مسار يظهر في نهاية المقال
            'related_type' => ['nullable', 'string', 'max:190'],
            'related_id' => ['nullable', 'integer'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['title']) ?: Str::lower(Str::random(8));

        if (Article::query()->where('slug', $data['slug'])->where('id', '!=', $request->route('article')?->id)->exists()) {
            $data['slug'] .= '-'.Str::lower(Str::random(4));
        }

        return $data;
    }
}
