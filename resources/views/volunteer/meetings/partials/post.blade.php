@php
    /** بوست النقاش بكومنتاته وتصويته — والمثبَّت أعلى القائمة (13.4-ح) */
    $myVote = (int) ($myVotes[$post->id]->value ?? 0);
    $depth = $depth ?? 0;
@endphp

<article class="card p-3 @if ($depth > 0) ms-4 @endif"
         @if ($post->is_pinned) style="border-color: var(--color-brand-600)" @endif>
    <div class="flex items-start gap-3">
        <div class="flex flex-col items-center gap-1">
            <form method="post" action="{{ route('volunteer.meetings.posts.vote', $post) }}">
                @csrf
                <input type="hidden" name="value" value="1">
                <button type="submit" aria-label="{{ setting('volunteer.meetings_post.aria', 'تصويت لأعلى') }}" class="rounded-lg p-1"
                        style="color: {{ $myVote === 1 ? 'var(--color-state-ok)' : 'var(--text-muted)' }}">
                    @include('volunteer.meetings.partials.icon', ['name' => 'up', 'size' => 18])
                </button>
            </form>
            <span class="text-xs font-bold">{{ (int) $post->votes }}</span>
            <form method="post" action="{{ route('volunteer.meetings.posts.vote', $post) }}">
                @csrf
                <input type="hidden" name="value" value="-1">
                <button type="submit" aria-label="{{ setting('volunteer.meetings_post.aria_2', 'تصويت لأسفل') }}" class="rounded-lg p-1"
                        style="color: {{ $myVote === -1 ? 'var(--color-state-danger)' : 'var(--text-muted)' }}">
                    @include('volunteer.meetings.partials.icon', ['name' => 'down', 'size' => 18])
                </button>
            </form>
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 text-xs" style="color: var(--text-muted)">
                <x-avatar :user="$post->user" size="7" />
                <span>{{ $post->user?->name }}</span>
                <span title="{{ $post->created_at?->format('Y-m-d H:i') }}">{{ $post->created_at?->diffForHumans() }}</span>
                @if ($post->is_pinned)
                    <x-state-badge state="honor" :label="setting('volunteer.meetings_post.label', 'مثبَّت')" />
                @endif
            </div>

            <p class="mt-2 text-sm whitespace-pre-line">{{ $post->body }}</p>

            @if ($post->attachment_path)
                <a href="{{ \Illuminate\Support\Facades\Storage::url($post->attachment_path) }}" target="_blank" rel="noopener"
                   class="mt-2 inline-flex items-center gap-1 text-xs" style="color: var(--color-brand-400)">
                    @include('volunteer.meetings.partials.icon', ['name' => 'attachment']) {{ setting('volunteer.common.attachment', 'مرفق') }}
                </a>
            @endif

            <div class="mt-2 flex items-center gap-2 text-xs">
                <details>
                    <summary class="cursor-pointer" style="color: var(--text-muted)">{{ setting('volunteer.meetings_post.summary', 'ردّ') }}</summary>
                    <form method="post" action="{{ route('volunteer.meetings.posts', $meeting) }}" class="mt-2 flex gap-2">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $post->id }}">
                        <input type="text" name="body" required maxlength="4000" placeholder="{{ setting('volunteer.meetings_post.placeholder', 'اكتب ردّك…') }}"
                               class="flex-1 rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <button type="submit" class="btn rounded-xl px-3 py-2 text-xs font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.meetings_post.action', 'إرسال') }}</button>
                    </form>
                </details>

                {{-- التثبيت لصاحب الاجتماع أو أيّ أبلاين — ومخفيّ لغيرهم (2.15-أ-7) --}}
                @if ($canManage && $depth === 0)
                    <form method="post" action="{{ route('volunteer.meetings.posts.pin', $post) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1" style="color: var(--text-muted)">
                            @include('volunteer.meetings.partials.icon', ['name' => 'pin'])
                            {{ $post->is_pinned ? setting('volunteer.meetings_post.text', 'فكّ التثبيت') : setting('volunteer.meetings_post.text_2', 'تثبيت') }}
                        </button>
                    </form>
                @endif
            </div>

            @foreach ($post->replies as $reply)
                <div class="mt-3">
                    @include('volunteer.meetings.partials.post', [
                        'post' => $reply, 'meeting' => $meeting,
                        'canManage' => $canManage, 'myVotes' => $myVotes, 'depth' => $depth + 1,
                    ])
                </div>
            @endforeach
        </div>
    </div>
</article>
