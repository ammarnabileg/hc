{{--
    إجراءات عنصر المكتبة (12.4-د): نفس التفاصيل — انسخ المسار / عدّل / احذف —
    يستدعيها كلٌّ من عرض الشبكة وعرض القائمة، فلا يتكرّر جدولٌ يدويّ ثانٍ.
    يتوقّع `$item` و`$usage` من السياق المستدعي.
--}}
<details class="mt-2">
    <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('admin.courses.media.ijraat', 'إجراءات') }}</summary>
    <div class="mt-2 space-y-2">
        <input type="text" readonly value="{{ $item->path }}"
               class="w-full rounded-lg px-2 py-1 text-xs" data-copy
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

        @can('media_library.edit')
            <form method="post" action="{{ route('admin.media.update', $item) }}" class="space-y-2">
                @csrf @method('put')
                <input type="text" name="name" value="{{ $item->name }}"
                       class="w-full rounded-lg px-2 py-1 text-xs"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <input type="text" name="tags" value="{{ implode(',', (array) $item->tags) }}"
                       placeholder="{{ setting('admin.courses.media.wswm_mfswla_bfasla', 'وسوم مفصولة بفاصلة') }}" class="w-full rounded-lg px-2 py-1 text-xs"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <input type="text" name="folder" value="{{ $item->folder }}" placeholder="{{ setting('admin.courses.media.mjld', 'مجلّد') }}"
                       class="w-full rounded-lg px-2 py-1 text-xs"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <button class="text-xs underline">{{ setting('admin.courses.media.hfz', 'حفظ') }}</button>
            </form>
        @endcan

        @can('media_library.delete')
            <form method="post" action="{{ route('admin.media.destroy', $item) }}"
                  onsubmit="return confirm('{{ ($usage[$item->id] ?? 0) > 0 ? setting('media.delete.in_use_warning', 'الملفّ ده مستخدَم في أماكن تانية — متأكّد؟') : setting('admin.courses.media.nshyl_almlf', 'نشيل الملفّ؟') }}')">
                @csrf @method('delete')
                @if (($usage[$item->id] ?? 0) > 0)
                    <input type="hidden" name="force" value="1">
                @endif
                <button class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('admin.courses.media.hdhf', 'حذف') }}</button>
            </form>
        @endcan
    </div>
</details>
