@extends('layouts.admin')

@section('title', 'دليل المستخدم')

@section('content')
    {{-- دليل المستخدم (12.6-ج): جدول + محرّر + بحث/وسوم + «هل كان مفيدًا؟» --}}
    <x-page-header
        title="دليل المستخدم"
        subtitle="محتوى المساعدة اللي بيشوفه المستخدم — مقالات وفيديوهات How-to."
        :breadcrumbs="[['label' => 'التوجيه والدعم', 'url' => route('admin.guidance.index')], ['label' => 'دليل المستخدم']]">
        <x-slot:action>
            @can('user_guide.create')
                <button type="button" data-modal-open="article-form"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ دليل</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    <x-tabs :tabs="$tabs" current="help" />

    <x-filters :action="route('admin.guidance.help')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="العنوان أو النصّ…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="block">
            <span class="block text-sm mb-1">التصنيف</span>
            <select name="category" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="block text-sm mb-1">الحالة</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="draft" @selected($filters['status'] === 'draft')>مسودّة</option>
                <option value="published" @selected($filters['status'] === 'published')>منشور</option>
                <option value="archived" @selected($filters['status'] === 'archived')>مؤرشف</option>
            </select>
        </label>
        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">تصفية</button>
    </x-filters>

    @if ($articles->isEmpty())
        <x-empty message="لا أدلّة بعد — اكتب أوّل واحد." />
    @else
        <div class="space-y-3">
            @foreach ($articles as $article)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <div class="font-semibold">{{ $article->title }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ $article->category ?: 'بلا تصنيف' }}
                                · {{ $article->views }} مشاهدة
                                · «مفيد» {{ $rates[$article->id] ?? 0 }}%
                            </div>
                            @if ($article->tags)
                                <div class="text-xs mt-1">{{ implode(' · ', (array) $article->tags) }}</div>
                            @endif
                        </div>
                        <x-state-badge :state="$article->status === 'published' ? 'ok' : 'warn'"
                                       :label="$article->status === 'published' ? 'منشور' : 'مسودّة'" />
                    </div>

                    @can('user_guide.delete')
                        <form method="post" action="{{ route('admin.guidance.help.destroy', $article) }}" class="mt-2"
                              onsubmit="return confirm('نشيل الدليل ده؟')">
                            @csrf @method('delete')
                            <button class="text-xs underline" style="color: var(--color-state-danger)">حذف</button>
                        </form>
                    @endcan
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $articles->links() }}</div>
    @endif

    @can('user_guide.create')
        <x-modal id="article-form" title="دليل جديد">
            <form method="post" action="{{ route('admin.guidance.help.store') }}" class="space-y-3">
                @csrf
                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input name="title" label="العنوان (عربيّ)" required />
                    <x-form.input name="title_en" label="العنوان (إنجليزيّ)" />
                </div>

                <x-form.input name="category" label="التصنيف" />
                <x-form.input name="tags" label="وسوم" hint="افصل بينها بفاصلة." />

                <label class="block">
                    <span class="block text-sm mb-1">المحتوى</span>
                    <textarea name="body" rows="6" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <label class="block">
                    <span class="block text-sm mb-1">الحالة</span>
                    <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="draft">مسودّة</option>
                        <option value="published">منشور</option>
                    </select>
                </label>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">حفظ</button>
            </form>
        </x-modal>
    @endcan

    @include('admin.courses.partials.toast')
@endsection
