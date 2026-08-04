@extends('layouts.admin')

@section('title', setting('admin.guidance.help.dlyl_almstkhdm', 'دليل المستخدم'))

@section('content')
    {{-- دليل المستخدم (12.6-ج): جدول + محرّر + بحث/وسوم + «هل كان مفيدًا؟» --}}
    <x-page-header
        :title="setting('admin.guidance.help.dlyl_almstkhdm', 'دليل المستخدم')"
        :subtitle="setting('admin.guidance.help.mhtwa_almsaada_ally_byshwfh_almstkhdm_mqalat', 'محتوى المساعدة اللي بيشوفه المستخدم — مقالات وفيديوهات How-to.')"
        :breadcrumbs="[['label' => setting('admin.guidance.help.altwjyh_waldam', 'التوجيه والدعم'), 'url' => route('admin.guidance.index')], ['label' => setting('admin.guidance.help.dlyl_almstkhdm', 'دليل المستخدم')]]">
        <x-slot:action>
            @can('user_guide.create')
                <button type="button" data-modal-open="article-form"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.help.dlyl', '+ دليل') }}</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    <x-tabs :tabs="$tabs" current="help" />

    <x-filters :action="route('admin.guidance.help')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">{{ setting('admin.guidance.help.bhth', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('admin.guidance.help.alanwan_aw_alns', 'العنوان أو النصّ…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.guidance.help.altsnyf', 'التصنيف') }}</span>
            <select name="category" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.guidance.help.alkl', 'الكلّ') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.guidance.help.alhala', 'الحالة') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.guidance.help.alkl', 'الكلّ') }}</option>
                <option value="draft" @selected($filters['status'] === 'draft')>{{ setting('admin.guidance.help.mswda', 'مسودّة') }}</option>
                <option value="published" @selected($filters['status'] === 'published')>{{ setting('admin.guidance.help.mnshwr', 'منشور') }}</option>
                <option value="archived" @selected($filters['status'] === 'archived')>{{ setting('admin.guidance.help.mwrshf', 'مؤرشف') }}</option>
            </select>
        </label>
        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.guidance.help.tsfya', 'تصفية') }}</button>
    </x-filters>

    @if ($articles->isEmpty())
        <x-empty :message="setting('admin.guidance.help.la_adla_bad_aktb_awl_wahd', 'لا أدلّة بعد — اكتب أوّل واحد.')" />
    @else
        <div class="space-y-3">
            @foreach ($articles as $article)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <div class="font-semibold">{{ $article->title }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ $article->category ?: setting('admin.guidance.help.bla_tsnyf', 'بلا تصنيف') }}
                                · {{ $article->views }} {{ setting('admin.guidance.help.mshahda_mfyd', 'مشاهدة · «مفيد»') }} {{ $rates[$article->id] ?? 0 }}%
                            </div>
                            @if ($article->tags)
                                <div class="text-xs mt-1">{{ implode(' · ', (array) $article->tags) }}</div>
                            @endif
                        </div>
                        <x-state-badge :state="$article->status === 'published' ? 'ok' : 'warn'"
                                       :label="$article->status === 'published' ? setting('admin.guidance.help.mnshwr', 'منشور') : setting('admin.guidance.help.mswda', 'مسودّة')" />
                    </div>

                    @can('user_guide.delete')
                        <form method="post" action="{{ route('admin.guidance.help.destroy', $article) }}" class="mt-2"
                              onsubmit="return confirm('{{ setting('admin.guidance.help.nshyl_aldlyl_dh', 'نشيل الدليل ده؟') }}')">
                            @csrf @method('delete')
                            <button class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('admin.guidance.help.hdhf', 'حذف') }}</button>
                        </form>
                    @endcan
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $articles->links() }}</div>
    @endif

    @can('user_guide.create')
        <x-modal id="article-form" :title="setting('admin.guidance.help.dlyl_jdyd', 'دليل جديد')">
            <form method="post" action="{{ route('admin.guidance.help.store') }}" class="space-y-3">
                @csrf
                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input name="title" :label="setting('admin.guidance.help.alanwan_arby', 'العنوان (عربيّ)')" required />
                    <x-form.input name="title_en" :label="setting('admin.guidance.help.alanwan_injlyzy', 'العنوان (إنجليزيّ)')" />
                </div>

                <x-form.input name="category" :label="setting('admin.guidance.help.altsnyf', 'التصنيف')" />
                <x-form.input name="tags" :label="setting('admin.guidance.help.wswm', 'وسوم')" :hint="setting('admin.guidance.help.afsl_bynha_bfasla', 'افصل بينها بفاصلة.')" />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.guidance.help.almhtwa', 'المحتوى') }}</span>
                    <textarea name="body" rows="6" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.guidance.help.alhala', 'الحالة') }}</span>
                    <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="draft">{{ setting('admin.guidance.help.mswda', 'مسودّة') }}</option>
                        <option value="published">{{ setting('admin.guidance.help.mnshwr', 'منشور') }}</option>
                    </select>
                </label>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.help.hfz', 'حفظ') }}</button>
            </form>
        </x-modal>
    @endcan

    @include('admin.courses.partials.toast')
@endsection
