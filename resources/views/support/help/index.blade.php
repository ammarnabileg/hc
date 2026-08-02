@extends('layouts.app')
@section('title', 'دليل المستخدم')

@section('content')
    <x-page-header
        title="دليل المستخدم"
        subtitle="إجابة سريعة من غير ما تفتح تذكرة."
        :breadcrumbs="[['label' => 'الدعم', 'url' => route('help.index')], ['label' => 'دليل المستخدم']]" />

    {{-- بحث بارز: هو الفعل الرئيسيّ للصفحة (24.5) --}}
    <form method="get" action="{{ route('help.index') }}" class="card p-4 mb-5">
        <label class="block">
            <span class="block text-sm mb-2 font-semibold">تدوّر على إيه؟</span>
            <div class="flex gap-2">
                <input type="search" name="q" value="{{ $q }}" autofocus
                       placeholder="اكتب كلمة… مثال: الشهادة، الشحن، الستريك"
                       class="flex-1 rounded-xl px-4 py-3 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <button type="submit" class="btn rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">إبحث</button>
            </div>
        </label>
        @if ($category)
            <input type="hidden" name="category" value="{{ $category }}">
        @endif
    </form>

    {{-- التصنيفات كروت --}}
    @if ($categories->isNotEmpty())
        <div class="flex flex-wrap gap-2 mb-5">
            <a href="{{ route('help.index', array_filter(['q' => $q])) }}"
               class="rounded-full px-4 py-2 text-sm motion-standard"
               style="{{ $category === '' ? 'background: var(--color-brand-500); color:#04201c; font-weight:700' : 'background: var(--surface-raised); color: var(--text)' }}">
                كلّ التصنيفات
            </a>
            @foreach ($categories as $row)
                <a href="{{ route('help.index', array_filter(['q' => $q, 'category' => $row->category])) }}"
                   class="rounded-full px-4 py-2 text-sm motion-standard"
                   style="{{ $category === $row->category ? 'background: var(--color-brand-500); color:#04201c; font-weight:700' : 'background: var(--surface-raised); color: var(--text)' }}">
                    {{ $row->category }} <span class="opacity-70">({{ $row->total }})</span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($articles->isEmpty())
        <x-empty message="مفيش نتائج — جرّب كلمة تانية."
                 action="افتح تذكرة"
                 :href="route('complaints.index', ['new' => 1, 'title' => $q !== '' ? 'استفسار عن: '.$q : ''])" />
    @else
        <div class="grid md:grid-cols-2 gap-3">
            @foreach ($articles as $article)
                <a href="{{ route('help.show', $article->slug) }}" class="card p-4 motion-standard hover:opacity-95 animate-fadeup">
                    @if ($article->category)
                        <span class="text-xs" style="color: var(--text-muted)">{{ $article->category }}</span>
                    @endif
                    <h2 class="font-bold mt-1">{{ $article->title }}</h2>
                    <p class="text-sm mt-1 line-clamp-2" style="color: var(--text-muted)">
                        {{ \Illuminate\Support\Str::limit(strip_tags((string) $article->body), 120) }}
                    </p>
                </a>
            @endforeach
        </div>

        <p class="text-sm text-center mt-6" style="color: var(--text-muted)">
            لسّه ملقيتش إجابتك؟
            <a href="{{ route('complaints.index', ['new' => 1, 'title' => $q !== '' ? 'استفسار عن: '.$q : '']) }}"
               class="underline" style="color: var(--color-brand-500)">افتح تذكرة</a>
        </p>
    @endif
@endsection
