@php
    // «باقي N يوم» — نصٌّ من الإعدادات لا محروق (2.13)، ورقمٌ واحد يُستبدَل فيه
    $daysLeftLabel = fn (int $days) => strtr((string) setting('admin.trash.days_left', 'باقي :days يوم'), [':days' => (string) $days]);
@endphp

{{-- جدول 5–7 أعمدة على الديسكتوب، وكروت رأسيّة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">{{ setting('admin.trash.col_type', 'النوع') }}</th>
                <th class="text-start p-3">{{ setting('admin.trash.col_item', 'العنصر') }}</th>
                <th class="text-start p-3">{{ setting('admin.trash.col_deleted_by', 'مَن حذفه') }}</th>
                <th class="text-start p-3">{{ setting('admin.trash.col_deleted_at', 'متى') }}</th>
                <th class="text-start p-3">{{ setting('admin.trash.col_remaining', 'متبقٍّ للحذف النهائيّ') }}</th>
                <th class="text-start p-3">{{ setting('admin.trash.col_actions', 'إجراءات') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr style="border-top: 1px solid var(--border)" data-trash-row
                    data-url="{{ route('admin.ops.trash.show', ['type' => $row['type'], 'id' => $row['id']]) }}" class="cursor-pointer">
                    <td class="p-3">{{ $resources[$row['type']] ?? $row['type'] }}</td>
                    <td class="p-3 font-semibold">{{ $row['title'] }}</td>
                    <td class="p-3">{{ $row['deleted_by'] ?? '—' }}</td>
                    <td class="p-3 text-xs" title="{{ $row['deleted_at'] }}">{{ $row['deleted_at']?->diffForHumans() }}</td>
                    <td class="p-3">
                        @if ($row['remaining_days'] > 0)
                            <x-state-badge :state="$row['remaining_days'] <= 3 ? 'warn' : 'ok'"
                                           :label="$daysLeftLabel($row['remaining_days'])" />
                        @else
                            <x-state-badge state="danger" :label="setting('admin.trash.window_closed_badge', 'المهلة خلصت')" />
                        @endif
                    </td>
                    <td class="p-3" onclick="event.stopPropagation()">
                        <div class="flex items-center gap-2">
                            @can('soft_delete_recovery.restore')
                                @if ($row['remaining_days'] > 0)
                                    <form method="post" action="{{ route('admin.ops.trash.restore', ['type' => $row['type'], 'id' => $row['id']]) }}">
                                        @csrf
                                        <button class="text-xs underline">{{ setting('admin.trash.action_restore', 'استرجاع') }}</button>
                                    </form>
                                @endif
                            @endcan
                            @can('soft_delete_recovery.delete')
                                {{-- ⛔ حذف نهائيّ لا رجعة فيه — تأكيد المتصفّح الأساسيّ يكفي هنا، والتوثيق إلزاميّ في الخدمة --}}
                                <form method="post" action="{{ route('admin.ops.trash.destroy', ['type' => $row['type'], 'id' => $row['id']]) }}"
                                      onsubmit="return confirm('{{ setting('admin.trash.confirm_delete', 'حذف نهائيّ لا يمكن التراجع عنه. متأكّد؟') }}')">
                                    @csrf
                                    @method('delete')
                                    <button class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('admin.trash.action_delete', 'حذف نهائيّ') }}</button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="md:hidden">
        @foreach ($rows as $row)
            <details style="border-top: 1px solid var(--border)">
                <summary class="p-3 flex items-center justify-between gap-2 cursor-pointer">
                    <span class="min-w-0">
                        <span class="font-semibold text-sm block truncate">{{ $row['title'] }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $resources[$row['type']] ?? $row['type'] }}</span>
                    </span>
                    @if ($row['remaining_days'] > 0)
                        <x-state-badge :state="$row['remaining_days'] <= 3 ? 'warn' : 'ok'" :label="$daysLeftLabel($row['remaining_days'])" />
                    @else
                        <x-state-badge state="danger" :label="setting('admin.trash.window_closed_badge', 'المهلة خلصت')" />
                    @endif
                </summary>
                <div class="px-3 pb-3 text-xs space-y-2" style="color: var(--text-muted)">
                    <div>{{ setting('admin.trash.col_deleted_by', 'مَن حذفه') }}: {{ $row['deleted_by'] ?? '—' }}</div>
                    <div>{{ setting('admin.trash.col_deleted_at', 'متى') }}: {{ $row['deleted_at']?->diffForHumans() }}</div>

                    <div class="flex items-center gap-3 pt-1">
                        <button type="button" data-trash-row
                                data-url="{{ route('admin.ops.trash.show', ['type' => $row['type'], 'id' => $row['id']]) }}"
                                class="underline">{{ setting('admin.trash.action_view', 'عرض البيانات') }}</button>

                        @can('soft_delete_recovery.restore')
                            @if ($row['remaining_days'] > 0)
                                <form method="post" action="{{ route('admin.ops.trash.restore', ['type' => $row['type'], 'id' => $row['id']]) }}">
                                    @csrf
                                    <button class="underline">{{ setting('admin.trash.action_restore', 'استرجاع') }}</button>
                                </form>
                            @endif
                        @endcan

                        @can('soft_delete_recovery.delete')
                            <form method="post" action="{{ route('admin.ops.trash.destroy', ['type' => $row['type'], 'id' => $row['id']]) }}"
                                  onsubmit="return confirm('{{ setting('admin.trash.confirm_delete', 'حذف نهائيّ لا يمكن التراجع عنه. متأكّد؟') }}')">
                                @csrf
                                @method('delete')
                                <button class="underline" style="color: var(--color-state-danger)">{{ setting('admin.trash.action_delete', 'حذف نهائيّ') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </details>
        @endforeach
    </div>
</div>
