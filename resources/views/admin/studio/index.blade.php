@extends('layouts.app')

@section('title', 'استوديو الصور')

@section('content')
    <x-page-header title="استوديو الصور"
                   subtitle="صمّم مرّة — والمنصّة تولّد آلاف النسخ بأسماء أصحابها."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'استوديو الصور'],
                   ]">
        <x-slot:action>
            @can('image_templates.create')
                <button type="button" data-modal-open="new-template"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">+ قالب</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    <x-filters :action="route('admin.studio.index')">
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ request('q') }}" class="rounded-xl px-3 py-2 text-sm w-48"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">الجمهور</span>
            <select name="audience" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($audiences as $key => $label)
                    <option value="{{ $key }}" @selected(request('audience') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-2 text-xs mt-4">
            <input type="checkbox" name="archived" value="1" @checked(request()->boolean('archived'))>
            <span>اعرض المؤرشف</span>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">فلترة</button>
    </x-filters>

    @if ($templates->isEmpty())
        <x-empty message="مافيش قوالب لسه — ابدأ بأوّل قالب." />
    @else
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($templates as $template)
                <div class="card p-4 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold text-sm">{{ $template->name }}</div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                {{ $template->width_px }}×{{ $template->height_px }} · {{ $audiences[$template->audience] ?? $template->audience }}
                            </div>
                        </div>
                        <x-state-badge :state="$template->is_archived ? 'idle' : ($template->is_active ? 'ok' : 'warn')"
                                       :label="$template->is_archived ? 'مؤرشف' : ($template->is_active ? 'مفعَّل' : 'موقوف')" />
                    </div>

                    <img src="{{ route('admin.studio.preview', $template) }}" alt="معاينة {{ $template->name }}"
                         loading="lazy" class="w-full rounded-xl" style="max-width:100%; background: var(--surface-sunken)">

                    <div class="flex flex-wrap gap-2 text-xs">
                        @can('image_templates.edit')
                            <a class="underline" href="{{ route('admin.studio.edit', $template) }}">تعديل</a>
                        @endcan
                        @can('image_templates.create')
                            <form method="post" action="{{ route('admin.studio.duplicate', $template) }}">
                                @csrf<button class="underline">نسخة</button>
                            </form>
                        @endcan
                        @can('image_templates.archive')
                            @unless ($template->is_archived)
                                <form method="post" action="{{ route('admin.studio.archive', $template) }}">
                                    @csrf<button class="underline">أرشفة</button>
                                </form>
                            @endunless
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5">{{ $templates->links() }}</div>
    @endif

    {{-- ⭐ إدارة قائمة أدوات الاسم من هنا — لأنّها تختلف بالثقافات (12.14-ج) --}}
    <div class="card p-4 mt-6">
        <h2 class="font-bold text-sm mb-2">أدوات الاسم</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted)">
            الأداة بتتعامل جزءًا من الكلمة اللي بعدها، فـ«عبد الرحمن محمد علي» بيبقى «عبد الرحمن محمد».
        </p>

        <div class="flex flex-wrap gap-2 mb-3">
            @foreach ($particles as $particle)
                <span class="rounded-full px-3 py-1 text-xs flex items-center gap-2" style="background: var(--surface-raised)">
                    {{ $particle->particle }}
                    @can('image_templates.edit')
                        <form method="post" action="{{ route('admin.studio.particles.delete', $particle) }}">
                            @csrf @method('DELETE')
                            <button aria-label="حذف {{ $particle->particle }}">✕</button>
                        </form>
                    @endcan
                </span>
            @endforeach
        </div>

        @can('image_templates.edit')
            <form method="post" action="{{ route('admin.studio.particles.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="text" name="particle" required placeholder="أداة جديدة"
                       class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <select name="locale" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="ar">عربيّة</option>
                    <option value="en">إنجليزيّة</option>
                </select>
                <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">إضافة</button>
            </form>
        @endcan
    </div>

    @push('modals')
        <x-modal id="new-template" title="قالب جديد">
            <form method="post" action="{{ route('admin.studio.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="name" label="اسم القالب" required />

                <label class="block text-sm">
                    <span class="block mb-1">مقاس جاهز</span>
                    <select id="preset-select" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">مخصّص</option>
                        @foreach ($presets as $key => $preset)
                            <option value="{{ $preset['width'] }}x{{ $preset['height'] }}">
                                {{ $preset['label'] }} — {{ $preset['width'] }}×{{ $preset['height'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <x-form.input name="width_px" label="العرض (بكسل)" type="number" value="1080" required />
                    <x-form.input name="height_px" label="الطول (بكسل)" type="number" value="1080" required />
                </div>

                <label class="block text-sm">
                    <span class="block mb-1">الجمهور</span>
                    <select name="audience" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($audiences as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">أنشئ القالب</button>
            </form>
        </x-modal>
    @endpush

    @push('scripts')
    <script>
    (function () {
        var preset = document.getElementById('preset-select');
        if (!preset) { return; }

        preset.addEventListener('change', function () {
            if (!preset.value) { return; }
            var parts = preset.value.split('x');
            document.getElementById('width_px').value = parts[0];
            document.getElementById('height_px').value = parts[1];
        });
    })();
    </script>
    @endpush
@endsection
