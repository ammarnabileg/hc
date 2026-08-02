{{-- ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4) --}}
<x-filters :action="route('admin.store.index')">
    <input type="hidden" name="tab" value="{{ $tab }}">

    <label class="text-xs">
        <span class="block mb-1" style="color: var(--text-muted)">بحث</span>
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="الاسم أو الرقم…"
               class="rounded-xl px-3 py-2 text-sm w-48"
               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
    </label>

    @if ($tab === 'products')
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">التصنيف</span>
            <select name="category" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category'] == $category->id)>{{ $category->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">الحالة</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="published" @selected($filters['status'] === 'published')>منشور</option>
                <option value="draft" @selected($filters['status'] === 'draft')>مسودّة</option>
                <option value="archived" @selected($filters['status'] === 'archived')>مؤرشف</option>
            </select>
        </label>
    @endif

    @if ($tab === 'library')
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">وضع الحماية</span>
            <select name="protection" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($protectionModes as $key => $label)
                    <option value="{{ $key }}" @selected($filters['protection'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    @endif

    @if ($tab === 'orders')
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">الحالة</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="paid" @selected($filters['status'] === 'paid')>مكتمل</option>
                <option value="pending" @selected($filters['status'] === 'pending')>معلّق</option>
                <option value="failed" @selected($filters['status'] === 'failed')>فاشل</option>
                <option value="cancelled" @selected($filters['status'] === 'cancelled')>ملغى</option>
            </select>
        </label>
    @endif

    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
            style="background: var(--color-brand-500); color: #04201c">فلترة</button>

    <x-slot:advanced>
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">من</span>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">إلى</span>
            <input type="date" name="to" value="{{ $filters['to'] }}" class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-slot:advanced>
</x-filters>
