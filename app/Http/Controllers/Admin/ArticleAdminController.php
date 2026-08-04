<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Services\Admin\Content\MediaLibrary;
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
    public function __construct(
        private readonly ArticleWorkflow $workflow,
        private readonly MediaLibrary $media,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $articles = Article::query()
            ->with(['author', 'article_category'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->when($status && array_key_exists($status, ArticleWorkflow::statuses()), fn ($q) => $q->where('status', $status))
            ->when($request->integer('category'), fn ($q, $id) => $q->where('article_category_id', $id))
            // الوسوم صارت حقلًا فصار لها فلتر — وإلّا فهي بيانات لا يصل إليها أحد
            ->when($request->string('tag')->toString(), fn ($q, $tag) => $q->whereJsonContains('tags', $tag))
            ->latest('id')
            ->paginate((int) setting('articles.admin.per_page', 20))
            ->withQueryString();

        return view('admin.articles.index', [
            'articles' => $articles,
            'statuses' => ArticleWorkflow::statuses(),
            'status' => $status,
            'tagList' => $this->tagList(),
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
            'tagList' => $this->tagList(),
            'workflow' => $this->workflow,
        ]);
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.edit', [
            'article' => $article,
            'categories' => ArticleCategory::query()->orderBy('sort_order')->get(),
            'tagList' => $this->tagList(),
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

        return redirect()->route('admin.articles.edit', $article)->with('status', (string) setting('articles.admin.store_ok', 'المسودّة اتحفظت ✓'));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        // المقال المنشور لا يُحرَّر مباشرةً — يرجع للمراجعة أوّلًا فلا يتغيّر المنشور بلا أثر
        abort_if($article->status === ArticleWorkflow::PUBLISHED && ! $request->user()->allows('articles.publish'), 403);

        $article->update($this->validated($request));

        return back()->with('status', (string) setting('articles.admin.update_ok', 'التعديل اتحفظ ✓'));
    }

    public function submit(Request $request, Article $article): RedirectResponse
    {
        return $this->run(fn () => $this->workflow->submitForReview($article, $request->user()), (string) setting('articles.admin.submit_ok', 'المقال راح للمراجعة ✓'));
    }

    /** ملاحظات مراجعة مكتوبة — والمقال يرجع مسودّة فيعدّل الكاتب ويعيد الإرسال */
    public function review(Request $request, Article $article): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        return $this->run(
            fn () => $this->workflow->requestChanges($article, $request->user(), $data['notes']),
            (string) setting('articles.admin.review_ok', 'الملاحظات اتبعتت للكاتب ✓'),
        );
    }

    public function publish(Request $request, Article $article): RedirectResponse
    {
        return $this->run(fn () => $this->workflow->publish($article, $request->user()), (string) setting('articles.admin.publish_ok', 'المقال اتنشر ✓'));
    }

    /** ⭐ أرشفة لا حذف — والرابط المنشور لا يتحوّل إلى 404 فجأةً */
    public function archive(Request $request, Article $article): RedirectResponse
    {
        return $this->run(fn () => $this->workflow->archive($article, $request->user()), (string) setting('articles.admin.archive_ok', 'المقال اتأرشف ✓'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            // `sort_order` يُقرَأ في أربعة مواضع (`orderBy`) ولم يكن يُكتَب أبدًا،
            // فكان الترتيب صفرًا للجميع — عمودٌ حيٌّ في القراءة ميّتٌ في الكتابة.
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        ArticleCategory::create([
            'name_ar' => $data['name_ar'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'slug' => Str::slug($data['name_ar']).'-'.Str::lower(Str::random(4)),
        ]);

        return back()->with('status', (string) setting('articles.admin.store_category_ok', 'التصنيف اتضاف ✓'));
    }

    /**
     * الوسوم المستعمَلة فعلًا — اقتراحٌ يمنع تشتّت التسمية بلا أن يمنع وسمًا جديدًا.
     *
     * @return array<int,string>
     */
    private function tagList(): array
    {
        return Article::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn ($tags) => is_array($tags) ? $tags : (json_decode((string) $tags, true) ?: []))
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
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
            // «التصنيف **والوسوم**» (21.2-أ) — و`articles.create` نصُّها في 12.2.2:
            // «إنشاء مقال جديد وتصنيفه **وربطه بوسومه**». كانت مُصادَقًا عليها بلا حقل.
            'tags' => ['nullable'],
            // بيانات SEO — عنوان ووصف الميتا (21.2-أ)
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            // ربط بتدريب أو مسار يظهر في نهاية المقال
            'related_type' => ['nullable', 'string', 'max:190'],
            'related_id' => ['nullable', 'integer'],
        ]);

        // ⭐ نفس منظّف الوسوم المستعمَل في مكتبة الوسائط والاستوديو — مصدرٌ واحد (2.14-ب)
        $data['tags'] = $this->media->cleanTags($data['tags'] ?? []);

        // `nullable` لا يضع المفتاح في النتيجة إن غاب عن الطلب — فالقراءة المباشرة
        // كانت تنفجر بـ500 على أيّ طلبٍ بلا حقل Slug (والفورم يرسله دائمًا فاختبأت)
        $data['slug'] = Str::slug(($data['slug'] ?? '') ?: $data['title']) ?: Str::lower(Str::random(8));

        if (Article::query()->where('slug', $data['slug'])->where('id', '!=', $request->route('article')?->id)->exists()) {
            $data['slug'] .= '-'.Str::lower(Str::random(4));
        }

        return $data;
    }
}
