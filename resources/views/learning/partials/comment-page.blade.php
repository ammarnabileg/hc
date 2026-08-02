@php
    /**
     * دفعة تعليقات واحدة (3.1): «6 تعليقات أقدم كلّ ما ينزل لأسفل».
     * نفس الجزء يُستخدَم في أوّل رسم للصفحة وفي كلّ دفعة تُجلَب بعده، فلا يختلف الشكل.
     */
@endphp

@foreach ($comments as $comment)
    @include('learning.partials.comment', ['comment' => $comment, 'reply' => false])
@endforeach

@if ($next_page)
    {{-- الرابط يعمل بلا جافاسكربت، والمراقب يضغطه تلقائيًّا عند النزول --}}
    <a class="comments-more block text-center text-xs rounded-xl px-4 py-3 motion-standard"
       style="background: var(--surface-sunken); color: var(--text-muted); min-block-size: 2.75rem"
       data-comments-next="{{ $next_page }}"
       href="{{ route('learning.lesson.comments.index', [$course, $lesson, 'page' => $next_page]) }}">
        {{ setting('learning.comments.load_more') }}
    </a>
@endif
