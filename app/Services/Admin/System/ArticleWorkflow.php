<?php

namespace App\Services\Admin\System;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\User;
use RuntimeException;

/**
 * دورة نشر المقالات الإلزاميّة (21.2-أ): مسودّة ⟵ قيد المراجعة ⟵ منشورة.
 *
 * ⭐ **والكاتب لا ينشر مقاله بنفسه** — وهذا شرط صحّة الدورة لا تفصيلة واجهة:
 *    الصلاحيّة `articles.create` مفصولة عن `articles.publish`، **والتحقّق في الخادم**
 *    فلا يكفي إخفاء الزرّ. والأرشفة بديل الحذف دائمًا.
 */
class ArticleWorkflow
{
    public const DRAFT = 'draft';

    public const IN_REVIEW = 'in_review';

    public const PUBLISHED = 'published';

    public const ARCHIVED = 'archived';

    /** @return array<string,string> */
    public static function statuses(): array
    {
        return [
            self::DRAFT => setting('articles.article_workflow.statuses_1', 'مسودّة'),
            self::IN_REVIEW => setting('articles.article_workflow.statuses_2', 'قيد المراجعة'),
            self::PUBLISHED => setting('articles.article_workflow.statuses_3', 'منشورة'),
            self::ARCHIVED => setting('articles.article_workflow.statuses_4', 'مؤرشفة'),
        ];
    }

    /** إرسال المسودّة للمراجعة — الكاتب أو مَن يملك التعديل */
    public function submitForReview(Article $article, User $actor): Article
    {
        if (! in_array($article->status, [self::DRAFT], true)) {
            throw new RuntimeException(setting('articles.article_workflow.submit_for_review_1', 'المقال مش مسودّة — مافيش حاجة تتبعت للمراجعة.'));
        }

        $old = $article->status;
        $article->update(['status' => self::IN_REVIEW, 'review_notes' => null]);
        $this->audit($article, 'articles.submit', $old, self::IN_REVIEW, $actor);

        return $article->refresh();
    }

    /** ردّ المراجع بملاحظات مكتوبة ⟵ يرجع مسودّة فيعدّل الكاتب ويعيد الإرسال */
    public function requestChanges(Article $article, User $actor, string $notes): Article
    {
        $notes = trim($notes);

        if ($notes === '') {
            throw new RuntimeException(setting('articles.article_workflow.request_changes_1', 'اكتب ملاحظات المراجعة — الكاتب لازم يعرف يعدّل إيه.'));
        }

        $old = $article->status;
        $article->update([
            'status' => self::DRAFT,
            'review_notes' => $notes,
            'reviewer_id' => $actor->id,
        ]);
        $this->audit($article, 'articles.review', $old, self::DRAFT, $actor, $notes);

        return $article->refresh();
    }

    /**
     * النشر — ويُرفَض في الخادم في حالتين:
     *  (أ) بلا صلاحيّة `articles.publish`.
     *  (ب) **إن كان المنفِّذ هو كاتب المقال** — ولو ملك الصلاحيّة.
     */
    public function publish(Article $article, User $actor): Article
    {
        if (! $actor->allows('articles.publish')) {
            throw new RuntimeException(setting('articles.article_workflow.publish_1', 'النشر لمن يملك صلاحيّة النشر وحده.'));
        }

        if ($this->isAuthor($article, $actor)) {
            throw new RuntimeException(setting('articles.article_workflow.publish_2', 'الكاتب لا ينشر مقاله بنفسه — لازم شخص تاني يراجع وينشر.'));
        }

        if ($article->status !== self::IN_REVIEW) {
            throw new RuntimeException(setting('articles.article_workflow.publish_3', 'لا يُنشَر إلّا مقالٌ «قيد المراجعة».'));
        }

        $old = $article->status;
        $article->update([
            'status' => self::PUBLISHED,
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
        $this->audit($article, 'articles.publish', $old, self::PUBLISHED, $actor);

        return $article->refresh();
    }

    /** ⭐ أرشفة لا حذف — المنشور يبقى أثره وروابطه قائمة */
    public function archive(Article $article, User $actor): Article
    {
        $old = $article->status;
        $article->update(['status' => self::ARCHIVED]);
        $this->audit($article, 'articles.archive', $old, self::ARCHIVED, $actor);

        return $article->refresh();
    }

    public function isAuthor(Article $article, User $actor): bool
    {
        return (int) $article->author_id === (int) $actor->id;
    }

    /** هل يظهر زرّ النشر أصلًا؟ (العنصر الذي لا يملكه المستخدم يُخفى — 2.15-أ-7) */
    public function mayPublish(Article $article, User $actor): bool
    {
        return $article->status === self::IN_REVIEW
            && ! $this->isAuthor($article, $actor)
            && $actor->allows('articles.publish');
    }

    private function audit(Article $article, string $action, ?string $old, string $new, User $actor, ?string $note = null): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $article->getMorphClass(),
            'auditable_id' => $article->getKey(),
            'old_values' => ['status' => $old],
            'new_values' => array_filter(['status' => $new, 'notes' => $note], fn ($v) => $v !== null),
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
