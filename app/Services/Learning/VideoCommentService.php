<?php

namespace App\Services\Learning;

use App\Models\Lesson;
use App\Models\User;
use App\Models\VideoComment;
use App\Models\VideoCommentLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * تعليقات الفيديو (الدستور 3.1): قسم تعليقات تحت كلّ فيديو · لايك وردّ لكلّ تعليق ·
 * **تحميل تدريجيّ 6 تعليقات كلّ مرّة** حفاظًا على الأداء · وإشرافٌ يخفي أو يحذف بصلاحيّة.
 */
class VideoCommentService
{
    /** عدد التعليقات في الدفعة الواحدة — إعداد لا رقم محروق (2.13) */
    public function perPage(): int
    {
        return max(1, (int) setting('learning.comments.per_page', 6));
    }

    /**
     * تعليقات جذريّة مرقَّمة، أحدث أوّلًا، ومعها ردودها ولايكات القارئ.
     * والمخفيّ لا يظهر إلّا لمن يملك صلاحيّة الإشراف — **يُخفى ولا يُعطَّل** (2.15-أ-7).
     */
    public function paginate(Lesson $lesson, User $viewer, int $page = 1): LengthAwarePaginator
    {
        $moderator = $viewer->can('video_comments.archive');

        $roots = VideoComment::query()
            ->where('lesson_id', $lesson->id)
            ->whereNull('parent_id')
            ->when(! $moderator, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('is_hidden', false)
                ->orWhere('user_id', $viewer->id)))
            ->with(['user'])
            ->latest('id')
            ->paginate($this->perPage(), ['*'], 'page', max(1, $page));

        $rootIds = collect($roots->items())->pluck('id')->all();

        $replies = VideoComment::query()
            ->whereIn('parent_id', $rootIds)
            ->when(! $moderator, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('is_hidden', false)
                ->orWhere('user_id', $viewer->id)))
            ->with(['user'])
            ->oldest('id')
            ->get()
            ->groupBy('parent_id');

        $liked = $this->likedIds($viewer, array_merge($rootIds, $replies->flatten()->pluck('id')->all()));

        foreach ($roots->items() as $comment) {
            $comment->setRelation('replies', $replies->get($comment->id, collect()));
            $comment->viewer_liked = in_array($comment->id, $liked, true);

            foreach ($comment->replies as $reply) {
                $reply->viewer_liked = in_array($reply->id, $liked, true);
            }
        }

        return $roots;
    }

    /** إجماليّ التعليقات الظاهرة تحت الدرس — يُعرض جنب العنوان (3.4-36) */
    public function countFor(Lesson $lesson, User $viewer): int
    {
        return VideoComment::query()
            ->where('lesson_id', $lesson->id)
            ->when(! $viewer->can('video_comments.archive'), fn ($q) => $q->where(fn ($inner) => $inner
                ->where('is_hidden', false)
                ->orWhere('user_id', $viewer->id)))
            ->count();
    }

    /** كتابة تعليق أو ردّ — والردّ بمستوًى واحد: ردٌّ على ردٍّ يُعلَّق على أصله */
    public function create(Lesson $lesson, User $user, string $body, ?VideoComment $parent = null): VideoComment
    {
        $root = $parent?->parent_id ? $parent->parent : $parent;

        return VideoComment::create([
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'parent_id' => $root?->id,
            'body' => trim($body),
        ]);
    }

    /**
     * تبديل اللايك — والقيد الفريد في القاعدة هو الضامن، فلو ضغط مرّتين معًا
     * لا يُحسَب لايكان.
     *
     * @return array{liked:bool,count:int}
     */
    public function toggleLike(VideoComment $comment, User $user): array
    {
        return DB::transaction(function () use ($comment, $user) {
            $existing = VideoCommentLike::query()
                ->where('video_comment_id', $comment->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $liked = false;
            } else {
                VideoCommentLike::query()->firstOrCreate([
                    'video_comment_id' => $comment->id,
                    'user_id' => $user->id,
                ]);
                $liked = true;
            }

            $count = VideoCommentLike::query()->where('video_comment_id', $comment->id)->count();
            $comment->update(['likes_count' => $count]);

            return ['liked' => $liked, 'count' => $count];
        });
    }

    /** إخفاء إشرافيّ: يختفي عن الناس ويبقى للمراجعة — لا مسح صامت (3.1) */
    public function hide(VideoComment $comment, User $moderator): void
    {
        $comment->update([
            'is_hidden' => true,
            'hidden_by' => $moderator->id,
            'hidden_at' => now(),
        ]);
    }

    public function unhide(VideoComment $comment): void
    {
        $comment->update(['is_hidden' => false, 'hidden_by' => null, 'hidden_at' => null]);
    }

    /** الحذف يسحب الردود معه حتى لا يبقى ردٌّ بلا سياق */
    public function delete(VideoComment $comment): void
    {
        DB::transaction(function () use ($comment) {
            VideoComment::query()->where('parent_id', $comment->id)->get()->each->delete();
            $comment->delete();
        });
    }

    // ------------------------------------------------------------ داخليّ

    /** @return array<int, int> */
    private function likedIds(User $viewer, array $commentIds): array
    {
        if ($commentIds === []) {
            return [];
        }

        return VideoCommentLike::query()
            ->where('user_id', $viewer->id)
            ->whereIn('video_comment_id', $commentIds)
            ->pluck('video_comment_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
