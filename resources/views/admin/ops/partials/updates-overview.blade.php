@php($failure = session('ops.failure') ?? (is_array($lastFailure?->report ? json_decode($lastFailure->report, true) : null) ? json_decode($lastFailure->report, true) : null))

{{-- ⛔ تقرير الفشل (2.11-ح · 2.17): ماذا حدث · ماذا عملنا · ماذا تفعل — بأعلى الشاشة --}}
@if ($failure)
    <div class="card p-4 mb-4"
         style="border: 1px solid var(--color-state-danger); background: color-mix(in srgb, var(--color-state-danger) 8%, transparent)">
        <div class="flex items-center gap-2 mb-2">
            <x-state-badge state="danger" label="التحديث وقف" />
            <h2 class="text-sm font-bold">{{ $failure['stage_label'] ?? 'تقرير الفشل' }}</h2>
        </div>

        <p class="text-sm mb-3">{{ $failure['what'] ?? '' }}</p>

        @if (! empty($failure['actions']))
            <div class="text-xs mb-3">
                <div class="font-semibold mb-1">اللي عملناه تلقائيًّا:</div>
                <ul class="space-y-1" style="color: var(--text-muted)">
                    @foreach ($failure['actions'] as $action)
                        <li>· {{ $action }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl p-3 text-xs" style="background: var(--surface-sunken)">
            <div class="font-semibold mb-1">ماذا تفعل الآن؟</div>
            <div>{{ $failure['next_steps'] ?? '' }}</div>
        </div>

        @if (! empty($failure['backup_file_id']) && ! ($failure['restored'] ?? false))
            @can('backups.restore')
                <button type="button" data-modal-open="restore-confirm"
                        class="mt-3 rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-raised); color: var(--color-state-danger); min-height: 44px">
                    ↩ استرجاع من النسخة الاحتياطيّة
                </button>
            @endcan
        @endif
    </div>
@endif

{{-- قواعد مثبّتة تُعرَض دائمًا (2.11): لا تنفيذ بلا معاينة، ولا ترحيل بلا نسخة --}}
<div class="card p-3 mb-4 text-xs" style="color: var(--text-muted)">
    كلّ هجرة تتنفّذ مرّة واحدة · مافيش حذف أعمى — الحذف بعد نقل وتحقّق · الأعمدة الجديدة بقيم افتراضيّة ·
    رفع الكود مابيمسّش الإعدادات ولا المرفوعات ولا الأسرار · النسخة الاحتياطيّة بتتاخد وتتفحص قبل الترحيل ·
    وضع الصيانة بيتفعّل أثناء الترحيل ويترفع بعده
    @if (setting('updates.dry_run_required', true)) · والـDry-run إلزاميّ قبل التنفيذ @endif
</div>

{{-- الفحوص القبليّة (2.11-ب): بعلامات ✓/✗ — وفحص واحد فاشل يمنع التنفيذ --}}
<div class="card overflow-hidden mb-4">
    <div class="px-4 py-3 flex items-center justify-between gap-2" style="border-bottom: 1px solid var(--border)">
        <h2 class="text-sm font-bold">الفحوص القبليّة</h2>
        <div class="flex items-center gap-2">
            <x-state-badge :state="$preflightOk ? 'ok' : 'danger'" :label="$preflightOk ? 'كلّها عدّت' : 'في فحص واقف'" />
            <form method="post" action="{{ route('admin.ops.updates.preflight') }}">
                @csrf
                <button class="rounded-xl px-3 py-2 text-xs" style="background: var(--surface-raised); min-height: 44px">إعادة الفحص</button>
            </form>
        </div>
    </div>

    <ul>
        @foreach ($checks as $check)
            <li class="px-4 py-3 flex items-start justify-between gap-3 text-sm" style="border-top: 1px solid var(--border)">
                <div class="min-w-0">
                    <div class="font-semibold">
                        <span style="color: {{ $check['state'] === 'ok' ? 'var(--color-state-ok)' : 'var(--color-state-danger)' }}">
                            {{ $check['state'] === 'ok' ? '✓' : '✗' }}
                        </span>
                        {{ $check['label'] }}
                    </div>
                    @if ($check['state'] !== 'ok')
                        <div class="text-xs mt-1" style="color: var(--text-muted)">{{ $check['hint'] }}</div>
                    @endif
                </div>
                <div class="text-xs shrink-0" style="color: var(--text-muted)">{{ $check['value'] }}</div>
            </li>
        @endforeach
    </ul>
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

{{-- جدول `schema_migrations` (2.11-أ): المعرّف · التوقيت · **Checksum** · الحالة --}}
<div class="card overflow-hidden mb-4">
    <div class="px-4 py-3 flex items-center justify-between gap-2" style="border-bottom: 1px solid var(--border)">
        <h2 class="text-sm font-bold">سجلّ الهجرات ببصمتها</h2>
        <span class="text-xs" style="color: var(--text-muted)">{{ $ledger->total() }} هجرة</span>
    </div>

    @if ($ledger->isEmpty())
        <p class="p-4 text-sm" style="color: var(--text-muted)">مافيش هجرات مسجَّلة لسه.</p>
    @else
        <table class="hidden md:table w-full text-sm">
            <thead style="background: var(--surface-sunken)">
                <tr class="text-xs" style="color: var(--text-muted)">
                    <th class="text-start p-3">المعرّف</th>
                    <th class="text-start p-3">التوقيت</th>
                    <th class="text-start p-3">Checksum</th>
                    <th class="text-start p-3">الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ledger as $row)
                    <tr style="border-top: 1px solid var(--border)">
                        <td class="p-3 font-mono text-xs break-all">{{ $row->migration }}</td>
                        <td class="p-3 text-xs">{{ $row->applied_at }}</td>
                        <td class="p-3 font-mono text-xs" title="{{ $row->checksum }}">{{ $row->checksum ? substr($row->checksum, 0, 12) : '—' }}</td>
                        <td class="p-3">
                            <x-state-badge :state="$row->status === 'applied' ? 'ok' : ($row->status === 'failed' ? 'danger' : 'idle')"
                                           :label="$row->status === 'applied' ? 'مطبَّقة' : ($row->status === 'failed' ? 'فاشلة' : 'مسترجَعة')" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="md:hidden">
            @foreach ($ledger as $row)
                <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                    <div class="font-mono text-xs break-all">{{ $row->migration }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ $row->applied_at }} · {{ $row->checksum ? substr($row->checksum, 0, 12) : 'بلا بصمة' }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="p-3">{{ $ledger->links() }}</div>
    @endif
</div>

{{-- شريط التقدّم المرحليّ (12.7-هـ): نسخة ⟵ هجرات ⟵ تحقّق ⟵ إنهاء --}}
@if ($runs !== [])
    @php($lastRun = $runs[0])
    @php($stages = ['backup' => 'نسخة احتياطيّة', 'migrate' => 'هجرات على دفعات', 'verify' => 'تحقّق', 'finish' => 'إنهاء'])
    @php($reached = array_search($lastRun->stage, array_keys($stages), true))

    <div class="card p-4 mb-4">
        <div class="flex items-center justify-between gap-2 mb-3">
            <h2 class="text-sm font-bold">آخر تشغيل</h2>
            <x-state-badge :state="$lastRun->status === 'success' ? 'ok' : ($lastRun->status === 'failed' ? 'danger' : 'warn')"
                           :label="$lastRun->status === 'success' ? 'نجح' : ($lastRun->status === 'failed' ? 'وقف' : 'شغّال')" />
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach ($stages as $key => $label)
                @php($done = $lastRun->status === 'success' || ($reached !== false && $loop->index < $reached))
                <div class="rounded-xl px-3 py-2 text-xs"
                     style="background: var(--surface-sunken); border-inline-start: 3px solid {{ $done ? 'var(--color-state-ok)' : ($lastRun->stage === $key && $lastRun->status === 'failed' ? 'var(--color-state-danger)' : 'var(--border)') }}">
                    {{ $done ? '✓' : ($lastRun->stage === $key && $lastRun->status === 'failed' ? '✗' : '○') }} {{ $label }}
                </div>
            @endforeach
        </div>

        <div class="text-xs mt-3" style="color: var(--text-muted)">
            {{ $lastRun->from_version }} ⟵ {{ $lastRun->to_version ?? $lastRun->from_version }} ·
            {{ $lastRun->performer_name ?? '—' }} · {{ $lastRun->started_at }}
        </div>
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
