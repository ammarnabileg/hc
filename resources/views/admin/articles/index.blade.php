@extends('layouts.admin')

@section('title', setting('admin.articles.index.almqalat', 'المقالات'))

@section('content')
    <x-page-header :title="setting('admin.articles.index.almqalat', 'المقالات')"
                   :subtitle="setting('admin.articles.index.mswda_qyd_almrajaa_mnshwra_walkatb_ma', 'مسودّة ⟵ قيد المراجعة ⟵ منشورة — والكاتب ما ينشرش مقاله بنفسه.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.articles.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.articles.index.almqalat', 'المقالات')],
                   ]">
        <x-slot:action>
            @can('articles.create')
                <a href="{{ route('admin.articles.create') }}"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.articles.index.mqal', '+ مقال') }}</a>
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
        'label' => setting('admin.articles.index.alkl', 'الكلّ'),
        'url' => route('admin.articles.index'),
    ])->all()" />

    <x-filters :action="route('admin.articles.index')">
        <input type="hidden" name="status" value="{{ $status }}">

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.articles.index.bhth', 'بحث') }}</span>
            <input type="search" name="q" value="{{ request('q') }}" class="rounded-xl px-3 py-2 text-sm w-52"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.articles.index.altsnyf', 'التصنيف') }}</span>
            <select name="category" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.articles.index.alkl', 'الكلّ') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name_ar }}</option>
                @endforeach
            </select>
        </label>

        {{-- الوسوم صارت حقلًا في المحرّر — فصار لها فلتر يصل إليها (21.2-أ) --}}
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('articles.tags_label') }}</span>
            <select name="tag" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('articles.filters.any') }}</option>
                @foreach ($tagList as $tag)
                    <option value="{{ $tag }}" @selected(request('tag') === $tag)>{{ $tag }}</option>
                @endforeach
            </select>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.articles.index.fltra', 'فلترة') }}</button>
    </x-filters>

    @if ($articles->isEmpty())
        <x-empty :message="setting('admin.articles.index.mafysh_mqalat_lsh_aktb_awl_wahd', 'مافيش مقالات لسه — اكتب أوّل واحد.')"
                 :action="setting('admin.articles.index.mqal', '+ مقال')" :href="\Illuminate\Support\Facades\Route::has('admin.articles.create') ? route('admin.articles.create') : '#'" />
    @else
        <div class="card overflow-hidden">
            <table class="hidden md:table w-full text-sm">
                <thead style="background: var(--surface-sunken)">
                    <tr class="text-xs" style="color: var(--text-muted)">
                        <th class="text-start p-3">{{ setting('admin.articles.index.alanwan', 'العنوان') }}</th>
                        <th class="text-start p-3">{{ setting('admin.articles.index.alkatb', 'الكاتب') }}</th>
                        <th class="text-start p-3">{{ setting('admin.articles.index.altsnyf', 'التصنيف') }}</th>
                        <th class="text-start p-3">{{ setting('admin.articles.index.alhala', 'الحالة') }}</th>
                        <th class="text-start p-3">{{ setting('admin.articles.index.akhr_thdyth', 'آخر تحديث') }}</th>
                        <th class="text-start p-3">{{ setting('admin.articles.index.ijraat', 'إجراءات') }}</th>
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
                                    <a class="underline" href="{{ route('admin.articles.edit', $article) }}">{{ setting('admin.articles.index.tadyl', 'تعديل') }}</a>
                                @endcan

                                {{-- ⭐ زرّ النشر يظهر فقط لمن يملكه **ولغير الكاتب** (2.15-أ-7 · 21.2-أ) --}}
                                @if ($workflow->mayPublish($article, auth()->user()))
                                    <form method="post" action="{{ route('admin.articles.publish', $article) }}" class="inline">
                                        @csrf<button class="underline">{{ setting('admin.articles.index.nshr', 'نشر') }}</button>
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

    {{--
      مسار `admin.articles.categories.store` كان موجودًا بتحقّقٍ على `name_ar`
      **وبلا فورمٍ واحد في المشروع كلّه** — نقطةُ نهايةٍ بلا شاشة. و`sort_order`
      يُقرأ في أربعة `orderBy` ولا يُكتَب — فالترتيب كان صفرًا للجميع (21.2-ي).
    --}}
    @can('articles.create')
        <div class="card p-4 mt-6">
            <h2 class="font-bold text-sm mb-2">{{ setting('articles.categories_label') }}</h2>

            <div class="flex flex-wrap gap-2 mb-3">
                @foreach ($categories as $category)
                    <span class="rounded-full px-3 py-1 text-xs" style="background: var(--surface-raised)">
                        {{ $category->name_ar }} · {{ $category->sort_order }}
                    </span>
                @endforeach
            </div>

            <form method="post" action="{{ route('admin.articles.categories.store') }}"
                  class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="text-xs">
                    <span class="block mb-1" style="color: var(--text-muted)">{{ setting('articles.category_name_label') }}</span>
                    <input type="text" name="name_ar" required maxlength="190"
                           class="rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-xs">
                    <span class="block mb-1" style="color: var(--text-muted)">{{ setting('articles.category_order_label') }}</span>
                    <input type="number" name="sort_order" value="0" min="0" max="999"
                           class="rounded-xl px-3 py-2 text-sm w-24"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">
                    {{ setting('articles.category_add_cta') }}
                </button>
            </form>
        </div>
    @endcan
@endsection

@section('mobile_action')
    @can('articles.create')
        <a href="{{ route('admin.articles.create') }}"
           class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.articles.index.mqal', '+ مقال') }}</a>
    @endcan
@endsection
