@extends('layouts.admin')

@section('title', setting('admin.certificates.template_preview.maayna_alqalb_balmqas_alhqyqy', 'معاينة القالب بالمقاس الحقيقيّ'))

@section('content')
    {{-- ⭐ معاينة بالمقاس الحقيقيّ (12.5-ب): نفس وحدات الراسم على الخادم --}}
    <x-page-header
        :title="setting('admin.certificates.template_preview.maayna', 'معاينة: ').$template->name"
        :subtitle="$template->width_px.'×'.$template->height_px.setting('admin.certificates.template_preview.bksl_balmqas_alhqyqy', ' بكسل — بالمقاس الحقيقيّ')"
        :breadcrumbs="[
            ['label' => setting('admin.certificates.template_preview.alshhadat', 'الشهادات'), 'url' => route('admin.certificates.index')],
            ['label' => $type->name_ar, 'url' => route('admin.certificates.designer', $type)],
            ['label' => setting('admin.certificates.template_preview.maayna_2', 'معاينة')],
        ]" />

    <div class="min-w-0 overflow-x-auto">
        @include('admin.certificates.partials.canvas', [
            'layers' => $layers,
            'template' => $template,
            'sample' => $sample,
            'canvasWidth' => (int) $template->width_px,
        ])
    </div>
@endsection
