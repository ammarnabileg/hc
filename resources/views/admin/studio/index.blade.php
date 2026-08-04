@extends('layouts.admin')

@section('title', setting('admin.studio.index.astwdyw_alswr', 'استوديو الصور'))

@section('content')
    <x-page-header :title="setting('admin.studio.index.astwdyw_alswr', 'استوديو الصور')"
                   :subtitle="setting('admin.studio.index.smm_mra_walmnsa_twld_alaf_alnskh_basma', 'صمّم مرّة — والمنصّة تولّد آلاف النسخ بأسماء أصحابها.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.studio.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.studio.index.astwdyw_alswr', 'استوديو الصور')],
                   ]">
        <x-slot:action>
            @can('image_templates.create')
                <button type="button" data-modal-open="new-template"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.studio.index.qalb', '+ قالب') }}</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    <x-filters :action="route('admin.studio.index')">
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.studio.index.bhth', 'بحث') }}</span>
            <input type="search" name="q" value="{{ request('q') }}" class="rounded-xl px-3 py-2 text-sm w-48"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.studio.index.aljmhwr', 'الجمهور') }}</span>
            <select name="audience" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.studio.index.alkl', 'الكلّ') }}</option>
                @foreach ($audiences as $key => $label)
                    <option value="{{ $key }}" @selected(request('audience') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        {{-- «مجلّدات ووسوم · بحث» (12.14-أ) — العمودان بلا فلترٍ كانا زينةً في القاعدة --}}
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('images.template.folders_label') }}</span>
            <select name="folder" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('images.filters.any') }}</option>
                @foreach ($folderList as $folder)
                    <option value="{{ $folder }}" @selected(request('folder') === $folder)>{{ $folder }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('images.template.tags_label') }}</span>
            <select name="tag" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('images.filters.any') }}</option>
                @foreach ($tagList as $tag)
                    <option value="{{ $tag }}" @selected(request('tag') === $tag)>{{ $tag }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-2 text-xs mt-4">
            <input type="checkbox" name="archived" value="1" @checked(request()->boolean('archived'))>
            <span>{{ setting('admin.studio.index.aard_almwrshf', 'اعرض المؤرشف') }}</span>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.studio.index.fltra', 'فلترة') }}</button>
    </x-filters>

    @if ($templates->isEmpty())
        <x-empty :message="setting('admin.studio.index.mafysh_qwalb_lsh_abda_bawl_qalb', 'مافيش قوالب لسه — ابدأ بأوّل قالب.')" />
    @else
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($templates as $template)
                <div class="card p-4 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold text-sm">{{ $template->name }}</div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                {{ $template->width_px }}×{{ $template->height_px }} · {{ $audiences[$template->audience] ?? $template->audience }}
                                · {{ $purposes[$template->purpose] ?? $template->purpose }}
                            </div>
                        </div>
                        <x-state-badge :state="$template->is_archived ? 'idle' : ($template->is_active ? 'ok' : 'warn')"
                                       :label="$template->is_archived ? setting('admin.studio.index.mwrshf', 'مؤرشف') : ($template->is_active ? setting('admin.studio.index.mfal', 'مفعَّل') : setting('admin.studio.index.mwqwf', 'موقوف'))" />
                    </div>

                    <img src="{{ route('admin.studio.preview', $template) }}" alt="{{ strtr(setting('admin.studio.index.maayna_v1', 'معاينة :v1'), [':v1' => e($template->name)]) }}"
                         loading="lazy" class="w-full rounded-xl" style="max-width:100%; background: var(--surface-sunken)">

                    @if (($template->folders ?? []) || ($template->tags ?? []))
                        <div class="flex flex-wrap gap-1 text-xs">
                            @foreach ((array) $template->folders as $folder)
                                <a class="rounded-full px-2 py-0.5" style="background: var(--surface-raised)"
                                   href="{{ route('admin.studio.index', ['folder' => $folder]) }}">{{ $folder }}</a>
                            @endforeach
                            @foreach ((array) $template->tags as $tag)
                                <a class="rounded-full px-2 py-0.5" style="background: var(--surface-sunken)"
                                   href="{{ route('admin.studio.index', ['tag' => $tag]) }}">#{{ $tag }}</a>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2 text-xs">
                        @can('image_templates.edit')
                            <a class="underline" href="{{ route('admin.studio.edit', $template) }}">{{ setting('admin.studio.index.tadyl', 'تعديل') }}</a>
                        @endcan
                        @can('image_templates.create')
                            <form method="post" action="{{ route('admin.studio.duplicate', $template) }}">
                                @csrf<button class="underline">{{ setting('admin.studio.index.nskha', 'نسخة') }}</button>
                            </form>
                        @endcan
                        @can('image_templates.archive')
                            @unless ($template->is_archived)
                                <form method="post" action="{{ route('admin.studio.archive', $template) }}">
                                    @csrf<button class="underline">{{ setting('admin.studio.index.arshfa', 'أرشفة') }}</button>
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
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.studio.index.adwat_alasm', 'أدوات الاسم') }}</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted)">
            {{ setting('admin.studio.index.aladaa_bttaaml_jza_mn_alklma_ally_badha_f', 'الأداة بتتعامل جزءًا من الكلمة اللي بعدها، فـ«عبد الرحمن محمد علي» بيبقى «عبد الرحمن محمد».') }}
        </p>

        <div class="flex flex-wrap gap-2 mb-3">
            @foreach ($particles as $particle)
                <span class="rounded-full px-3 py-1 text-xs flex items-center gap-2" style="background: var(--surface-raised)">
                    {{ $particle->particle }}
                    @can('image_templates.edit')
                        <form method="post" action="{{ route('admin.studio.particles.delete', $particle) }}">
                            @csrf @method('DELETE')
                            <button aria-label="{{ strtr(setting('admin.studio.index.hdhf_v1', 'حذف :v1'), [':v1' => e($particle->particle)]) }}">✕</button>
                        </form>
                    @endcan
                </span>
            @endforeach
        </div>

        @can('image_templates.edit')
            <form method="post" action="{{ route('admin.studio.particles.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="text" name="particle" required placeholder="{{ setting('admin.studio.index.adaa_jdyda', 'أداة جديدة') }}"
                       class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <select name="locale" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="ar">{{ setting('admin.studio.index.arbya', 'عربيّة') }}</option>
                    <option value="en">{{ setting('admin.studio.index.injlyzya', 'إنجليزيّة') }}</option>
                </select>
                <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.studio.index.idafa', 'إضافة') }}</button>
            </form>
        @endcan
    </div>

    @push('modals')
        <x-modal id="new-template" :title="setting('admin.studio.index.qalb_jdyd', 'قالب جديد')">
            <form method="post" action="{{ route('admin.studio.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="name" :label="setting('admin.studio.index.asm_alqalb', 'اسم القالب')" required />

                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.studio.index.mqas_jahz', 'مقاس جاهز') }}</span>
                    <select name="preset" id="preset-select" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">{{ setting('admin.studio.index.mkhss', 'مخصّص') }}</option>
                        @foreach ($presets as $key => $preset)
                            <option value="{{ $key }}" data-size="{{ $preset['width'] }}x{{ $preset['height'] }}">
                                {{ $preset['label'] }} — {{ $preset['width'] }}×{{ $preset['height'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('images.template.purpose_label') }}</span>
                    <select name="purpose" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($purposes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <x-form.input name="width_px" :label="setting('admin.studio.index.alard_bksl', 'العرض (بكسل)')" type="number" value="1080" required />
                    <x-form.input name="height_px" :label="setting('admin.studio.index.altwl_bksl', 'الطول (بكسل)')" type="number" value="1080" required />
                </div>

                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.studio.index.aljmhwr', 'الجمهور') }}</span>
                    <select name="audience" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($audiences as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.studio.index.anshy_alqalb', 'أنشئ القالب') }}</button>
            </form>
        </x-modal>
    @endpush

    @push('scripts')
    <script>
    (function () {
        var preset = document.getElementById('preset-select');
        if (!preset) { return; }

        preset.addEventListener('change', function () {
            var size = preset.options[preset.selectedIndex].getAttribute('data-size');
            if (!size) { return; }
            var parts = size.split('x');
            document.getElementById('width_px').value = parts[0];
            document.getElementById('height_px').value = parts[1];
        });
    })();
    </script>
    @endpush
@endsection
