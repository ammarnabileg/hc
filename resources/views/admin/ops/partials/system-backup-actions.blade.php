{{-- الأفعال داخل «⋯» بلمسة 44×44 — والحذف بعيد عن التنزيل بالغلط (2.15-ج) --}}
<details class="relative">
    <summary class="cursor-pointer rounded-xl text-sm inline-flex items-center justify-center"
             style="background: var(--surface-raised); min-width: 44px; min-height: 44px">⋯</summary>
    <div class="absolute end-0 mt-2 w-44 card p-2 z-20 text-sm space-y-1">
        @can('backups.export')
            <a class="block px-2 py-2 rounded hover:opacity-80"
               href="{{ route('admin.ops.system.backups.download', $row->id) }}">⬇ تنزيل</a>
        @endcan
        @can('backups.delete')
            <form method="post" action="{{ route('admin.ops.system.backups.destroy', $row->id) }}"
                  onsubmit="return confirm('نمسح النسخة دي نهائيًّا؟')">
                @csrf @method('delete')
                <button class="w-full text-start px-2 py-2 rounded hover:opacity-80"
                        style="color: var(--color-state-danger)">🗑 حذف</button>
            </form>
        @endcan
    </div>
</details>
