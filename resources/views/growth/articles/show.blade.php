@extends('layouts.app')

@section('title', $article->meta_title ?: $article->title)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) ($article->meta_description ?: $article->excerpt)), 155))
@section('og_image', $ogImage)

@if (! $indexable)
    {{-- الفهرسة لكلّ نوع صفحة إعداد لا قرار محروق (21.2-ي) --}}
    @section('noindex', '1')
@endif

@push('head')
    {{-- ⭐ Schema.org Article — لتظهر نتيجةً غنيّة في محرّكات البحث (21.2-ب) --}}
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <link rel="canonical" href="{{ $url }}">
@endpush

@section('content')
    <article class="max-w-3xl mx-auto">
        <x-page-header :title="$article->title"
                       :breadcrumbs="[['label' => setting('growth.articles.index_title', 'المقالات'), 'url' => route('growth.articles.index')], ['label' => $article->title]]" />

        {{-- اسم الكاتب ورابط بروفايله وتاريخ النشر وآخر تحديث (21.2-أ) --}}
        <div class="flex flex-wrap items-center gap-3 text-sm mb-5" style="color: var(--text-muted)">
            @if ($showAuthor && $article->author)
                <a href="{{ $article->author->profileUrl() }}" class="flex items-center gap-2 hover:underline">
                    <x-avatar :user="$article->author" size="8" />
                    <span class="font-semibold" style="color: var(--text)">{{ $article->author->name }}</span>
                </a>
                <span aria-hidden="true">·</span>
            @endif

            <time datetime="{{ $article->published_at?->toDateString() }}">
                نُشر {{ $article->published_at?->translatedFormat('j F Y') }}
            </time>

            @if ($article->updated_at && $article->published_at && $article->updated_at->gt($article->published_at))
                <span aria-hidden="true">·</span>
                <span>آخر تحديث {{ $article->updated_at->translatedFormat('j F Y') }}</span>
            @endif

            @if ($article->article_category)
                <span aria-hidden="true">·</span>
                <span style="color: var(--color-brand-500)">{{ $article->article_category->name_ar }}</span>
            @endif
        </div>

        @if ($article->cover_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($article->cover_path) }}" alt="{{ $article->title }}"
                 loading="lazy" class="w-full rounded-2xl mb-5" style="border: 1px solid var(--border)">
        @endif

        @if ($article->excerpt)
            <p class="text-base mb-5 p-4 rounded-2xl" style="background: var(--surface-sunken); color: var(--text-muted)">
                {{ $article->excerpt }}
            </p>
        @endif

        <div class="article-body space-y-4 leading-8">{!! $body !!}</div>

        @if (is_array($article->tags) && $article->tags !== [])
            <div class="mt-6 flex flex-wrap gap-2">
                @foreach ($article->tags as $tag)
                    <span class="rounded-full px-3 py-1 text-xs" style="background: var(--surface-sunken); color: var(--text-muted)">#{{ $tag }}</span>
                @endforeach
            </div>
        @endif

        {{-- ربط بتدريب أو مسار يظهر في نهاية المقال (21.2-أ) --}}
        @if ($related)
            <a href="{{ $related['url'] }}" class="card p-4 mt-6 flex items-center justify-between gap-3 motion-standard">
                <div>
                    <p class="text-xs" style="color: var(--text-muted)">{{ setting('growth.articles.related_label', 'اتعلّم الموضوع ده عمليًّا') }}</p>
                    <p class="font-bold mt-1">{{ $related['title'] }}</p>
                </div>
                <span class="rounded-xl px-4 py-2 text-sm font-semibold shrink-0"
                      style="background: var(--color-brand-500); color: #04201c">افتح</span>
            </a>
        @endif

        {{-- أزرار المشاركة — بروابط موسومة بـUTM (21.2-ح) وبأيقونات مرسومة عندنا (2.16-ج) --}}
        <section class="mt-6" aria-label="مشاركة المقال">
            <p class="text-xs mb-2" style="color: var(--text-muted)">{{ setting('growth.articles.share_label', 'شارك المقال') }}</p>
            <div class="flex flex-wrap items-center gap-2">
                @php
                    $encoded = urlencode($shareUrl);
                    $titleEncoded = urlencode($article->title);
                @endphp

                <a href="https://wa.me/?text={{ $titleEncoded }}%20{{ $encoded }}" target="_blank" rel="noopener nofollow"
                   class="rounded-xl px-3 py-2 text-sm inline-flex items-center gap-2 motion-standard" style="background: var(--surface-sunken)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 20l1.3-4A8 8 0 1 1 8 18.7L4 20z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    </svg>
                    واتساب
                </a>

                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encoded }}" target="_blank" rel="noopener nofollow"
                   class="rounded-xl px-3 py-2 text-sm inline-flex items-center gap-2 motion-standard" style="background: var(--surface-sunken)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="3" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M7.5 10.5V17M7.5 7.5v.01M11.5 17v-3.6a2 2 0 0 1 4 0V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    لينكدإن
                </a>

                <button type="button" data-copy="{{ $shareUrl }}"
                        class="rounded-xl px-3 py-2 text-sm inline-flex items-center gap-2 motion-standard" style="background: var(--surface-sunken)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="9" y="9" width="11" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M15 5H6a2 2 0 0 0-2 2v9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    انسخ الرابط
                </button>
            </div>
        </section>

        @if ($more->isNotEmpty())
            <section class="mt-8" aria-label="مقالات قريبة">
                <h2 class="font-extrabold mb-3">{{ setting('growth.articles.more_label', 'اقرأ كمان') }}</h2>
                <div class="grid gap-3 md:grid-cols-3">
                    @foreach ($more as $row)
                        <a href="{{ route('growth.articles.show', $row->slug) }}" class="card p-3 motion-standard">
                            <p class="font-semibold text-sm leading-snug">{{ $row->title }}</p>
                            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $row->published_at?->translatedFormat('j F Y') }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
@endsection

@push('scripts')
    <script>
        // ردّ فوريّ لكلّ فعل (2.17): «اتنسخ ✓» جنب الزرّ نفسه لا رسالة عائمة
        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                const original = button.innerHTML;
                try {
                    await navigator.clipboard.writeText(button.dataset.copy);
                    button.textContent = 'اتنسخ ✓';
                } catch (error) {
                    button.textContent = 'انسخه يدويًّا';
                }
                setTimeout(() => { button.innerHTML = original; }, 1800);
            });
        });
    </script>
@endpush
