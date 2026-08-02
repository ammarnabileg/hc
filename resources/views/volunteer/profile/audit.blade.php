@extends('layouts.app')

@section('title', 'سجلّ المشرف — '.$owner->shortName())
@section('noindex', '1')

@section('content')
    {{-- «سجلّ المشرف» (13.4-م): كلّ حركة على هذا الشخص — بمَن نفّذها ومتى، اطّلاعٌ فقط. --}}
    <x-page-header
        title="سجلّ المشرف"
        :subtitle="$owner->name.' · '.$owner->code"
        :breadcrumbs="[['label' => 'الرئيسيّة', 'url' => route('dashboard')], ['label' => 'بروفايل المتطوّع'], ['label' => 'سجلّ المشرف']]" />

    @if ($rows->isEmpty())
        <x-empty message="مفيش حركات مسجّلة على الحساب ده" action="ارجع للبروفايل" :href="'/u/'.$owner->code" />
    @else
        <section class="card p-4">
            <ul class="space-y-3">
                @foreach ($rows as $row)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="min-w-0 truncate font-semibold">{{ $row->action }}</span>
                        <span class="text-xs" style="color: var(--text-muted)"
                              title="{{ $row->created_at->format('Y-m-d H:i') }}">
                            {{ $row->user?->shortName() ?? 'النظام' }} · {{ $row->created_at->diffForHumans() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
