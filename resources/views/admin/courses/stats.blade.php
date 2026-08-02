@extends('layouts.app')

@section('title', 'إحصائيّات '.$course->name_ar)

@section('content')
    {{-- صفحة إحصائيّات لكلّ تدريب (12.4-هـ) --}}
    <x-page-header
        :title="'إحصائيّات: '.$course->name_ar"
        subtitle="نظرة سريعة على أداء التدريب."
        :breadcrumbs="[
            ['label' => 'التدريبات', 'url' => route('admin.courses.index')],
            ['label' => $course->name_ar, 'url' => route('admin.courses.edit', $course)],
            ['label' => 'الإحصائيّات'],
        ]" />

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <x-kpi label="المسجّلون" :value="$stats['enrolled']" icon="👥" />
        <x-kpi label="أكملوا" :value="$stats['completed']" icon="🎓" :hint="$stats['completion_rate'].'% نسبة الإكمال'" />
        <x-kpi label="متوسّط التقدّم" :value="$stats['avg_progress']" icon="📈" />
        <x-kpi label="الإيراد (كوينز)" :value="$stats['revenue_coins']" icon="💰" />
    </div>

    <div class="grid md:grid-cols-2 gap-3 mt-4">
        <div class="card p-4">
            <h2 class="font-bold mb-2">المحتوى</h2>
            <p class="text-sm">{{ $stats['sections'] }} سيكشن · {{ $stats['lessons'] }} درس</p>
        </div>

        <div class="card p-4">
            <h2 class="font-bold mb-2">الأسئلة العامّة</h2>
            <x-state-badge :state="$indicator['state']"
                           :label="$indicator['available'].' من '.$indicator['required']" />
            @if ($indicator['short'] > 0)
                <p class="text-sm mt-2">ناقصك {{ $indicator['short'] }} سؤال عامّ عشان الامتحان النهائيّ يتبني كامل.</p>
            @endif
        </div>
    </div>
@endsection
