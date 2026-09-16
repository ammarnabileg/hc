@extends('layouts.admin')

{{--
    ⭐⭐ فورم صفحة الهبوط **المستقلّة** (12.2.3 `landing_pages`) — إنشاءٌ (بلا
    `$landingPage->exists`) أو تعديل، ومربوطة دائمًا بكيانٍ واحد (بندل/منتج).
    والحقول متعمَّدة البساطة (راجع تعليق `LandingPageController`): لا محرّك
    وراثة كـ`BundleLanding` — عنوانٌ ووعدٌ وصورة ونداء فعل وقائمتا نتائج/أسئلة
    وفقرة حرّة، ونشرٌ بحقل حالة صريح لا تلقائيّ.
--}}

@section('title', $landingPage->exists ? setting('admin.landing_pages.form.tadyl', 'تعديل صفحة هبوط') : setting('admin.landing_pages.form.jdyd', 'صفحة هبوط جديدة'))

@section('content')
    <x-page-header :title="$landingPage->exists ? $landingPage->title() : setting('admin.landing_pages.form.jdyd', 'صفحة هبوط جديدة')"
                   :subtitle="setting('admin.landing_pages.form.subtitle', 'مربوطة بـ:').' '.$landingable->name_ar"
                   :breadcrumbs="[
                       ['label' => setting('store.breadcrumb_label', 'المتجر'), 'url' => route('admin.store.index')],
                       ['label' => setting('admin.landing_pages.form.sfhat_alhbwt', 'صفحة هبوط')],
                   ]" />

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger); color: var(--color-state-danger)">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[2fr_1fr]">
        <form method="post"
              action="{{ $landingPage->exists ? route('admin.store.landing-pages.update', $landingPage) : route('admin.store.landing-pages.store') }}"
              class="card p-4 space-y-3">
            @csrf
            @if ($landingPage->exists)
                @method('PUT')
            @else
                <input type="hidden" name="type" value="{{ $landingableType }}">
                <input type="hidden" name="id" value="{{ $landingable->id }}">
            @endif

            <x-form.input name="headline" :label="setting('admin.landing_pages.form.alanwan_alrysy', 'العنوان الرئيسيّ (Headline)')"
                          :value="$landingPage->headline" :placeholder="$landingable->name_ar" />

            <label class="block text-sm">
                <span class="block mb-1">{{ setting('admin.landing_pages.form.alwad_alfray', 'الوعد/العنوان الفرعيّ') }}</span>
                <textarea name="subheadline" rows="2" placeholder="{{ $landingable->description ?? '' }}" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('subheadline', $landingPage->subheadline) }}</textarea>
            </label>

            <div>
                <x-form.input name="hero_image_path" :label="setting('admin.landing_pages.form.swrat_alhyro', 'صورة الهيرو')" :value="$landingPage->hero_image_path" />
                {{-- ⭐ «أيّ حقل رفع يفتح اختَر من المكتبة أو ارفع جديد» (12.4-هـ) --}}
                <div class="flex items-center gap-2 mt-2">
                    <button type="button" data-media-pick="hero_image_path"
                            class="rounded-xl px-3 py-1.5 text-xs"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <x-icon name="library" size="14" /> {{ setting('media.picker.cta') }}
                    </button>
                    <span data-media-preview="hero_image_path" class="inline-flex items-center"></span>
                </div>
            </div>

            <x-form.input name="cta_label" :label="setting('admin.landing_pages.form.nass_zr_alfl', 'نصّ زرّ النداء (CTA)')"
                          :value="$landingPage->cta_label" :placeholder="setting('landing_pages.default_cta_label', 'اعرف أكتر')" />

            <label class="block text-sm">
                <span class="block mb-1">{{ setting('admin.landing_pages.form.alntayj', 'بعد الصفحة دي هتقدر… (سطرٌ لكلّ نتيجة)') }}</span>
                <textarea name="outcomes" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ old('outcomes', implode("\n", (array) ($landingPage->outcomes ?? []))) }}</textarea>
            </label>

            <label class="block text-sm">
                <span class="block mb-1">{{ setting('admin.landing_pages.form.alasela', 'الأسئلة الشائعة، سطر: سؤال | إجابة') }}</span>
                <textarea name="faq" rows="5" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ old('faq', collect((array) ($landingPage->faq ?? []))->map(fn ($r) => ($r['q'] ?? '').' | '.($r['a'] ?? ''))->implode("\n")) }}</textarea>
            </label>

            <label class="block text-sm">
                <span class="block mb-1">{{ setting('admin.landing_pages.form.fqra_hra', 'فقرة حرّة إضافيّة (اختياريّ)') }}</span>
                <textarea name="body" rows="6" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('body', $landingPage->body) }}</textarea>
            </label>

            @if ($landingPage->exists)
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.landing_pages.form.alhala', 'الحالة') }}</span>
                    <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="draft" @selected($landingPage->status === 'draft')>{{ setting('admin.landing_pages.form.mswda', 'مسوّدة') }}</option>
                        <option value="published" @selected($landingPage->status === 'published')>{{ setting('admin.landing_pages.form.mnshwra', 'منشورة') }}</option>
                    </select>
                    <span class="block mt-1 text-xs" style="color: var(--text-muted)">{{ setting('admin.landing_pages.form.status_hint', 'اختَر «منشورة» بعد ما تراجع الصفحة، والرابط العامّ ما يفتحش قبلها.') }}</span>
                </label>
            @endif

            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.landing_pages.form.hfz', 'حفظ') }}</button>
        </form>

        <div class="space-y-4">
            <div class="card p-4 space-y-2 text-sm">
                <h2 class="font-bold">{{ setting('admin.landing_pages.form.alhala_2', 'الحالة') }}</h2>
                <x-state-badge :state="$landingPage->status === 'published' ? 'ok' : ($landingPage->status === 'archived' ? 'muted' : 'idle')"
                               :label="[
                                   'draft' => setting('admin.landing_pages.form.mswda', 'مسوّدة'),
                                   'published' => setting('admin.landing_pages.form.mnshwra', 'منشورة'),
                                   'archived' => setting('admin.landing_pages.form.mwrshfa', 'مؤرشفة'),
                               ][$landingPage->status] ?? $landingPage->status" />

                @if ($landingPage->exists && $landingPage->isPublished())
                    <a href="{{ route('landing-pages.show', $landingPage->slug) }}" target="_blank" rel="noopener"
                       class="block text-xs underline">{{ setting('admin.landing_pages.form.myana_alsfha_almnshwra', 'معاينة الصفحة المنشورة ↗') }}</a>
                @endif

                @if ($landingPage->exists)
                    @can('landing_pages.archive')
                        @if (! $landingPage->isArchived())
                            <form method="post" action="{{ route('admin.store.landing-pages.archive', $landingPage) }}">
                                @csrf
                                <button class="w-full rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.landing_pages.form.arshf', 'أرشف الصفحة') }}</button>
                            </form>
                        @endif
                    @endcan

                    @can('landing_pages.restore')
                        @if ($landingPage->isArchived())
                            <form method="post" action="{{ route('admin.store.landing-pages.restore', $landingPage) }}">
                                @csrf
                                <button class="w-full rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.landing_pages.form.astad', 'استعد الصفحة') }}</button>
                            </form>
                        @endif
                    @endcan

                    @can('landing_pages.delete')
                        <form method="post" action="{{ route('admin.store.landing-pages.destroy', $landingPage) }}"
                              onsubmit="return confirm('{{ setting('admin.landing_pages.form.confirm_delete', 'حذف نهائيّ بلا رجوع. متأكّد؟') }}')">
                            @csrf
                            @method('DELETE')
                            <button class="w-full rounded-xl px-4 py-2 text-sm" style="color: var(--color-state-danger); border: 1px solid var(--color-state-danger); background: transparent">{{ setting('admin.landing_pages.form.hthf_nhaay', 'حذف نهائيّ') }}</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
    </div>
@endsection
