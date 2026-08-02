@extends('layouts.app')
@section('title', $article->title)

@section('content')
    <x-page-header
        :title="$article->title"
        :subtitle="$article->category"
        :breadcrumbs="[
            ['label' => 'الدعم', 'url' => route('help.index')],
            ['label' => 'دليل المستخدم', 'url' => route('help.index')],
            ['label' => $article->title],
        ]" />

    <article class="card p-5 leading-8 text-sm">
        {!! $article->body !!}
    </article>

    {{-- «هل كان مفيدًا؟» — ردّ فوريّ بلا صفحة جديدة (2.17-ب) --}}
    <section class="card p-4 mt-4 flex flex-wrap items-center gap-3" aria-label="تقييم المقال">
        <span class="text-sm font-semibold">هل كان مفيدًا؟</span>

        <form method="post" action="{{ route('help.feedback', $article->slug) }}">
            @csrf
            <input type="hidden" name="helpful" value="yes">
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm motion-standard"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <x-icon name="kudos" size="16" /> أيوه
            </button>
        </form>

        <form method="post" action="{{ route('help.feedback', $article->slug) }}">
            @csrf
            <input type="hidden" name="helpful" value="no">
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm motion-standard"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <x-icon name="warning" size="16" /> لأ
            </button>
        </form>

        <div class="flex-1"></div>

        {{-- «لم أجد إجابتي» ⟵ تذكرة بعنوان مملوء مسبقًا (24.5) --}}
        <a href="{{ route('complaints.index', ['new' => 1, 'title' => 'استفسار حول: '.$article->title]) }}"
           class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">لم أجد إجابتي</a>
    </section>

    @if ($related->isNotEmpty())
        <h2 class="text-sm font-bold mt-6 mb-2">مقالات قريبة</h2>
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
    <a href="{{ route('complaints.index', ['new' => 1, 'title' => 'استفسار حول: '.$article->title]) }}"
       class="btn flex items-center justify-center w-full rounded-xl px-4 py-3 text-sm font-semibold motion-standard"
       style="background: var(--color-brand-500); color: #04201c">لم أجد إجابتي</a>
@endsection
