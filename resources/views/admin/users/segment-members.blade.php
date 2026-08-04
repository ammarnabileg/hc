@extends('layouts.admin')

@section('title', setting('admin.users.segment_members.aada_alshryha', 'أعضاء الشريحة'))

@section('content')
    {{--
      معاينة أعضاء الشريحة (12.13). والعدد هنا يخرج من **نفس** مصدر الإرسال:
      الثابتة من قائمتها المجمَّدة، والديناميكيّة من إعادة حساب شرطها — فما تراه
      هو ما سيصل الناس، لا رقمٌ مخزَّن قديم.
    --}}
    <x-page-header :title="setting('admin.users.segment_members.aada', 'أعضاء: ').$segment->name"
                   :subtitle="$service->summary((array) $segment->rule)"
                   :breadcrumbs="[
                       ['label' => setting('admin.users.segment_members.almstkhdmwn', 'المستخدمون'), 'url' => route('admin.users.index')],
                       ['label' => setting('admin.users.segment_members.shrayh_aljmhwr', 'شرائح الجمهور'), 'url' => route('admin.users.segments')],
                       ['label' => $segment->name],
                   ]" />

    <div class="card p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm">{{ setting('admin.users.segment_members.aladd', 'العدد:') }} <strong>{{ number_format($count) }}</strong></div>
            <x-state-badge :state="$segment->archived_at ? 'idle' : 'ok'"
                           :label="$types[$segment->segment_type] ?? $segment->segment_type" />
        </div>

        @if ($segment->frozen_at)
            <p class="text-xs mt-1" style="color: var(--text-muted)">
                {!! strtr(setting('admin.users.segment_members.atjmdt_fy_v1_aladd_ma_bytghyrsh_btghyr', 'اتجمّدت في :v1 — العدد ما بيتغيّرش بتغيّر البيانات.'), [':v1' => e($segment->frozen_at->format('Y-m-d H:i'))]) !!}
            </p>
        @endif

        @if ($members->isEmpty())
            <x-empty :message="setting('admin.segments.members_empty', 'مافيش أعضاء مطابقين دلوقتي')" />
        @else
            <x-table :label="setting('admin.users.segment_members.aada_alshryha', 'أعضاء الشريحة')" class="mt-3">
                <thead>
                    <tr class="text-right text-xs" style="color: var(--text-muted)">
                        <th class="p-2">{{ setting('admin.users.segment_members.alasm', 'الاسم') }}</th>
                        <th class="p-2">{{ setting('admin.users.segment_members.alkwd', 'الكود') }}</th>
                        <th class="p-2">{{ setting('admin.users.segment_members.alhala', 'الحالة') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($members as $member)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-2">{{ $member->shortName() }}</td>
                            <td class="p-2 text-xs" style="color: var(--text-muted)">#{{ $member->code }}</td>
                            <td class="p-2 text-xs">{{ \App\Services\Admin\UserDirectory::STATUSES[$member->status] ?? $member->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </div>
@endsection
