@extends('layouts.app')

@section('title', setting('growth.articles.index_title', 'مقالات المنصّة'))
@section('meta_description', setting('growth.articles.index_subtitle', 'محتوًى عربيّ مكتوب بأيدينا — نصائح ومسارات تعلّم وتجارب حقيقيّة.'))
@section('og_image', $ogImage)

@if (! $indexable)
    @section('noindex', '1')
@endif

@section('content')
    <x-page-header :title="setting('growth.articles.index_title', 'مقالات المنصّة')"
                   :subtitle="setting('growth.articles.index_subtitle', 'محتوًى عربيّ مكتوب بأيدينا — تقرأه بلا حساب.')" />

    {{-- فلتران ظاهران فقط: التصنيف والبحث — والباقي لا لزوم له هنا (2.15-أ-4) --}}
    <form method="get" class="card p-3 mb-5 flex flex-wrap items-center gap-2">
        <label class="flex-1 min-w-48">
            <span class="sr-only">{{ setting('articles.index.text_1', 'ابحث في المقالات') }}</span>
            <input type="search" name="q" value="{{ $term }}"
                   placeholder="{{ setting('growth.articles.search_placeholder', 'دوّر على موضوع…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        @if ($categories->isNotEmpty())
            <label>
                <span class="sr-only">{{ setting('articles.index.text_2', 'التصنيف') }}</span>
                <select name="category" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('articles.index.text_3', 'كلّ التصنيفات') }}</option>
                    @foreach ($categories as $row)
                        <option value="{{ $row->id }}" @selected($category === $row->id)>{{ $row->name_ar }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <button type="submit" class="btn rounded-xl px-5 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('articles.index.text_4', 'بحث') }}</button>
    </form>

    @if ($articles->isEmpty())
        <x-empty :message="setting('growth.articles.empty', 'لسّه مافيش مقالات منشورة هنا — قريب إن شاء الله.')" />
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($articles as $article)
                <article class="card p-4 flex flex-col gap-2 animate-fadeup">
                    @if ($article->article_category)
                        <span class="text-xs" style="color: var(--color-brand-500)">{{ $article->article_category->name_ar }}</span>
                    @endif

                    <h2 class="font-extrabold leading-snug">
                        <a href="{{ route('growth.articles.show', $article->slug) }}" class="hover:underline">{{ $article->title }}</a>
                    </h2>

                    @if ($article->excerpt)
                        <p class="text-sm" style="color: var(--text-muted)">{{ \Illuminate\Support\Str::limit($article->excerpt, 120) }}</p>
                    @endif

                    <div class="mt-auto pt-2 text-xs flex items-center gap-2" style="color: var(--text-muted)">
                        @if ($article->author)
                            <x-avatar :user="$article->author" size="6" />
                            <span>{{ $article->author->shortName() }}</span>
                            <span aria-hidden="true">·</span>
                        @endif
                        <time datetime="{{ $article->published_at?->toDateString() }}">
                            {{ $article->published_at?->translatedFormat('j F Y') }}
                        </time>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-5">{{ $articles->links() }}</div>
    @endif
@endsection
