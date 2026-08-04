@extends('layouts.app')
@section('title', $article->title)

@section('content')
    <x-page-header
        :title="$article->title"
        :subtitle="$article->category"
        :breadcrumbs="[
            ['label' => (string) setting('help.show.breadcrumbs_1', 'الدعم'), 'url' => route('help.index')],
            ['label' => (string) setting('help.show.breadcrumbs_2', 'دليل المستخدم'), 'url' => route('help.index')],
            ['label' => $article->title],
        ]" />

    <article class="card p-5 leading-8 text-sm">
        {!! $article->body !!}
    </article>

    {{-- «هل كان مفيدًا؟» — ردّ فوريّ بلا صفحة جديدة (2.17-ب) --}}
    <section class="card p-4 mt-4 flex flex-wrap items-center gap-3" aria-label="{{ setting('help.show.aria_label_1', 'تقييم المقال') }}">
        <span class="text-sm font-semibold">{{ setting('help.show.text_1', 'هل كان مفيدًا؟') }}</span>

        <form method="post" action="{{ route('help.feedback', $article->slug) }}">
            @csrf
            <input type="hidden" name="helpful" value="yes">
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm motion-standard"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <x-icon name="kudos" size="16" /> {{ setting('help.show.text_2', 'أيوه') }}
            </button>
        </form>

        <form method="post" action="{{ route('help.feedback', $article->slug) }}">
            @csrf
            <input type="hidden" name="helpful" value="no">
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm motion-standard"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <x-icon name="warning" size="16" /> {{ setting('help.show.text_3', 'لأ') }}
            </button>
        </form>

        <div class="flex-1"></div>

        {{-- «لم أجد إجابتي» ⟵ تذكرة بعنوان مملوء مسبقًا (24.5) --}}
        <a href="{{ route('complaints.index', ['new' => 1, 'title' => strtr((string) setting('help.show.href_expr_1', 'استفسار حول: :a1'), [':a1' => (string) ($article->title)])]) }}"
           class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('help.show.text_4', 'لم أجد إجابتي') }}</a>
    </section>

    @if ($related->isNotEmpty())
        <h2 class="text-sm font-bold mt-6 mb-2">{{ setting('help.show.text_5', 'مقالات قريبة') }}</h2>
        <div class="grid md:grid-cols-3 gap-3">
            @foreach ($related as $item)
                <a href="{{ route('help.show', $item->slug) }}" class="card p-3 text-sm motion-standard hover:opacity-95">
                    {{ $item->title }}
                </a>
            @endforeach
        </div>
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('complaints.index', ['new' => 1, 'title' => strtr((string) setting('help.show.href_expr_2', 'استفسار حول: :a1'), [':a1' => (string) ($article->title)])]) }}"
       class="btn flex items-center justify-center w-full rounded-xl px-4 py-3 text-sm font-semibold motion-standard"
       style="background: var(--color-brand-500); color: #04201c">{{ setting('help.show.text_6', 'لم أجد إجابتي') }}</a>
@endsection
