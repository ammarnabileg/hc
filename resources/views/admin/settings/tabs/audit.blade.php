{{-- سجلّ التدقيق: Append-only — لا تعديل ولا حذف من الواجهة إطلاقًا (24.3) --}}
<div class="card p-3 text-xs flex items-center justify-between" style="color: var(--text-muted)">
    <span>للقراءة فقط — غير قابل للتعديل أو الحذف.</span>
    <span>الاحتفاظ: {{ (int) setting('audit.retention_days', 365) }} يومًا للعامّ · بلا حدّ للماليّ <x-icon name="lock" size="16" /></span>
</div>

@if (! $logs || $logs->isEmpty())
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
                        <td class="p-3 font-mono text-xs">{{ $log->action }}</td>
                        <td class="p-3 text-xs">
                            {{ \Illuminate\Support\Str::limit((string) ($log->old_values['value'] ?? '—'), 20) }}
                            ← {{ \Illuminate\Support\Str::limit((string) ($log->new_values['value'] ?? '—'), 20) }}
                        </td>
                        <td class="p-3 text-xs">{{ $log->ip }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="md:hidden">
            @foreach ($logs as $log)
                <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                    <div class="font-mono text-xs">{{ $log->action }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ $log->user?->name ?? '—' }} · {{ $log->created_at?->diffForHumans() }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-5">{{ $logs->links() }}</div>
@endif
