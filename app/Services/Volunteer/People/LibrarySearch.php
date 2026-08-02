<?php

namespace App\Services\Volunteer\People;

use App\Models\InternalLibraryItem;
use App\Models\LibraryAccessRequest;
use App\Models\Membership;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * المكتبة الداخليّة (23-3.3 · 24.4-10).
 *
 * ثلاث قواعد حاسمة هنا:
 *  1) **البحث داخل محتوى المُدخَل نفسه** (`content_text`) لا في العنوان فقط،
 *     والنتيجة تعرض **مقتطفًا من موضع المطابقة مع تظليل الكلمة**.
 *  2) **ما هو خارج نطاقي لا يُخفى** — يظهر بعنوانه وقفلٍ وزرّ [اطلب وصولًا].
 *  3) **بلا رفع يدويّ** — الفهرسة آليّة لحظة اعتماد المهمّة.
 */
class LibrarySearch
{
    /** الأنواع الستّة المعتمَدة — تسمياتها من الإعدادات */
    public function types(): array
    {
        return [
            'design' => (string) setting('internal_library.type.design', 'تصميم'),
            'content' => (string) setting('internal_library.type.content', 'محتوى'),
            'plan' => (string) setting('internal_library.type.plan', 'خطّة'),
            'form' => (string) setting('internal_library.type.form', 'نموذج'),
            'document' => (string) setting('internal_library.type.document', 'مستند'),
            'research' => (string) setting('internal_library.type.research', 'بحث'),
        ];
    }

    public function accessLevels(): array
    {
        return [
            'entity' => (string) setting('internal_library.access.entity', 'كياني فقط'),
            'all_volunteers' => (string) setting('internal_library.access.all', 'كلّ المتطوّعين'),
            'restricted' => (string) setting('internal_library.access.restricted', 'مقيَّد ببوزشن فأعلى'),
        ];
    }

