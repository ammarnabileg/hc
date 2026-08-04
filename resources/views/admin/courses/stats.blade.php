@extends('layouts.admin')

@section('title', setting('admin.courses.stats.ihsayyat_2', 'إحصائيّات ').$course->name_ar)

@section('content')
    {{-- صفحة إحصائيّات لكلّ تدريب (12.4-هـ) --}}
    <x-page-header
        :title="setting('admin.courses.stats.ihsayyat', 'إحصائيّات: ').$course->name_ar"
        :subtitle="setting('admin.courses.stats.nzra_sryaa_ala_ada_altdryb', 'نظرة سريعة على أداء التدريب.')"
        :breadcrumbs="[
            ['label' => setting('admin.courses.stats.altdrybat', 'التدريبات'), 'url' => route('admin.courses.index')],
            ['label' => $course->name_ar, 'url' => route('admin.courses.edit', $course)],
            ['label' => setting('admin.courses.stats.alihsayyat', 'الإحصائيّات')],
        ]" />

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <x-kpi :label="setting('admin.courses.stats.almsjlwn', 'المسجّلون')" :value="$stats['enrolled']" icon="people" />
        <x-kpi :label="setting('admin.courses.stats.akmlwa', 'أكملوا')" :value="$stats['completed']" icon="training" :hint="$stats['completion_rate'].setting('admin.courses.stats.nsba_alikmal', '% نسبة الإكمال')" />
        <x-kpi :label="setting('admin.courses.stats.mtwst_altqdm', 'متوسّط التقدّم')" :value="$stats['avg_progress']" icon="chart" />
        <x-kpi :label="setting('admin.courses.stats.aliyrad_kwynz', 'الإيراد (كوينز)')" :value="$stats['revenue_coins']" icon="money" />
    </div>

    <div class="grid md:grid-cols-2 gap-3 mt-4">
        <div class="card p-4">
            <h2 class="font-bold mb-2">{{ setting('admin.courses.stats.almhtwa', 'المحتوى') }}</h2>
            <p class="text-sm">{{ $stats['sections'] }} {!! strtr(setting('admin.courses.stats.sykshn_v1_drs', 'سيكشن · :v1 درس'), [':v1' => e($stats['lessons'])]) !!}</p>
        </div>

        <div class="card p-4">
            <h2 class="font-bold mb-2">{{ setting('admin.courses.stats.alasyla_alaama', 'الأسئلة العامّة') }}</h2>
            <x-state-badge :state="$indicator['state']"
                           :label="$indicator['available'].setting('admin.courses.stats.mn', ' من ').$indicator['required']" />
            @if ($indicator['short'] > 0)
                <p class="text-sm mt-2">{!! strtr(setting('admin.courses.stats.naqsk_v1_swal_aam_ashan_alamthan_alnhayy', 'ناقصك :v1 سؤال عامّ عشان الامتحان النهائيّ يتبني كامل.'), [':v1' => e($indicator['short'])]) !!}</p>
            @endif
        </div>
    </div>
@endsection
