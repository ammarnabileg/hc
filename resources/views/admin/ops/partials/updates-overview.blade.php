{{-- قواعد مثبّتة تُعرَض دائمًا (2.11): لا تنفيذ بلا معاينة، ولا ترحيل بلا نسخة --}}
<div class="card p-3 mb-4 text-xs" style="color: var(--text-muted)">
    كلّ هجرة تتنفّذ مرّة واحدة · مافيش حذف أعمى · النسخة الاحتياطيّة بتتاخد قبل الترحيل
    @if (setting('updates.dry_run_required', true)) · والـDry-run إلزاميّ قبل التنفيذ @endif
</div>

{{-- الهجرات المعلّقة بالاسم — رقمٌ مجرّد لا يُبنى عليه قرار --}}
<div class="card overflow-hidden mb-4">
    <div class="px-4 py-3 flex items-center justify-between gap-2" style="border-bottom: 1px solid var(--border)">
        <h2 class="text-sm font-bold">الهجرات المعلّقة</h2>
        <x-state-badge :state="$pending === [] ? 'ok' : 'warn'"
                       :label="$pending === [] ? 'مافيش' : count($pending) . ' معلّقة'" />
    </div>

    @if ($pending === [])
        <p class="p-4 text-sm" style="color: var(--text-muted)">إنت على أحدث إصدار ✓</p>
    @else
        <ul class="p-2">
            @foreach ($pending as $name)
                <li class="px-2 py-2 font-mono text-xs break-all" style="border-top: 1px solid var(--border)">{{ $name }}</li>
            @endforeach
        </ul>
    @endif
</div>

@if ($dryRun)
    {{-- نتيجة Dry-run: ما سيُنفَّذ بالضبط — ولا حرف واحد اتغيّر في قاعدة البيانات --}}
    <div class="card overflow-hidden mb-4">
        <div class="px-4 py-3 flex items-center gap-2" style="border-bottom: 1px solid var(--border)">
            <x-state-badge state="ok" label="بلا تنفيذ" />
            <h2 class="text-sm font-bold">نتيجة الـDry-run</h2>
        </div>
        <pre class="p-4 text-xs overflow-x-auto" style="color: var(--text-muted); white-space: pre-wrap">{{ $dryRun['output'] }}</pre>
    </div>
@endif

{{-- الاسترجاع: التحذير بما سيُفقَد معروض قبل أيّ زرّ --}}
@can('updates.restore')
    <div class="card p-4 mb-4">
        <div class="flex items-center gap-2 mb-2">
            <x-state-badge state="danger" label="خطر" />
            <h2 class="text-sm font-bold">استرجاع آخر دفعة</h2>
        </div>

        @if ($lastBatch === [])
            <p class="text-sm" style="color: var(--text-muted)">مافيش دفعة نرجع عنها.</p>
        @else
            <p class="text-sm mb-2">الاسترجاع هيشيل الهجرات دي — <strong>وكلّ البيانات اللي جواها هتروح ولا ترجع</strong>:</p>
            <ul class="mb-3">
                @foreach ($lastBatch as $name)
                    <li class="font-mono text-xs break-all py-1">{{ $name }}</li>
                @endforeach
            </ul>
            <button type="button" data-modal-open="rollback-confirm"
                    class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised); color: var(--color-state-danger); min-height: 44px">
                استرجاع بتأكيد
            </button>
        @endif
    </div>
@endcan

{{-- سجلّ التدقيق: مَن نفّذ ماذا ومتى — للقراءة فقط (Append-only) --}}
@if ($logs)
    <div class="card overflow-hidden">
        <div class="px-4 py-3 flex items-center justify-between gap-2" style="border-bottom: 1px solid var(--border)">
            <h2 class="text-sm font-bold">آخر العمليّات</h2>
            <span class="text-xs" style="color: var(--text-muted)">للقراءة فقط</span>
        </div>

        @if ($logs->isEmpty())
            <p class="p-4 text-sm" style="color: var(--text-muted)">مافيش عمليّات مسجّلة لسه.</p>
        @else
            <table class="hidden md:table w-full text-sm">
                <thead style="background: var(--surface-sunken)">
                    <tr class="text-xs" style="color: var(--text-muted)">
                        <th class="text-start p-3">الوقت</th>
                        <th class="text-start p-3">المنفِّذ</th>
                        <th class="text-start p-3">العمليّة</th>
                        <th class="text-start p-3">التفاصيل</th>
                        <th class="text-start p-3">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-3 text-xs" title="{{ $log->created_at }}">{{ \Illuminate\Support\Carbon::parse($log->created_at)->diffForHumans() }}</td>
                            <td class="p-3">{{ $log->actor_name ?? '—' }}</td>
                            <td class="p-3">{{ \App\Services\Admin\Ops\OpsAudit::label($log->action) }}</td>
                            <td class="p-3 text-xs" style="color: var(--text-muted)">{{ \App\Services\Admin\Ops\OpsAudit::summarize($log->new_values) }}</td>
                            <td class="p-3 text-xs">{{ $log->ip }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- الموبايل: كروت رأسيّة — ممنوع التمرير الأفقيّ (2.15-ج) --}}
            <div class="md:hidden">
                @foreach ($logs as $log)
                    <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                        <div class="font-semibold">{{ \App\Services\Admin\Ops\OpsAudit::label($log->action) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $log->actor_name ?? '—' }} · {{ \Illuminate\Support\Carbon::parse($log->created_at)->diffForHumans() }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-5">{{ $logs->links() }}</div>
@endif
