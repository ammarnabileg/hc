@php
    /**
     * قسم تعليقات الفيديو (3.1) — تحت المشغّل مباشرةً:
     * كتابة تعليق · لايك · ردّ · تحميل تدريجيّ 6 كلّ مرّة مع Skeleton · وإشراف بصلاحيّة.
     */
@endphp

{{--
    القسم تحت المشغّل مباشرةً، ومطويّ افتراضيًّا: التعليقات مع ردودها لا يجوز أن تفصل
    بين الفيديو وبوّابة الانتقال على الموبايل — والموبايل معيار قبول (2.15-ج).
--}}
<details class="card p-4" id="comments" data-comments
         data-url="{{ route('learning.lesson.comments.index', [$course, $lesson]) }}"
         @if ($errors->has('body')) open @endif>
    <summary class="flex items-center justify-between gap-2 flex-wrap cursor-pointer list-none"
             style="min-block-size: 2.75rem">
        <h2 class="font-bold flex items-center gap-2">
            @include('learning.partials.icon', ['name' => 'comment', 'box' => 18])
            <span>{{ setting('learning.comments.title') }}</span>
        </h2>
        {{-- عدد التعليقات تحت الدرس (3.4-36) --}}
        <span class="text-xs" style="color: var(--text-muted)">
            {{ $comments_count }} {{ setting('learning.comments.unit') }}
        </span>
    </summary>

    @can('video_comments.create')
        <form method="post" action="{{ route('learning.lesson.comments.store', [$course, $lesson]) }}" class="mt-3 mb-4">
            @csrf
            <label class="sr-only" for="comment-body">{{ setting('learning.comments.title') }}</label>
            <textarea id="comment-body" name="body" rows="3" required
                      maxlength="{{ (int) setting('learning.comments.max_length', 1000) }}"
                      placeholder="{{ setting('learning.comments.placeholder') }}"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('body') }}</textarea>

            @error('body')
                {{-- ماذا حدث + ماذا تفعل (2.17-ب) --}}
                <p class="text-xs mt-1" style="color: var(--color-state-danger)">▲ {{ $message }}</p>
            @enderror

            <div class="flex items-center justify-between gap-2 flex-wrap mt-2">
                <span class="text-xs" style="color: var(--text-muted)">{{ setting('learning.comments.hint') }}</span>
                <button class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    @include('learning.partials.icon', ['name' => 'send'])
                    <span>{{ setting('learning.comments.submit') }}</span>
                </button>
            </div>
        </form>
    @endcan

    <div class="space-y-3 mt-3" data-comments-list>
        @if ($comments->total() === 0)
            {{-- الحالة الفارغة: سطر واحد يشجّع ولا يعاتب (2.17-ج) --}}
            <x-empty :message="setting('learning.comments.empty')" />
        @else
            @include('learning.partials.comment-page', [
                'comments' => $comments,
                'next_page' => $comments->hasMorePages() ? $comments->currentPage() + 1 : null,
            ])
        @endif
    </div>

    {{-- Skeleton أثناء جلب الدفعة التالية — بدل قفزة فراغ (3.1) --}}
    <div class="space-y-3 mt-3 hidden" data-comments-skeleton aria-hidden="true">
        @for ($i = 0; $i < 2; $i++)
            <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
                <div class="skeleton-line" style="inline-size: 40%"></div>
                <div class="skeleton-line mt-2"></div>
                <div class="skeleton-line mt-2" style="inline-size: 70%"></div>
            </div>
        @endfor
    </div>
</details>
