<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * دليل المستخدم (الدستور 24.5): إجابة سريعة **بلا تذكرة**.
 * ولذلك البحث بارز في الهيدر، و«لم أجد إجابتي» يفتح تذكرة بعنوان مملوء مسبقًا.
 */
class HelpController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim($request->string('q')->toString());
        $category = $request->string('category')->toString();

        $published = HelpArticle::query()->where('status', 'published');

        $categories = (clone $published)
            ->whereNotNull('category')
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $articles = (clone $published)
            ->when($category !== '', fn ($b) => $b->where('category', $category))
            ->when($q !== '', fn ($b) => $b->where(function ($sub) use ($q) {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('body', 'like', '%'.$q.'%');
            }))
            ->orderByDesc('helpful_yes')
            ->take(max(1, (int) setting('account.help.page_size', 20)))
            ->get();

        return view('support.help.index', [
            'articles' => $articles,
            'categories' => $categories,
            'q' => $q,
            'category' => $category,
        ]);
    }

    public function show(HelpArticle $article): View
    {
        abort_unless($article->status === 'published', 404);

        return view('support.help.show', [
            'article' => $article,
            'related' => HelpArticle::where('status', 'published')
                ->where('category', $article->category)
                ->whereKeyNot($article->id)
                ->take(max(1, (int) setting('account.help.related_count', 3)))
                ->get(),
        ]);
    }

    /**
     * «هل كان مفيدًا؟» — ردّ فوريّ بلا صفحة جديدة (2.17-ب)،
     * ويزيد `helpful_yes` أو `helpful_no` مرّةً واحدة لكلّ جلسة على المقال.
     */
    public function feedback(Request $request, HelpArticle $article): RedirectResponse
    {
        $data = $request->validate([
            'helpful' => ['required', 'in:yes,no'],
        ], [
            'helpful.required' => (string) setting('help.screen.feedback_msg', 'اختار «أيوه» أو «لأ».'),
            'helpful.in' => (string) setting('help.screen.feedback_denied', 'الاختيار ده مش متاح.'),
        ]);

        $voted = (array) $request->session()->get('help.voted', []);

        if (! in_array($article->id, $voted, true)) {
            $article->increment($data['helpful'] === 'yes' ? 'helpful_yes' : 'helpful_no');
            $voted[] = $article->id;
            $request->session()->put('help.voted', $voted);
        }

        return back()->with('status', $data['helpful'] === 'yes'
            ? (string) setting('help.screen.feedback_msg_2', 'شكرًا لك 💛 — رأيك بيساعدنا نحسّن الدليل.')
            : (string) setting('help.screen.feedback_msg_3', 'تمام، هنشتغل على تحسين الشرح. ولو محتاج مساعدة دلوقتي افتح تذكرة.'));
    }
}
