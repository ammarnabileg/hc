{{-- النسخ المجدول بإعداداته: الدوريّة · الساعة · النوع · عدد النسخ المحفوظة (12.7-و) --}}
<form method="post" action="{{ route('admin.ops.system.schedule') }}" class="card p-4 mb-4 space-y-3">
    @csrf

    <label class="flex items-center gap-2 text-sm" style="min-height: 44px">
        <input type="hidden" name="enabled" value="0">
        <input type="checkbox" name="enabled" value="1" @checked($schedule['enabled'])>
        تشغيل النسخ المجدول
    </label>

    <div class="grid gap-3 md:grid-cols-4">
        <label class="block">
            <span class="block text-sm mb-1">الدوريّة</span>
            <select name="frequency" class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                @foreach ($frequencies as $key => $label)
                    <option value="{{ $key }}" @selected($key === $schedule['frequency'])>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <x-form.input name="time" label="الساعة" type="time" :value="$schedule['time']" hint="بتوقيت الخادم." />

        <label class="block">
            <span class="block text-sm mb-1">النوع</span>
            <select name="kind" class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                @foreach ($kinds as $key => $label)
                    <option value="{{ $key }}" @selected($key === $schedule['kind'])>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <x-form.input name="keep" label="عدد النسخ المحفوظة" type="number" :value="$schedule['keep']"
                      hint="الأقدم بيتمسح لوحده." min="1" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <span class="text-xs" style="color: var(--text-muted)">
            آخر تشغيل مجدول:
            {{ $schedule['last_run'] !== '' ? \Illuminate\Support\Carbon::parse($schedule['last_run'])->diffForHumans() : 'لسه مافيش' }}
        </span>
        @can('scheduled_jobs.manage')
            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">حفظ الجدولة</button>
        @endcan
    </div>
</form>

{{-- سجلّ عمليّات النظام: مَن نسخ ومَن مسح ومتى — للقراءة فقط --}}
@if ($logs)
    <div class="card overflow-hidden">
        <div class="px-4 py-3 flex items-center justify-between gap-2" style="border-bottom: 1px solid var(--border)">
            <h2 class="text-sm font-bold">سجلّ عمليّات النظام</h2>
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
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-3 text-xs" title="{{ $log->created_at }}">{{ \Illuminate\Support\Carbon::parse($log->created_at)->diffForHumans() }}</td>
                            <td class="p-3">{{ $log->actor_name ?? 'النظام' }}</td>
                            <td class="p-3">{{ \App\Services\Admin\Ops\OpsAudit::label($log->action) }}</td>
                            <td class="p-3 text-xs" style="color: var(--text-muted)">{{ \App\Services\Admin\Ops\OpsAudit::summarize($log->new_values) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="md:hidden">
                @foreach ($logs as $log)
                    <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                        <div class="font-semibold">{{ \App\Services\Admin\Ops\OpsAudit::label($log->action) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $log->actor_name ?? 'النظام' }} · {{ \Illuminate\Support\Carbon::parse($log->created_at)->diffForHumans() }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-5">{{ $logs->links() }}</div>
@endif
