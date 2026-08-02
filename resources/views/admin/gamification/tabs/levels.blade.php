{{-- المستويات: جدول levels بعتبات XP --}}

<section class="card p-4 md:p-5">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
        <h2 class="font-bold">المستويات وعتبات XP</h2>
        @can('achievements.edit')
            <button type="button" data-modal-open="level-modal"
                    class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">+ مستوى</button>
        @endcan
    </div>

    @forelse ($data['levels'] as $level)
        <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
            <div class="min-w-0">
                <span class="font-semibold">المستوى {{ $level->level }} — {{ $level->name_ar }}</span>
                <div class="text-xs" style="color: var(--text-muted)">يبدأ من {{ number_format($level->min_xp) }} XP</div>
            </div>
            <span class="flex items-center gap-2 shrink-0">
                @can('achievements.edit')
                    <button type="button" class="text-xs underline" data-level-edit
                            data-id="{{ $level->id }}" data-level="{{ $level->level }}"
                            data-name="{{ $level->name_ar }}" data-xp="{{ $level->min_xp }}">تعديل</button>
                @endcan
                @can('achievements.manage')
                    <form method="post" action="{{ route('admin.gamification.levels.delete', $level) }}"
                          onsubmit="return confirm('تحذف المستوى ده؟')">
                        @csrf
                        <button type="submit" class="text-xs underline" style="color: var(--color-state-danger)">حذف</button>
                    </form>
                @endcan
            </span>
        </div>
    @empty
        <x-empty message="مفيش مستويات لسّه — أضف أوّل مستوى." />
    @endforelse
</section>

@push('modals')
    @can('achievements.edit')
        <x-modal id="level-modal" title="مستوى">
            <form method="post" action="{{ route('admin.gamification.levels.save') }}">
                @csrf
                <input type="hidden" name="id" id="level-id">

                <label class="block text-sm font-semibold mb-1" for="level-num">رقم المستوى</label>
                <input type="number" min="1" name="level" id="level-num" required
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="level-name">الاسم</label>
                <input type="text" name="name_ar" id="level-name" required maxlength="80"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="level-xp">عتبة XP</label>
                <input type="number" min="0" name="min_xp" id="level-xp" required
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">احفظ</button>
            </form>
        </x-modal>
    @endcan
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-level-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('level-id').value = btn.dataset.id;
                document.getElementById('level-num').value = btn.dataset.level;
                document.getElementById('level-name').value = btn.dataset.name;
                document.getElementById('level-xp').value = btn.dataset.xp;
                const modal = document.getElementById('level-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });
    </script>
@endpush
