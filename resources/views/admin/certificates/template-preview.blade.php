@extends('layouts.app')

@section('title', 'معاينة القالب بالمقاس الحقيقيّ')

@section('content')
    {{-- ⭐ معاينة بالمقاس الحقيقيّ (12.5-ب): نفس وحدات الراسم على الخادم --}}
    <x-page-header
        :title="'معاينة: '.$template->name"
        :subtitle="$template->width_px.'×'.$template->height_px.' بكسل — بالمقاس الحقيقيّ'"
        :breadcrumbs="[
            ['label' => 'الشهادات', 'url' => route('admin.certificates.index')],
            ['label' => $type->name_ar, 'url' => route('admin.certificates.designer', $type)],
            ['label' => 'معاينة'],
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
