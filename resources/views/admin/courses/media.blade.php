@extends('layouts.admin')

@section('title', 'مكتبة الوسائط')

@section('content')
    {{-- مكتبة الوسائط المركزيّة (12.4-د · 24.1) --}}
    <x-page-header
        title="مكتبة الوسائط"
        subtitle="ارفع الملفّ مرّة واستخدمه في أيّ مكان — والمكرَّر بنكتشفه بالهاش."
        :breadcrumbs="[['label' => 'إدارة التدريب', 'url' => route('admin.courses.index')], ['label' => 'مكتبة الوسائط']]">
        <x-slot:action>
            @can('media_library.create')
                <button type="button" data-modal-open="media-upload"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">رفع</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    @include('admin.courses.partials.nav', ['current' => 'media'])

    <x-filters :action="route('admin.media.index')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="اسم الملفّ…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="block">
            <span class="block text-sm mb-1">النوع</span>
            <select name="kind" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach (['image' => 'صورة', 'pdf' => 'PDF', 'doc' => 'Word', 'audio' => 'صوت', 'video' => 'فيديو'] as $key => $label)
                    <option value="{{ $key }}" @selected($filters['kind'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="block text-sm mb-1">المجلّد</span>
            <select name="folder" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($folders as $folder)
                    <option value="{{ $folder }}" @selected($filters['folder'] === $folder)>{{ $folder }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">تصفية</button>

        <x-slot:advanced>
            <label class="block">
                <span class="block text-sm mb-1">الوسم</span>
                <select name="tag" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag }}" @selected($filters['tag'] === $tag)>{{ $tag }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex items-center gap-2 text-sm mt-6">
                <input type="checkbox" name="unused" value="1" @checked($filters['unused'])> غير مستخدَم فقط
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($items->isEmpty())
        <x-empty message="المكتبة فاضية — ارفع أوّل ملفّ." />
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
                        {{ $item->size ? round($item->size / 1024).' ك.ب' : '—' }}
                        · مستخدَم في {{ $usage[$item->id] ?? 0 }} مكان
                    </div>

                    <details class="mt-2">
                        <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">إجراءات</summary>
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
                                           placeholder="وسوم مفصولة بفاصلة" class="w-full rounded-lg px-2 py-1 text-xs"
                                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    <input type="text" name="folder" value="{{ $item->folder }}" placeholder="مجلّد"
                                           class="w-full rounded-lg px-2 py-1 text-xs"
                                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    <button class="text-xs underline">حفظ</button>
                                </form>
                            @endcan

                            @can('media_library.delete')
                                <form method="post" action="{{ route('admin.media.destroy', $item) }}"
                                      onsubmit="return confirm('{{ ($usage[$item->id] ?? 0) > 0 ? setting('media.delete.in_use_warning', 'الملفّ ده مستخدَم في أماكن تانية — متأكّد؟') : 'نشيل الملفّ؟' }}')">
                                    @csrf @method('delete')
                                    @if (($usage[$item->id] ?? 0) > 0)
                                        <input type="hidden" name="force" value="1">
                                    @endif
                                    <button class="text-xs underline" style="color: var(--color-state-danger)">حذف</button>
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
        <x-modal id="media-upload" title="رفع ملفّ">
            <form method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <p class="text-sm" style="color: var(--text-muted)">
                    لو الملفّ اترفع قبل كده هنستخدم النسخة الموجودة بدل ما نكرّره.
                </p>
                <input type="file" name="file" required class="w-full text-sm">
                <x-form.input name="folder" label="المجلّد" />
                <x-form.input name="tags" label="وسوم" hint="افصل بينها بفاصلة." />
                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">ارفع</button>
            </form>
        </x-modal>
    @endcan

    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            /* نسخ المسار بضغطة — ردّ فوريّ لكلّ فعل (2.17-ب) */
            document.querySelectorAll('[data-copy]').forEach((input) => {
                input.addEventListener('click', () => {
                    input.select();
                    navigator.clipboard?.writeText(input.value).then(() => window.hcToast?.('اتنسخ المسار ✓'));
                });
            });
        </script>
    @endpush
@endsection

@section('mobile_action')
    @can('media_library.create')
        <button type="button" data-modal-open="media-upload"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">رفع ملفّ</button>
    @endcan
@endsection
