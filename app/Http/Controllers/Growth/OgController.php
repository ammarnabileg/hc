<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\User;
use App\Services\Admin\System\ArticleWorkflow;
use App\Services\Growth\InviteLeaderboard;
use App\Services\Growth\OgCardRenderer;
use Illuminate\Http\Response;

/**
 * ⭐ صورة OG **لكلّ نوع رابط** (21.1-أ · 12.14): تدريب · مسار · بروفايل · ترتيب ·
 *    مقال · شهادة — لا للفعاليّات وحدها كما كانت.
 *
 * والمسارات **عامّة بلا تسجيل** عمدًا: مَن تُشارَك معه الرابط لا حساب له،
 * ولذلك لا تعرض البطاقة إلّا ما هو **منشورٌ عامّ** أصلًا — والمسودّة 404.
 */
class OgController extends Controller
{
    public function __construct(
        private readonly OgCardRenderer $renderer,
        private readonly InviteLeaderboard $board,
    ) {}

    public function course(string $slug): Response
    {
        $course = Course::query()
            ->where('slug', $slug)
            ->where('status', (string) setting('learning.course.published_status', 'published'))
            ->firstOrFail();

        return $this->svg($this->renderer->card('course', (string) $course->name_ar, array_filter([
            $course->is_free ? (string) setting('growth.og.free_label', 'مجّانيّ') : null,
            (string) setting('growth.og.course_meta', 'صفحة التدريب · معاينة أوّل درس'),
        ])));
    }

    public function path(string $slug): Response
    {
        $path = LearningPath::query()
            ->where('slug', $slug)
            ->where('status', (string) setting('learning.course.published_status', 'published'))
            ->firstOrFail();

        return $this->svg($this->renderer->card('path', (string) $path->name_ar, [
            (string) setting('growth.og.path_meta', 'مسار تعلّم متكامل'),
        ]));
    }

    /** بطاقة البروفايل العامّ — بالاسم المختصر والكود، بلا أيّ حقلٍ خصوصيّته مقيّدة */
    public function profile(string $code): Response
    {
        $user = User::query()->where('code', $code)->firstOrFail();

        return $this->svg($this->renderer->card('profile', $user->shortName(), array_filter([
            '#'.$user->code,
            (string) setting('growth.og.profile_meta', 'بروفايل على المنصّة'),
        ])));
    }

    /** بطاقة لوحة متصدّري الدعوات — «كلّ اللوحات قابلة للاستخراج كصورة» (21.1-ج) */
    public function leaderboard(?string $month = null): Response
    {
        $period = $this->board->period($month);
        $rows = $this->board->rows($month);
        $top = $rows->first();

        return $this->svg($this->renderer->card(
            'leaderboard',
            (string) setting('growth.invite_board.title', 'متصدّرو الدعوات'),
            array_filter([
                $period['from']->translatedFormat('F Y'),
                $top && $top['user'] ? '١ · '.$top['user']->shortName().' — '.$top['completed'] : null,
            ]),
        ));
    }

    public function article(string $slug): Response
    {
        $article = Article::query()
            ->where('slug', $slug)
            ->where('status', ArticleWorkflow::PUBLISHED)
            ->firstOrFail();

        return $this->svg($this->renderer->card('article', (string) $article->title, array_filter([
            $article->published_at?->translatedFormat('j F Y'),
            (bool) setting('growth.articles.show_author', true) ? $article->author?->shortName() : null,
        ])));
    }

    /** بطاقة فهرس المقالات — تُستعمَل على صفحة القائمة نفسها */
    public function articles(): Response
    {
        return $this->svg($this->renderer->card(
            'article',
            (string) setting('growth.articles.index_title', 'مقالات المنصّة'),
            [(string) setting('growth.articles.index_subtitle', 'محتوًى عربيّ مكتوب بأيدينا')],
        ));
    }

    public function certificate(string $code): Response
    {
        $certificate = Certificate::query()->with('user')->where('code', $code)->firstOrFail();
        $snapshot = (array) ($certificate->data_snapshot ?? []);

        return $this->svg($this->renderer->card('certificate', (string) ($snapshot['certificate_name'] ?? 'شهادة معتمدة'), array_filter([
            (string) ($snapshot['holder_name'] ?? $certificate->user?->name ?? ''),
            '#'.$certificate->code,
        ])));
    }

    private function svg(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age='.(int) setting('growth.og.cache_seconds', 3600),
        ]);
    }
}
