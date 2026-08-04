@extends('layouts.admin')

@section('title', setting('admin.store.finance.audit.sjl_tdqyq_almalyat', 'سجلّ تدقيق الماليّات'))

@section('content')
    <x-page-header :title="setting('admin.store.finance.audit.sjl_tdqyq_almalyat', 'سجلّ تدقيق الماليّات')"
                   :subtitle="setting('admin.store.finance.audit.llqraa_fqt_ghyr_qabl_lltadyl_aw_alhdhf', 'للقراءة فقط — غير قابل للتعديل أو الحذف.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.store.finance.audit.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.store.finance.audit.almalyat', 'الماليّات'), 'url' => route('admin.finance.index')],
                       ['label' => setting('admin.store.finance.audit.sjl_altdqyq', 'سجلّ التدقيق')],
                   ]" />

    @if ($logs->isEmpty())
        <x-empty :message="setting('admin.store.finance.audit.mafysh_sjlat_fy_alntaq_dh', 'مافيش سجلّات في النطاق ده.')" />
    @else
        <div class="card overflow-hidden">
            <table class="hidden md:table w-full text-sm">
                <thead style="background: var(--surface-sunken)">
                    <tr class="text-xs" style="color: var(--text-muted)">
                        <th class="text-start p-3">{{ setting('admin.store.finance.audit.alwqt', 'الوقت') }}</th>
                        <th class="text-start p-3">{{ setting('admin.store.finance.audit.almnfdh', 'المنفِّذ') }}</th>
                        <th class="text-start p-3">{{ setting('admin.store.finance.audit.almwrd_alfal', 'المورد.الفعل') }}</th>
                        <th class="text-start p-3">{{ setting('admin.store.finance.audit.qbl_bad', 'قبل ← بعد') }}</th>
                        <th class="text-start p-3">{{ setting('admin.store.finance.audit.alsbb', 'السبب') }}</th>
                        <th class="text-start p-3">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-3 text-xs" title="{{ $log->created_at }}">{{ $log->created_at?->diffForHumans() }}</td>
                            <td class="p-3">
                                @if ($log->user)
                                    <a class="underline" href="{{ $log->user->profileUrl() }}">{{ $log->user->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="p-3 font-mono text-xs"><x-icon name="lock" size="16" /> {{ $log->action }}</td>
                            <td class="p-3 text-xs">
                                {{ \Illuminate\Support\Str::limit((string) ($log->old_values['value'] ?? '—'), 24) }}
                                ← {{ \Illuminate\Support\Str::limit((string) ($log->new_values['value'] ?? '—'), 24) }}
                            </td>
                            <td class="p-3 text-xs">{{ $log->new_values['reason'] ?? '—' }}</td>
                            <td class="p-3 text-xs">{{ $log->ip }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="md:hidden">
                @foreach ($logs as $log)
                    <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                        <div class="font-mono text-xs"><x-icon name="lock" size="16" /> {{ $log->action }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $log->user?->name ?? '—' }} · {{ $log->created_at?->diffForHumans() }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-5">{{ $logs->links() }}</div>
    @endif
@endsection
