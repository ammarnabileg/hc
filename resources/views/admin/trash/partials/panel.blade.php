{{--
    بانل «عرض قبل الاسترجاع» (`soft_delete_recovery.view` — 12.2.2) في بانل
    جانبيّ لا صفحة جديدة (2.15-أ-6)، وفيه فعلا الاسترجاع والحذف النهائيّ نفساهما
    خلف صلاحيّتيهما — فلا داعي لفتح صفٍّ ثانٍ لتنفيذ ما عُرِض هنا.
--}}
<div class="p-4 space-y-4">
    <header>
        <h2 class="font-bold text-base">{{ $row['title'] }}</h2>
        <p class="text-xs mt-1" style="color: var(--text-muted)">
            {{ setting('admin.trash.panel_deleted_at', 'اتحذف') }}
            {{ $row['deleted_at']?->translatedFormat('j F Y — H:i') }}
            @if ($row['deleted_by'])
                · {{ setting('admin.trash.panel_deleted_by', 'بمعرفة') }} {{ $row['deleted_by'] }}
            @endif
        </p>
    </header>

    <div>
        @if ($row['remaining_days'] > 0)
            <x-state-badge :state="$row['remaining_days'] <= 3 ? 'warn' : 'ok'"
                           :label="strtr((string) setting('admin.trash.days_left', 'باقي :days يوم'), [':days' => (string) $row['remaining_days']])" />
        @else
            <x-state-badge state="danger" :label="setting('admin.trash.window_closed_badge', 'المهلة خلصت')" />
        @endif
    </div>

    <section>
        <h3 class="font-semibold text-sm mb-2">{{ setting('admin.trash.panel_data_title', 'البيانات كما كانت وقت الحذف') }}</h3>
        <div class="card p-3 text-xs space-y-1 max-h-72 overflow-y-auto">
            @foreach ($row['attributes'] as $field => $value)
                @continue(in_array($field, ['password', 'remember_token'], true))
                <div class="flex gap-2">
                    <span class="shrink-0 font-mono" style="color: var(--text-muted)">{{ $field }}</span>
                    <span class="min-w-0 break-words">{{ is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-2 pt-2" style="border-top: 1px solid var(--border)">
        @can('soft_delete_recovery.restore')
            @if ($row['remaining_days'] > 0)
                <form method="post" action="{{ route('admin.ops.trash.restore', ['type' => $row['type'], 'id' => $row['id']]) }}">
                    @csrf
                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.trash.action_restore', 'استرجاع') }}</button>
                </form>
            @else
                <p class="text-xs" style="color: var(--text-muted)">{{ setting('admin.trash.restore_window_closed', 'انتهت مهلة الاسترجاع — العنصر متاح للحذف النهائيّ فقط.') }}</p>
            @endif
        @endcan

        @can('soft_delete_recovery.delete')
            <form method="post" action="{{ route('admin.ops.trash.destroy', ['type' => $row['type'], 'id' => $row['id']]) }}"
                  onsubmit="return confirm('{{ setting('admin.trash.confirm_delete', 'حذف نهائيّ لا يمكن التراجع عنه — متأكّد؟') }}')">
                @csrf
                @method('delete')
                <button class="rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--surface-sunken); color: var(--color-state-danger)">{{ setting('admin.trash.action_delete', 'حذف نهائيّ') }}</button>
            </form>
        @endcan
    </div>
</div>
