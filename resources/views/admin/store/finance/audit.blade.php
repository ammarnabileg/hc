@extends('layouts.app')

@section('title', 'سجلّ تدقيق الماليّات')

@section('content')
    <x-page-header title="سجلّ تدقيق الماليّات"
                   subtitle="للقراءة فقط — غير قابل للتعديل أو الحذف."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'الماليّات', 'url' => route('admin.finance.index')],
                       ['label' => 'سجلّ التدقيق'],
                   ]" />

    @if ($logs->isEmpty())
        <x-empty message="مافيش سجلّات في النطاق ده." />
    @else
        <div class="card overflow-hidden">
            <table class="hidden md:table w-full text-sm">
                <thead style="background: var(--surface-sunken)">
                    <tr class="text-xs" style="color: var(--text-muted)">
                        <th class="text-start p-3">الوقت</th>
                        <th class="text-start p-3">المنفِّذ</th>
                        <th class="text-start p-3">المورد.الفعل</th>
                        <th class="text-start p-3">قبل ← بعد</th>
                        <th class="text-start p-3">السبب</th>
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
