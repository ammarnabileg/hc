@extends('layouts.app')

@section('title', 'المقالات')

@section('content')
    <x-page-header title="المقالات"
                   subtitle="مسودّة ⟵ قيد المراجعة ⟵ منشورة — والكاتب ما ينشرش مقاله بنفسه."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'المقالات'],
                   ]">
        <x-slot:action>
            @can('articles.create')
                <a href="{{ route('admin.articles.create') }}"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                   style="background: var(--color-brand-500); color: #04201c">+ مقال</a>
            @endcan
        </x-slot:action>
    </x-page-header>

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger); color: var(--color-state-danger)">
            {{ $errors->first() }}
        </div>
    @endif

    <x-tabs :current="$status ?: 'all'" :tabs="collect($statuses)->map(fn ($label, $key) => [
        'key' => $key,
        'label' => $label,
        'count' => $counts[$key] ?? null,
        'url' => route('admin.articles.index', ['status' => $key]),
    ])->values()->prepend([
        'key' => 'all',
        'label' => 'الكلّ',
        'url' => route('admin.articles.index'),
    ])->all()" />

    <x-filters :action="route('admin.articles.index')">
        <input type="hidden" name="status" value="{{ $status }}">

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ request('q') }}" class="rounded-xl px-3 py-2 text-sm w-52"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">التصنيف</span>
            <select name="category" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">فلترة</button>
    </x-filters>

    @if ($articles->isEmpty())
        <x-empty message="مافيش مقالات لسه — اكتب أوّل واحد."
                 action="+ مقال" :href="\Illuminate\Support\Facades\Route::has('admin.articles.create') ? route('admin.articles.create') : '#'" />
    @else
        <div class="card overflow-hidden">
            <table class="hidden md:table w-full text-sm">
                <thead style="background: var(--surface-sunken)">
                    <tr class="text-xs" style="color: var(--text-muted)">
                        <th class="text-start p-3">العنوان</th>
                        <th class="text-start p-3">الكاتب</th>
                        <th class="text-start p-3">التصنيف</th>
                        <th class="text-start p-3">الحالة</th>
                        <th class="text-start p-3">آخر تحديث</th>
                        <th class="text-start p-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($articles as $article)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-3 font-semibold">{{ $article->title }}</td>
                            <td class="p-3">{{ $article->author?->name }}</td>
                            <td class="p-3">{{ $article->article_category?->name_ar ?? '—' }}</td>
                            <td class="p-3">
                                <x-state-badge :state="match ($article->status) { 'published' => 'ok', 'in_review' => 'warn', 'archived' => 'idle', default => 'idle' }"
                                               :label="$statuses[$article->status] ?? $article->status" />
                            </td>
                            <td class="p-3 text-xs">{{ $article->updated_at?->diffForHumans() }}</td>
                            <td class="p-3 text-xs space-x-2 space-x-reverse">
                                @can('articles.edit')
                                    <a class="underline" href="{{ route('admin.articles.edit', $article) }}">تعديل</a>
                                @endcan

                                {{-- ⭐ زرّ النشر يظهر فقط لمن يملكه **ولغير الكاتب** (2.15-أ-7 · 21.2-أ) --}}
                                @if ($workflow->mayPublish($article, auth()->user()))
                                    <form method="post" action="{{ route('admin.articles.publish', $article) }}" class="inline">
                                        @csrf<button class="underline">نشر</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="md:hidden">
                @foreach ($articles as $article)
                    <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold">{{ $article->title }}</span>
                            <x-state-badge :state="match ($article->status) { 'published' => 'ok', 'in_review' => 'warn', default => 'idle' }"
                                           :label="$statuses[$article->status] ?? $article->status" />
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">{{ $article->author?->name }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-5">{{ $articles->links() }}</div>
    @endif
@endsection

@section('mobile_action')
    @can('articles.create')
        <a href="{{ route('admin.articles.create') }}"
           class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c">+ مقال</a>
    @endcan
@endsection
