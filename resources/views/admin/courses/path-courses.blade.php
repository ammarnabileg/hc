@extends('layouts.admin')

@section('title', setting('admin.courses.path_courses.tdrybat_almsar', 'تدريبات المسار'))

@section('content')
    {{-- إدارة تدريبات المسار: بحث/سحب-ترتيب/إضافة/حذف (12.4-أ) --}}
    <x-page-header
        :title="setting('admin.courses.path_courses.tdrybat', 'تدريبات: ').$path->name_ar"
        :subtitle="setting('admin.courses.path_courses.rtb_altdrybat_jwh_almsar_walizala_hna_btfk', 'رتّب التدريبات جوّه المسار — والإزالة هنا بتفكّ الارتباط بس، التدريب بيفضل موجود.')"
        :breadcrumbs="[
            ['label' => setting('admin.courses.path_courses.almsarat', 'المسارات'), 'url' => route('admin.paths.index')],
            ['label' => $path->name_ar],
        ]">
        <x-slot:action>
            @can('paths.edit')
                <button type="button" data-modal-open="attach-courses"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.path_courses.adf_tdrybat', '+ أضِف تدريبات') }}</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    <form method="get" class="mb-4">
        <input type="search" name="q" value="{{ $search }}" placeholder="{{ setting('admin.courses.path_courses.abhth_fy_tdrybat_almsar', 'ابحث في تدريبات المسار…') }}"
               class="w-full md:w-80 rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    </form>

    @if ($courses->isEmpty())
        {{-- تمييز «المسار فاضي فعلًا» عن «البحث الحاليّ ما طابقش حاجة» — فلا تُعرَض
             رسالة «ضيف أوّل تدريب» المضلّلة لمّا يكون السبب بحثًا نشطًا لا فراغًا فعليًّا. --}}
        <x-empty :message="setting('admin.courses.path_courses.almsar_lsh_fady_dyf_awl_tdryb', 'المسار لسّه فاضي — ضيف أوّل تدريب.')"
                 :filtered="$search !== ''" />
    @else
        <div class="space-y-3" data-sortable="{{ route('admin.paths.courses.reorder', $path) }}">
            @foreach ($courses as $course)
                <div class="card p-4 flex items-center gap-3" data-sort-id="{{ $course->id }}">
                    <span class="cursor-grab select-none hidden md:inline" aria-hidden="true">⠿</span>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold truncate">{{ $course->name_ar }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">
                            {{ $course->is_free ? setting('admin.courses.path_courses.mjany', 'مجّانيّ') : (int) $course->price_coins.setting('admin.courses.path_courses.kwynz', ' كوينز') }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" data-sort-up class="btn md:hidden rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken)" aria-label="{{ setting('admin.courses.path_courses.fwq', 'فوق') }}">↑</button>
                        <button type="button" data-sort-down class="btn md:hidden rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken)" aria-label="{{ setting('admin.courses.path_courses.tht', 'تحت') }}">↓</button>
                        @can('paths.edit')
                            <form method="post" action="{{ route('admin.paths.courses.detach', [$path, $course]) }}"
                                  onsubmit="return confirm('{{ setting('paths.detach.confirm_text', 'هنشيله من المسار بس — التدريب هيفضل موجود. نكمّل؟') }}')">
                                @csrf @method('delete')
                                <button class="btn rounded-xl px-3 py-2 text-sm"
                                        style="background: var(--surface-sunken); color: var(--color-state-danger)">{{ setting('admin.courses.path_courses.izala', 'إزالة') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @can('paths.edit')
        <x-modal id="attach-courses" :title="setting('admin.courses.path_courses.adf_tdrybat_llmsar', 'أضِف تدريبات للمسار')">
            <p class="text-sm mb-3" style="color: var(--text-muted)">
                {{ setting('admin.courses.path_courses.altdryb_yqdr_ykwn_fy_aktr_mn_msar_idafth_hna', 'التدريب يقدر يكون في أكتر من مسار — إضافته هنا ما بتشيلوش من مساراته التانية.') }}
            </p>

            @if ($attachable->isEmpty())
                <p class="text-sm">{{ setting('admin.courses.path_courses.mfysh_tdrybat_jdyda_tdyfha_dlwqty', 'مفيش تدريبات جديدة تضيفها دلوقتي.') }}</p>
            @else
                <form method="post" action="{{ route('admin.paths.courses.attach', $path) }}" class="space-y-2">
                    @csrf
                    @foreach ($attachable as $candidate)
                        <label class="flex items-center gap-2 text-sm card p-2">
                            <input type="checkbox" name="course_ids[]" value="{{ $candidate->id }}">
                            <span class="flex-1">{{ $candidate->name_ar }}</span>
                        </label>
                    @endforeach
                    <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.path_courses.adf_almkhtar', 'أضِف المختار') }}</button>
                </form>
            @endif
        </x-modal>
    @endcan

    @include('admin.courses.partials.sortable')
    @include('admin.courses.partials.toast')
@endsection
