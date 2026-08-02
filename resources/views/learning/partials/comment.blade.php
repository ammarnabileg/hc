@php
    /**
     * تعليق واحد تحت الفيديو (3.1): لايك + ردّ، وأفعال الإشراف تظهر لمن يملكها فقط
     * — **العنصر المحظور يُخفى ولا يُعطَّل** (2.15-أ-7).
     */
    $reply = $reply ?? false;
    $liked = (bool) ($comment->viewer_liked ?? false);
@endphp

<article id="comment-{{ $comment->id }}" class="rounded-xl p-3 {{ $reply ? 'mt-2' : '' }}"
         style="background: var(--surface-{{ $reply ? 'raised' : 'sunken' }})">
    <div class="flex items-start gap-2">
        <x-avatar :user="$comment->user" size="8" />

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold">{{ $comment->user?->name }}</span>
                <span class="text-xs" style="color: var(--text-muted)">{{ $comment->created_at?->diffForHumans() }}</span>

                @if ($comment->is_hidden)
                    {{-- المخفيّ لا يراه إلّا الإشراف وصاحبه — وبعلامة مكتوبة لا بلونٍ وحده (2.16) --}}
                    <x-state-badge state="warn" :label="setting('learning.comments.hidden_badge')" />
                @endif
            </div>

            <p class="text-sm mt-1 whitespace-pre-line break-words">{{ $comment->body }}</p>

            <div class="flex items-center gap-1 flex-wrap mt-2">
                {{-- اللايك: ردّ فوريّ بالجافاسكربت، ويعمل كفورم عاديّ بدونه (2.17-أ) --}}
                <form method="post" action="{{ route('learning.lesson.comments.like', [$course, $lesson, $comment]) }}"
                      data-like-form>
                    @csrf
                    <button class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs motion-standard"
                            style="min-block-size: 2.75rem; color: {{ $liked ? 'var(--color-brand-400)' : 'var(--text-muted)' }}"
                            aria-pressed="{{ $liked ? 'true' : 'false' }}"
                            aria-label="{{ setting('learning.comments.like') }}">
                        @include('learning.partials.icon', ['name' => 'heart'])
                        <span data-like-count>{{ (int) $comment->likes_count }}</span>
                    </button>
                </form>

                @if (! $reply)
                    <button type="button" class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs motion-standard"
                            style="min-block-size: 2.75rem; color: var(--text-muted)"
                            data-reply-toggle="reply-{{ $comment->id }}">
                        @include('learning.partials.icon', ['name' => 'reply'])
                        <span>{{ setting('learning.comments.reply') }}</span>
                    </button>
                @endif

                @can('video_comments.archive')
                    @if (! $comment->is_hidden)
                        <form method="post" action="{{ route('learning.lesson.comments.hide', [$course, $lesson, $comment]) }}">
                            @csrf
                            <button class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs motion-standard"
                                    style="min-block-size: 2.75rem; color: var(--text-muted)">
                                @include('learning.partials.icon', ['name' => 'eye-off'])
                                <span>{{ setting('learning.comments.hide') }}</span>
                            </button>
                        </form>
                    @endif
                @endcan

                @can('video_comments.restore')
                    @if ($comment->is_hidden)
                        <form method="post" action="{{ route('learning.lesson.comments.unhide', [$course, $lesson, $comment]) }}">
                            @csrf
                            <button class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs motion-standard"
                                    style="min-block-size: 2.75rem; color: var(--text-muted)">
                                @include('learning.partials.icon', ['name' => 'eye'])
                                <span>{{ setting('learning.comments.unhide') }}</span>
                            </button>
                        </form>
                    @endif
                @endcan

                @can('video_comments.delete', $comment)
                    <form method="post" action="{{ route('learning.lesson.comments.destroy', [$course, $lesson, $comment]) }}"
                          onsubmit="return confirm(@js(setting('learning.comments.delete_confirm')))">
                        @csrf
                        @method('delete')
                        <button class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs motion-standard"
                                style="min-block-size: 2.75rem; color: var(--text-muted)">
                            @include('learning.partials.icon', ['name' => 'trash'])
                            <span>{{ setting('learning.comments.delete') }}</span>
                        </button>
                    </form>
                @endcan
            </div>

            @if (! $reply)
                @can('video_comments.create')
                    <form method="post" action="{{ route('learning.lesson.comments.store', [$course, $lesson]) }}"
                          id="reply-{{ $comment->id }}" class="mt-2 hidden" data-reply-form>
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                        <label class="sr-only" for="reply-body-{{ $comment->id }}">{{ setting('learning.comments.reply') }}</label>
                        <textarea id="reply-body-{{ $comment->id }}" name="body" rows="2" required
                                  maxlength="{{ (int) setting('learning.comments.max_length', 1000) }}"
                                  placeholder="{{ setting('learning.comments.reply_placeholder') }}"
                                  class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface); border: 1px solid var(--border); color: var(--text)"></textarea>
                        <button class="btn mt-2 inline-flex items-center gap-1 rounded-xl px-4 py-2 text-xs font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">
                            @include('learning.partials.icon', ['name' => 'send'])
                            <span>{{ setting('learning.comments.reply_submit') }}</span>
                        </button>
                    </form>
                @endcan

                @foreach ($comment->replies ?? [] as $child)
                    @include('learning.partials.comment', ['comment' => $child, 'reply' => true])
                @endforeach
            @endif
        </div>
    </div>
</article>
