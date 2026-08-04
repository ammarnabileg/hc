@extends('layouts.admin')

@section('title', setting('admin.courses.media.mktba_alwsayt', 'مكتبة الوسائط'))

@section('content')
    {{-- مكتبة الوسائط المركزيّة (12.4-د · 24.1) --}}
    <x-page-header
        :title="setting('admin.courses.media.mktba_alwsayt', 'مكتبة الوسائط')"
        :subtitle="setting('admin.courses.media.arfa_almlf_mra_wastkhdmh_fy_ay_mkan_walmkrr', 'ارفع الملفّ مرّة واستخدمه في أيّ مكان — والمكرَّر بنكتشفه بالهاش.')"
        :breadcrumbs="[['label' => setting('admin.courses.media.idara_altdryb', 'إدارة التدريب'), 'url' => route('admin.courses.index')], ['label' => setting('admin.courses.media.mktba_alwsayt', 'مكتبة الوسائط')]]">
        <x-slot:action>
            @can('media_library.create')
                <button type="button" data-modal-open="media-upload"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.media.rfa', 'رفع') }}</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    @include('admin.courses.partials.nav', ['current' => 'media'])

    <x-filters :action="route('admin.media.index')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">{{ setting('admin.courses.media.bhth', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('admin.courses.media.asm_almlf', 'اسم الملفّ…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.courses.media.alnwa', 'النوع') }}</span>
            <select name="kind" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.courses.media.alkl', 'الكلّ') }}</option>
                @foreach (['image' => setting('admin.courses.media.swra', 'صورة'), 'pdf' => 'PDF', 'doc' => 'Word', 'audio' => setting('admin.courses.media.swt', 'صوت'), 'video' => setting('admin.courses.media.fydyw', 'فيديو')] as $key => $label)
                    <option value="{{ $key }}" @selected($filters['kind'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.courses.media.almjld', 'المجلّد') }}</span>
            <select name="folder" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.courses.media.alkl', 'الكلّ') }}</option>
                @foreach ($folders as $folder)
                    <option value="{{ $folder }}" @selected($filters['folder'] === $folder)>{{ $folder }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.courses.media.tsfya', 'تصفية') }}</button>

        <x-slot:advanced>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.courses.media.alwsm', 'الوسم') }}</span>
                <select name="tag" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('admin.courses.media.alkl', 'الكلّ') }}</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag }}" @selected($filters['tag'] === $tag)>{{ $tag }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex items-center gap-2 text-sm mt-6">
                <input type="checkbox" name="unused" value="1" @checked($filters['unused'])> {{ setting('admin.courses.media.ghyr_mstkhdm_fqt', 'غير مستخدَم فقط') }}
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($items->isEmpty())
        <x-empty :message="setting('admin.courses.media.almktba_fadya_arfa_awl_mlf', 'المكتبة فاضية — ارفع أوّل ملفّ.')" />
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach ($items as $item)
                <div class="card p-3">
                    @if (str_starts_with((string) $item->mime, 'image/'))
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($item->disk ?: 'public')->url($item->path) }}"
                             alt="{{ $item->name }}" loading="lazy"
                             class="w-full h-24 object-cover rounded-lg mb-2">
                    @else
                        <div class="w-full h-24 rounded-lg mb-2 flex items-center justify-center text-2xl"
                             style="background: var(--surface-sunken)" aria-hidden="true"><x-icon name="document" size="16" /></div>
                    @endif

                    <div class="text-sm font-semibold truncate" title="{{ $item->name }}">{{ $item->name }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ $item->size ? round($item->size / 1024).setting('admin.courses.media.k_b', ' ك.ب') : '—' }}
                        {!! strtr(setting('admin.courses.media.mstkhdm_fy_v1_mkan', '· مستخدَم في :v1 مكان'), [':v1' => e($usage[$item->id] ?? 0)]) !!}
                    </div>

                    <details class="mt-2">
                        <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('admin.courses.media.ijraat', 'إجراءات') }}</summary>
                        <div class="mt-2 space-y-2">
                            <input type="text" readonly value="{{ $item->path }}"
                                   class="w-full rounded-lg px-2 py-1 text-xs" data-copy
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                            @can('media_library.edit')
                                <form method="post" action="{{ route('admin.media.update', $item) }}" class="space-y-2">
                                    @csrf @method('put')
                                    <input type="text" name="name" value="{{ $item->name }}"
                                           class="w-full rounded-lg px-2 py-1 text-xs"
                                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    <input type="text" name="tags" value="{{ implode(',', (array) $item->tags) }}"
                                           placeholder="{{ setting('admin.courses.media.wswm_mfswla_bfasla', 'وسوم مفصولة بفاصلة') }}" class="w-full rounded-lg px-2 py-1 text-xs"
                                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    <input type="text" name="folder" value="{{ $item->folder }}" placeholder="{{ setting('admin.courses.media.mjld', 'مجلّد') }}"
                                           class="w-full rounded-lg px-2 py-1 text-xs"
                                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    <button class="text-xs underline">{{ setting('admin.courses.media.hfz', 'حفظ') }}</button>
                                </form>
                            @endcan

                            @can('media_library.delete')
                                <form method="post" action="{{ route('admin.media.destroy', $item) }}"
                                      onsubmit="return confirm('{{ ($usage[$item->id] ?? 0) > 0 ? setting('media.delete.in_use_warning', 'الملفّ ده مستخدَم في أماكن تانية — متأكّد؟') : setting('admin.courses.media.nshyl_almlf', 'نشيل الملفّ؟') }}')">
                                    @csrf @method('delete')
                                    @if (($usage[$item->id] ?? 0) > 0)
                                        <input type="hidden" name="force" value="1">
                                    @endif
                                    <button class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('admin.courses.media.hdhf', 'حذف') }}</button>
                                </form>
                            @endcan
                        </div>
                    </details>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    @endif

    @can('media_library.create')
        <x-modal id="media-upload" :title="setting('admin.courses.media.rfa_mlf', 'رفع ملفّ')">
            <form method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <p class="text-sm" style="color: var(--text-muted)">
                    {{ setting('admin.courses.media.lw_almlf_atrfa_qbl_kdh_hnstkhdm_alnskha', 'لو الملفّ اترفع قبل كده هنستخدم النسخة الموجودة بدل ما نكرّره.') }}
                </p>
                <input type="file" name="file" required class="w-full text-sm">
                <x-form.input name="folder" :label="setting('admin.courses.media.almjld', 'المجلّد')" />
                <x-form.input name="tags" :label="setting('admin.courses.media.wswm', 'وسوم')" :hint="setting('admin.courses.media.afsl_bynha_bfasla', 'افصل بينها بفاصلة.')" />
                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.media.arfa', 'ارفع') }}</button>
            </form>
        </x-modal>
    @endcan

    @include('admin.courses.partials.toast')

    @push('scripts')
        @php
            /*
             | نصوص السكربت من الإعدادات (2.13-أ): لا حرفَ عربيّ داخل `<script>`،
             | فالمحروق هناك لا يصل لوحةَ الإدارة ولا الترجمة.
             */
            $jsText = [
                'copied' => setting('admin.courses.media.atnskh_almsar', 'اتنسخ المسار ✓'),
            ];
        @endphp

        <script>
            const HC_MEDIA_TEXT = @json($jsText);
            /* نسخ المسار بضغطة — ردّ فوريّ لكلّ فعل (2.17-ب) */
            document.querySelectorAll('[data-copy]').forEach((input) => {
                input.addEventListener('click', () => {
                    input.select();
                    navigator.clipboard?.writeText(input.value).then(() => window.hcToast?.(HC_MEDIA_TEXT.copied));
                });
            });
        </script>
    @endpush
@endsection

@section('mobile_action')
    @can('media_library.create')
        <button type="button" data-modal-open="media-upload"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.media.rfa_mlf', 'رفع ملفّ') }}</button>
    @endcan
@endsection
