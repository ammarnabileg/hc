@extends('layouts.admin')

@section('title', setting('admin.guidance.help.dlyl_almstkhdm', 'دليل المستخدم'))

@section('content')
    @php
        /** حمولة الصفّ لفورم البوب-أب — تُبنى هنا كي يبقى الجدول نظيفًا (2.15-أ-6) */
        $articlePayload = fn ($article) => json_encode([
            'title' => $article->title,
            'title_en' => $article->title_en,
            'category' => $article->category,
            'tags' => implode(', ', (array) $article->tags),
            'body' => $article->body,
            'media_path' => $article->media_path,
            'status' => $article->status,
            'url' => route('admin.guidance.help.update', $article),
        ], JSON_UNESCAPED_UNICODE);
    @endphp

    {{-- دليل المستخدم (12.6-ج): جدول + محرّر + بحث/وسوم + «هل كان مفيدًا؟» --}}
    <x-page-header
        :title="setting('admin.guidance.help.dlyl_almstkhdm', 'دليل المستخدم')"
        :subtitle="setting('admin.guidance.help.mhtwa_almsaada_ally_byshwfh_almstkhdm_mqalat', 'محتوى المساعدة اللي بيشوفه المستخدم — مقالات وفيديوهات How-to.')"
        :breadcrumbs="[['label' => setting('admin.guidance.help.altwjyh_waldam', 'التوجيه والدعم'), 'url' => route('admin.guidance.index')], ['label' => setting('admin.guidance.help.dlyl_almstkhdm', 'دليل المستخدم')]]">
        <x-slot:action>
            @can('user_guide.create')
                <button type="button" data-modal-open="article-form" data-article-new
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
        {{-- تمييز «لا أدلّة أصلًا» عن «الفلتر ما طابقش حاجة» (24.2) --}}
        <x-empty :message="setting('admin.guidance.help.la_adla_bad_aktb_awl_wahd', 'لا أدلّة بعد — اكتب أوّل واحد.')"
                 :filtered="$filters['q'] !== '' || $filters['category'] !== '' || $filters['status'] !== ''" />
    @else
        {{-- ديسكتوب: جدول العنوان (ع/إ) · التصنيف · الحالة · إجراءات (24 · 2.15-ج) --}}
        <div class="hidden md:block card overflow-hidden">
            <x-table :label="setting('admin.guidance.help.dlyl_almstkhdm', 'دليل المستخدم')">
                {{-- كلّ عمودٍ منصوصٍ قابلٌ للفرز بالخادم (12.6-ج) — `x-sort-th` --}}
                <thead style="background: var(--surface-sunken)">
                    <tr>
                        <x-sort-th key="title" :label="setting('admin.guidance.help.alanwan_arby_iinjlyzy', 'العنوان (ع/إ)')" />
                        <x-sort-th key="category" :label="setting('admin.guidance.help.altsnyf', 'التصنيف')" />
                        <x-sort-th key="status" :label="setting('admin.guidance.help.alhala', 'الحالة')" />
                        <th class="p-3 text-end">{{ setting('admin.guidance.help.ijraat', 'إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($articles as $article)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-3 min-w-0">
                                <div class="font-semibold">{{ $article->title }}</div>
                                @if ($article->title_en)
                                    <div class="text-xs" style="color: var(--text-muted)" dir="ltr">{{ $article->title_en }}</div>
                                @endif
                                {{-- التفاصيل المطويّة: المشاهدات/نسبة «مفيد»/الوسوم — بلا فقدان معلومة (2.15-أ-5) --}}
                                <details class="mt-1">
                                    <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('admin.guidance.help.tfasyl_aktr', 'تفاصيل أكتر') }}</summary>
                                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                                        {{ $article->views }} {{ setting('admin.guidance.help.mshahda_mfyd', 'مشاهدة · «مفيد»') }} {{ $rates[$article->id] ?? 0 }}%
                                        @if ($article->tags)
                                            · {{ implode(' · ', (array) $article->tags) }}
                                        @endif
                                    </div>
                                </details>
                            </td>
                            <td class="p-3 text-xs">{{ $article->category ?: setting('admin.guidance.help.bla_tsnyf', 'بلا تصنيف') }}</td>
                            <td class="p-3">
                                <x-state-badge :state="$article->status === 'published' ? 'ok' : 'warn'"
                                               :label="$article->status === 'published' ? setting('admin.guidance.help.mnshwr', 'منشور') : setting('admin.guidance.help.mswda', 'مسودّة')" />
                            </td>
                            <td class="p-3 text-end">
                                <div class="flex gap-3 text-xs flex-wrap justify-end">
                                    @can('user_guide.edit')
                                        <button type="button" class="underline" data-modal-open="article-form"
                                                data-article="{{ $articlePayload($article) }}">{{ setting('admin.guidance.help.tadyl', 'تعديل') }}</button>
                                    @endcan
                                    @can('user_guide.delete')
                                        <form method="post" action="{{ route('admin.guidance.help.destroy', $article) }}"
                                              onsubmit="return confirm('{{ setting('admin.guidance.help.nshyl_aldlyl_dh', 'نشيل الدليل ده؟') }}')">
                                            @csrf @method('delete')
                                            <button class="underline" style="color: var(--color-state-danger)">{{ setting('admin.guidance.help.hdhf', 'حذف') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </div>

        {{-- موبايل: كروت رأسيّة — نفس المعلومة بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="md:hidden space-y-3">
            @foreach ($articles as $article)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <div class="font-semibold">{{ $article->title }}</div>
                            @if ($article->title_en)
                                <div class="text-xs" style="color: var(--text-muted)" dir="ltr">{{ $article->title_en }}</div>
                            @endif
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

                    <div class="flex gap-3 mt-2 text-xs flex-wrap">
                        @can('user_guide.edit')
                            <button type="button" class="underline" data-modal-open="article-form"
                                    data-article="{{ $articlePayload($article) }}">{{ setting('admin.guidance.help.tadyl', 'تعديل') }}</button>
                        @endcan
                        @can('user_guide.delete')
                            <form method="post" action="{{ route('admin.guidance.help.destroy', $article) }}"
                                  onsubmit="return confirm('{{ setting('admin.guidance.help.nshyl_aldlyl_dh', 'نشيل الدليل ده؟') }}')">
                                @csrf @method('delete')
                                <button class="underline" style="color: var(--color-state-danger)">{{ setting('admin.guidance.help.hdhf', 'حذف') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $articles->links() }}</div>
    @endif

    @can('user_guide.create')
        <x-modal id="article-form" :title="setting('admin.guidance.help.dlyl_jdyd', 'دليل جديد')">
            <form method="post" action="{{ route('admin.guidance.help.store') }}" data-article-form class="space-y-3">
                @csrf
                <input type="hidden" name="_method" value="post" data-article-method>

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

                {{-- ⭐ «أيّ حقل رفع يفتح اختَر من المكتبة أو ارفع جديد» (12.4-هـ · 12.6-ج) —
                     بكلّ الأنواع: صورة/فيديو/PDF، ونفس منتقي المكتبة الموحّد --}}
                <div>
                    <x-form.input name="media_path" :label="setting('admin.guidance.help.alwsayt_msr_mn_mktbt_alwsayt', 'الوسائط (مسار من مكتبة الوسائط)')" />
                    <div class="flex items-center gap-2 mt-2">
                        <button type="button" data-media-pick="media_path"
                                class="rounded-xl px-3 py-1.5 text-xs"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <x-icon name="library" size="14" /> {{ setting('media.picker.cta') }}
                        </button>
                        <span data-media-preview="media_path" class="inline-flex items-center"></span>
                    </div>
                </div>

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
    {{-- بوب-أب «اختَر من المكتبة / ارفع جديد» لحقل الوسائط (12.4-هـ · 12.6-ج) --}}
    @include('admin.courses.partials.media-picker-modal')

    @push('scripts')
        <script>
            /* تعبئة فورم الدليل من زرّ التعديل — بلا صفحة جديدة (2.15-أ-6) */
            const articleForm = document.querySelector('[data-article-form]');
            const articleStoreUrl = @json(route('admin.guidance.help.store'));

            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-article], [data-article-new]');
                if (!btn || !articleForm) return;

                const data = btn.dataset.article ? JSON.parse(btn.dataset.article) : null;
                articleForm.action = data?.url || articleStoreUrl;
                articleForm.querySelector('[data-article-method]').value = data ? 'put' : 'post';

                ['title', 'title_en', 'category', 'tags', 'media_path'].forEach((key) => {
                    const field = articleForm.querySelector(`[name="${key}"]`);
                    if (field) field.value = data?.[key] ?? '';
                });

                const body = articleForm.querySelector('[name="body"]');
                if (body) body.value = data?.body ?? '';

                const status = articleForm.querySelector('[name="status"]');
                if (status) status.value = data?.status ?? 'draft';

                // معاينة المرفق الحاليّ فور فتح التعديل — لا تنتظر اختيارًا جديدًا
                const preview = articleForm.querySelector('[data-media-preview="media_path"]');
                if (preview) {
                    preview.innerHTML = data?.media_path
                        ? '<span class="text-xs">' + data.media_path + '</span>'
                        : '';
                }
            });
        </script>
    @endpush
@endsection
