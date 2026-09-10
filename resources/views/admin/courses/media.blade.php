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
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.courses.media.mn_tarykh', 'من تاريخ') }}</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                       class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.courses.media.ila_tarykh', 'إلى تاريخ') }}</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                       class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.courses.media.asghr_hjm_k_b', 'أصغر حجم (ك.ب)') }}</span>
                <input type="number" min="0" name="size_min" value="{{ $filters['size_min'] }}"
                       class="w-24 rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.courses.media.akbr_hjm_k_b', 'أكبر حجم (ك.ب)') }}</span>
                <input type="number" min="0" name="size_max" value="{{ $filters['size_max'] }}"
                       class="w-24 rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
        </x-slot:advanced>
    </x-filters>

    {{--
        ⭐ عرض شبكة/قائمة (تبديل) (12.4-د) — كان `$view` يُمرَّر من المتحكّم بلا
        زرّ تبديلٍ ولا فرعٍ يستهلكه، فالشبكة كانت العرض الوحيد مهما كبرت
        المكتبة. النمط مطابقٌ لـ admin/events/index.blade.php (12.11).
    --}}
    @php
        $mediaViewQuery = array_filter([
            'q' => $filters['q'],
            'kind' => $filters['kind'],
            'folder' => $filters['folder'],
            'tag' => $filters['tag'],
            'unused' => $filters['unused'] ? 1 : null,
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'size_min' => $filters['size_min'],
            'size_max' => $filters['size_max'],
        ]);
    @endphp
    <div class="flex items-center gap-2 mb-4">
        <a href="{{ route('admin.media.index', $mediaViewQuery + ['view' => 'grid']) }}"
           class="rounded-xl px-4 py-2 text-sm font-semibold"
           style="background: {{ $view === 'grid' ? 'var(--color-brand-500)' : 'var(--surface-raised)' }}; color: {{ $view === 'grid' ? '#04201c' : 'var(--text)' }}">{{ setting('admin.courses.media.shbka', 'شبكة') }}</a>
        <a href="{{ route('admin.media.index', $mediaViewQuery + ['view' => 'list']) }}"
           class="rounded-xl px-4 py-2 text-sm font-semibold"
           style="background: {{ $view === 'list' ? 'var(--color-brand-500)' : 'var(--surface-raised)' }}; color: {{ $view === 'list' ? '#04201c' : 'var(--text)' }}">{{ setting('admin.courses.media.qaema', 'قائمة') }}</a>
    </div>

    @if ($items->isEmpty())
        <x-empty :message="setting('admin.courses.media.almktba_fadya_arfa_awl_mlf', 'المكتبة فاضية — ارفع أوّل ملفّ.')" />
    @elseif ($view === 'list')
        <x-table :label="setting('admin.courses.media.mktba_alwsayt', 'مكتبة الوسائط')">
            <thead>
                <tr class="text-right text-xs" style="color: var(--text-muted)">
                    <th class="p-2">{{ setting('admin.courses.media.msghra', 'مصغّرة') }}</th>
                    <th class="p-2">{{ setting('admin.courses.media.alasm', 'الاسم') }}</th>
                    <th class="p-2">{{ setting('admin.courses.media.alnwa', 'النوع') }}</th>
                    <th class="p-2">{{ setting('admin.courses.media.almjld', 'المجلّد') }}</th>
                    <th class="p-2">{{ setting('admin.courses.media.alhjm', 'الحجم') }}</th>
                    <th class="p-2">{{ setting('admin.courses.media.add_alastkhdamat', 'عدد الاستخدامات') }}</th>
                    <th class="p-2">{{ setting('admin.courses.media.ijraat', 'إجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr style="border-top: 1px solid var(--border)">
                        <td class="p-2">
                            @if (str_starts_with((string) $item->mime, 'image/'))
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk($item->disk ?: 'public')->url($item->path) }}"
                                     alt="{{ $item->name }}" loading="lazy"
                                     class="w-10 h-10 object-cover rounded-lg">
                            @else
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                                     style="background: var(--surface-sunken)" aria-hidden="true"><x-icon name="document" size="16" /></div>
                            @endif
                        </td>
                        <td class="p-2 text-sm font-semibold truncate max-w-[16rem]" title="{{ $item->name }}">{{ $item->name }}</td>
                        <td class="p-2 text-xs" style="color: var(--text-muted)">{{ $item->mime }}</td>
                        <td class="p-2 text-xs" style="color: var(--text-muted)">{{ $item->folder ?: '—' }}</td>
                        <td class="p-2 text-xs" style="color: var(--text-muted)">{{ $item->size ? round($item->size / 1024).setting('admin.courses.media.k_b', ' ك.ب') : '—' }}</td>
                        <td class="p-2 text-xs" style="color: var(--text-muted)">{{ $usage[$item->id] ?? 0 }}</td>
                        <td class="p-2">
                            @include('admin.courses.partials.media-item-actions', ['item' => $item, 'usage' => $usage])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>

        <div class="mt-4">{{ $items->links() }}</div>
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

                    @include('admin.courses.partials.media-item-actions', ['item' => $item, 'usage' => $usage])
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
