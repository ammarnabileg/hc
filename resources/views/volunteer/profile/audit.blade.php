@extends('layouts.volunteer')

@section('title', setting('volunteer.profile_audit.title', 'سجلّ المشرف — ').$owner->shortName())

@section('content')
    {{-- «سجلّ المشرف» (13.4-م): كلّ حركة على هذا الشخص — بمَن نفّذها ومتى، اطّلاعٌ فقط. --}}
    <x-page-header
        :title="setting('volunteer.profile_audit.tooltip', 'سجلّ المشرف')"
        :subtitle="$owner->name.' · '.$owner->code"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.profile_audit.label', 'بروفايل المتطوّع')], ['label' => setting('volunteer.profile_audit.tooltip', 'سجلّ المشرف')]]" />

    @if ($rows->isEmpty())
        <x-empty :message="setting('volunteer.profile_audit.empty', 'مفيش حركات مسجّلة على الحساب ده')" :action="setting('volunteer.profile_audit.action', 'ارجع للبروفايل')" :href="'/u/'.$owner->code" />
    @else
        <section class="card p-4">
            <ul class="space-y-3">
                @foreach ($rows as $row)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="min-w-0 truncate font-semibold">{{ $row->action }}</span>
                        <span class="text-xs" style="color: var(--text-muted)"
                              title="{{ $row->created_at->format('Y-m-d H:i') }}">
                            {{ $row->user?->shortName() ?? setting('volunteer.profile_audit.text', 'النظام') }} · {{ $row->created_at->diffForHumans() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
