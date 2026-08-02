@can('backups.create')
    <form method="post" action="{{ route('admin.ops.system.backups.store') }}" class="card p-3 mb-4 flex flex-wrap items-end gap-3">
        @csrf
        <label class="block">
            <span class="block text-sm mb-1">نوع النسخة</span>
            <select name="kind" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                @foreach ($kinds as $key => $label)
                    <option value="{{ $key }}" @selected($key === $schedule['kind'])>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c; min-height: 44px">خُد نسخة دلوقتي</button>
        <p class="text-xs w-full" style="color: var(--text-muted)">
            بنحتفظ بآخر {{ (int) setting('backups.keep_count', 7) }} نسخة — والأقدم بيتمسح لوحده.
        </p>
    </form>
@endcan

@if (! $backups || $backups->isEmpty())
    <x-empty message="مافيش نسخ لسه — خُد نسختك الأولى دلوقتي." />
@else
    <div class="card overflow-hidden">
        <table class="hidden md:table w-full text-sm">
            <thead style="background: var(--surface-sunken)">
                <tr class="text-xs" style="color: var(--text-muted)">
                    <th class="text-start p-3">التاريخ</th>
                    <th class="text-start p-3">النوع</th>
                    <th class="text-start p-3">الحجم</th>
                    <th class="text-start p-3">مَن أخذها</th>
                    <th class="text-start p-3">الحالة</th>
                    <th class="text-start p-3">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($backups as $row)
                    <tr style="border-top: 1px solid var(--border)">
                        <td class="p-3 text-xs" title="{{ $row->created_at }}">
                            {{ \Illuminate\Support\Carbon::parse($row->created_at)->diffForHumans() }}
                        </td>
                        <td class="p-3">{{ $kinds[$row->kind] ?? $row->kind }}{{ $row->is_scheduled ? ' · مجدولة' : '' }}</td>
                        <td class="p-3">{{ app(\App\Services\Admin\Ops\BackupManager::class)->humanSize((int) $row->size_bytes) }}</td>
                        <td class="p-3">{{ $row->author_name ?? 'النظام' }}</td>
                        <td class="p-3">
                            <x-state-badge :state="$row->status === 'done' ? 'ok' : 'danger'"
                                           :label="$row->status === 'done' ? 'سليمة' : 'فاشلة'" />
                        </td>
                        <td class="p-3">
                            @include('admin.ops.partials.system-backup-actions', ['row' => $row])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- الموبايل: كروت رأسيّة بأهمّ 3 حقول — بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="md:hidden">
            @foreach ($backups as $row)
                <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold">{{ $kinds[$row->kind] ?? $row->kind }}</span>
                        <x-state-badge :state="$row->status === 'done' ? 'ok' : 'danger'"
                                       :label="$row->status === 'done' ? 'سليمة' : 'فاشلة'" />
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ app(\App\Services\Admin\Ops\BackupManager::class)->humanSize((int) $row->size_bytes) }} ·
                        {{ \Illuminate\Support\Carbon::parse($row->created_at)->diffForHumans() }} ·
                        {{ $row->author_name ?? 'النظام' }}
                    </div>
                    <div class="mt-2">
                        @include('admin.ops.partials.system-backup-actions', ['row' => $row])
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-5">{{ $backups->links() }}</div>
@endif