    /**
     * نتائج البحث — **كلّها**، ولكلّ نتيجة علم `locked` يقرّر شكل الكارت لا وجوده.
     *
     * @param  array{q?:string,type?:string,entity?:int,track?:int,from?:string,to?:string,access?:string,owner?:string,tag?:string}  $filters
     * @return Collection<int, InternalLibraryItem>
     */
    public function search(User $user, array $filters = []): Collection
    {
        $q = trim((string) ($filters['q'] ?? ''));

        $items = InternalLibraryItem::query()
            ->with(['entity', 'owner', 'task'])
            ->when($q !== '', function ($b) use ($q) {
                // ⭐ البحث داخل المحتوى نفسه لا في العنوان فقط (23-3.3)
                $b->where(fn ($w) => $w->where('content_text', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%"));
            })
            ->when(! empty($filters['type']), fn ($b) => $b->where('type', $filters['type']))
            ->when(! empty($filters['entity']), fn ($b) => $b->where('entity_id', (int) $filters['entity']))
            ->when(! empty($filters['track']), fn ($b) => $b->whereIn('entity_id',
                \App\Models\Entity::query()->where('track_id', (int) $filters['track'])->select('id')))
            ->when(! empty($filters['access']), fn ($b) => $b->where('access_level', $filters['access']))
            ->when(! empty($filters['owner']), fn ($b) => $b->where('owner_id', (int) $filters['owner']))
            ->when(! empty($filters['from']), fn ($b) => $b->where('approved_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($b) => $b->where('approved_at', '<=', $filters['to']))
            ->when(! empty($filters['tag']), fn ($b) => $b->where('tags', 'like', '%"'.$filters['tag'].'"%'))
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->get();

        $scope = $this->scopeOf($user);

        return $items->each(function (InternalLibraryItem $item) use ($user, $scope, $q) {
            $item->setAttribute('locked', ! $this->canOpen($user, $item, $scope));
            $item->setAttribute('snippet', $q !== '' ? $this->snippet($item, $q) : null);
        });
    }

    /**
     * مقتطف من **موضع المطابقة** داخل المحتوى مع تظليل الكلمة.
     * يرجع HTML آمنًا: كلّ ما حوله مهروب، و`<mark>` وحده هو الوسم المسموح.
     */
    public function snippet(InternalLibraryItem $item, string $needle): ?string
    {
        $haystack = (string) $item->content_text;

        if ($haystack === '' || $needle === '') {
            return null;
        }

        $pos = mb_stripos($haystack, $needle);

        if ($pos === false) {
            return null;
        }

        $pad = max(20, (int) setting('internal_library.snippet_chars', 80));
        $start = max(0, $pos - $pad);
        $length = mb_strlen($needle) + ($pad * 2);
        $chunk = mb_substr($haystack, $start, $length);

        $escaped = e($chunk);
        $highlighted = preg_replace(
            '/'.preg_quote(e($needle), '/').'/iu',
            '<mark style="background: color-mix(in srgb, var(--color-brand-500) 35%, transparent); color: inherit">$0</mark>',
            $escaped,
        );

        return ($start > 0 ? '…' : '').$highlighted.(mb_strlen($haystack) > $start + $length ? '…' : '');
    }

    /**
     * نطاق المستخدم: كياناته · مساره · أعلى رتبة بوزشن يملكها.
     *
     * @return array{entities:array<int,int>,tracks:array<int,int>,rank:int,all:bool}
     */
    public function scopeOf(User $user): array
    {
        $memberships = Membership::query()
            ->with(['entity', 'position'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        return [
            'entities' => $memberships->pluck('entity_id')->map(fn ($i) => (int) $i)->all(),
            'tracks' => $memberships->pluck('entity.track_id')->filter()->map(fn ($i) => (int) $i)->unique()->values()->all(),
            'rank' => (int) ($memberships->pluck('position.rank')->filter()->max() ?? 0),
            // مشرف عام التطوّع والأدمن يريان الكلّ (23-3.3)
            'all' => $user->allows('internal_library.view') && $user->widestScope('internal_library.view') === 'ALL',
        ];
    }

    /** هل يفتح هذا المُدخَل فعلًا؟ (وما دونه يظهر بقفل لا يختفي) */
    public function canOpen(User $user, InternalLibraryItem $item, ?array $scope = null): bool
    {
        $scope ??= $this->scopeOf($user);

        if ($scope['all']) {
            return true;
        }

        // مشرف المسار يرى مسارَه كاملًا
        if ($item->entity && in_array((int) $item->entity->track_id, $scope['tracks'], true)
            && $user->widestScope('internal_library.view') === 'TRACK') {
            return true;
        }

        return match ((string) $item->access_level) {
            'all_volunteers' => true,
            'restricted' => $scope['rank'] >= $this->minRank($item),
            default => in_array((int) $item->entity_id, $scope['entities'], true),
        };
    }

    private function minRank(InternalLibraryItem $item): int
    {
        if (! $item->min_position_id) {
            return PHP_INT_MAX;
        }

        return (int) (Position::query()->whereKey($item->min_position_id)->value('rank') ?? PHP_INT_MAX);
    }

    /** طلب وصول بضغطة — **يقرّه دايركتور الكيان** (23-3.3) */
    public function requestAccess(InternalLibraryItem $item, User $user, ?string $reason): LibraryAccessRequest
    {
        return LibraryAccessRequest::firstOrCreate(
            ['internal_library_item_id' => $item->id, 'user_id' => $user->id, 'status' => 'pending'],
            ['reason' => $reason],
        );
    }

    /** هل عليه طلب معلَّق؟ — فلا يُكرّر الضغط بلا فائدة */
    public function pendingRequestIds(User $user, Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        return LibraryAccessRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereIn('internal_library_item_id', $items->pluck('id'))
            ->pluck('internal_library_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** كلّ الوسوم الموجودة — تُضاف لحظة الاعتماد وتقبل التعدّد */
    public function allTags(): array
    {
        return InternalLibraryItem::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn ($tags) => is_array($tags) ? $tags : [])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
