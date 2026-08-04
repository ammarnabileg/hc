@extends('layouts.admin')

@section('title', setting('admin.courses.enrollees.almsjlwn_fy', 'المسجّلون في ').$course->name_ar)

@section('content')
    {{-- الضغط على «عدد المسجّلين» ⟵ مَن هم (12.4-ب) --}}
    <x-page-header
        :title="setting('admin.courses.enrollees.almsjlwn', 'المسجّلون: ').$course->name_ar"
        :subtitle="$enrollments->total().setting('admin.courses.enrollees.msjl', ' مسجّل')"
        :breadcrumbs="[
            ['label' => setting('admin.courses.enrollees.altdrybat', 'التدريبات'), 'url' => route('admin.courses.index')],
            ['label' => $course->name_ar, 'url' => route('admin.courses.edit', $course)],
            ['label' => setting('admin.courses.enrollees.almsjlwn_2', 'المسجّلون')],
        ]" />

    @if ($enrollments->isEmpty())
        <x-empty :message="setting('admin.courses.enrollees.mhdsh_sjl_lsh_awl_msjl_jay', 'محدّش سجّل لسّه — أوّل مسجّل جاي.')" />
    @else
        <div class="space-y-3">
            @foreach ($enrollments as $enrollment)
                <div class="card p-3 flex items-center gap-3">
                    <x-avatar :user="$enrollment->user" size="10" />
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold truncate">{{ $enrollment->user?->name }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">#{{ $enrollment->user?->code }}</div>
                    </div>
                    <div class="text-sm">{{ $enrollment->progress_percent }}%</div>
                    <x-state-badge :state="$enrollment->status === 'completed' ? 'ok' : 'warn'"
                                   :label="$enrollment->status === 'completed' ? setting('admin.courses.enrollees.mktml', 'مكتمل') : setting('admin.courses.enrollees.shghal', 'شغّال')" />
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $enrollments->links() }}</div>
    @endif
@endsection
