@extends('layouts.app')

@section('title', 'اختَر من المكتبة')

@section('content')
    {{-- «اختَر من المكتبة / ارفع جديد» المستدعى من أيّ حقل رفع (12.4-د) --}}
    <x-page-header title="اختَر من المكتبة" subtitle="اضغط الملفّ عشان تنسخ مساره وتلزقه في الحقل." />

    <form method="get" class="mb-4">
        <input type="hidden" name="target" value="{{ $target }}">
        <input type="search" name="q" placeholder="ابحث بالاسم…"
               class="w-full md:w-80 rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    </form>

    @if ($items->isEmpty())
        <x-empty message="المكتبة فاضية — ارفع أوّل ملفّ." action="افتح المكتبة" :href="route('admin.media.index')" />
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach ($items as $item)
                <button type="button" class="card p-3 text-start" data-pick="{{ $item->path }}">
                    @if (str_starts_with((string) $item->mime, 'image/'))
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($item->disk ?: 'public')->url($item->path) }}"
                             alt="{{ $item->name }}" loading="lazy" class="w-full h-24 object-cover rounded-lg mb-2">
                    @else
                        <div class="w-full h-24 rounded-lg mb-2 flex items-center justify-center text-2xl"
                             style="background: var(--surface-sunken)" aria-hidden="true">📄</div>
                    @endif
                    <div class="text-sm truncate">{{ $item->name }}</div>
                </button>
            @endforeach
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    @endif

    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            document.querySelectorAll('[data-pick]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    navigator.clipboard?.writeText(btn.dataset.pick);
                    window.hcToast?.('اتنسخ المسار — الزقه في حقل الملفّ ✓');
                });
            });
        </script>
    @endpush
@endsection
