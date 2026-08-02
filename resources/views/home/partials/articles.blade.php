@php
    /**
     * أحدث المقالات المنشورة (21.2-أ) — محتوى عربيّ حقيقيّ مكتوب بأيدينا.
     * ورابط المقال يظهر **فقط** لو صفحته العامّة موجودة فعلًا — فلا رابط مكسور.
     */
    $title = (string) setting('home.articles.title', 'أحدث المقالات');
    $hasPublicPage = \Illuminate\Support\Facades\Route::has('articles.show');
    $dateFormat = (string) setting('home.date_format', 'j F Y');
@endphp

@if ($articles->isNotEmpty())
    <section class="mb-6" aria-labelledby="home-articles-title">
        <h2 id="home-articles-title" class="text-lg md:text-xl font-extrabold mb-3">{{ $title }}</h2>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($articles as $article)
                <article class="card p-4 animate-fadeup" style="animation-delay: {{ $loop->index * 40 }}ms">
                    <div class="flex items-center gap-2 mb-2" style="color: var(--color-brand-500)">
                        @include('home.partials.icon', ['name' => 'article', 'size' => 18])
                        <span class="text-xs" style="color: var(--text-muted)">
                            {{ $article->published_at?->translatedFormat($dateFormat) }}
                        </span>
                    </div>

                    <h3 class="font-bold text-sm break-words">
                        @if ($hasPublicPage)
                            <a href="{{ route('articles.show', $article->slug) }}" class="hover:underline">{{ $article->title }}</a>
                        @else
                            {{ $article->title }}
                        @endif
                    </h3>

                    @if ($article->excerpt)
                        <p class="mt-1 text-xs line-clamp-3" style="color: var(--text-muted)">{{ $article->excerpt }}</p>
                    @endif

                    {{-- اسم الكاتب حاضر — إظهاره إعداد لا قاعدة محروقة (21.2-ي) --}}
                    @if (setting('home.articles.show_author', true) && $article->author)
                        <div class="mt-3 flex items-center gap-2 text-xs" style="color: var(--text-muted)">
                            <x-avatar :user="$article->author" size="6" />
                            <span class="truncate">{{ $article->author->shortName() }}</span>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
