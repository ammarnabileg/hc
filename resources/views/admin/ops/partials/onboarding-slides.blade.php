{{-- اختيار الشاشة: رقائق أفقيّة متمرّرة — على الموبايل بلا ازدحام (2.15-ج) --}}
<div class="flex gap-2 min-w-0 overflow-x-auto no-scrollbar mb-4">
    @foreach ($screens as $key => $label)
        <a href="{{ route('admin.ops.onboarding', ['tab' => 'slides', 'screen' => $key]) }}"
           class="shrink-0 rounded-xl px-3 py-2 text-sm motion-standard"
           style="{{ $screen === $key
                ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                : 'background: var(--surface-raised); color: var(--text)' }}">
            {{ $label }}
            <span class="opacity-70">({{ $counts[$key]['active'] ?? 0 }})</span>
        </a>
    @endforeach
</div>

@if ($slides->isEmpty())
    {{-- الحالة الفارغة = سطر واحد + زرّ واحد، تشجّع ولا تعاتب (2.15-د · 2.17-ج) --}}
    <x-empty :message="setting('onboarding.slides.empty_text', 'لسّه مافيش شرائح — ابدأ بأوّل واحدة.')"
             action="معاينة الرحلة" :href="route('admin.ops.onboarding.preview', ['screen' => $screen])" />
@else
    <form method="post" action="{{ route('admin.ops.onboarding.reorder') }}" id="reorder-form">
        @csrf
        <input type="hidden" name="screen" value="{{ $screen }}">

        <div class="card overflow-hidden" id="slides-list">
            @foreach ($slides as $slide)
                <div class="p-3 flex items-start gap-3" style="border-top: 1px solid var(--border)" data-slide-row data-id="{{ $slide->id }}">
                    <span class="text-xs mt-1 shrink-0 w-6 text-center" style="color: var(--text-muted)" data-order-badge>{{ $loop->iteration }}</span>

                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-sm truncate">{{ $slide->title_ar }}</div>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">{{ \Illuminate\Support\Str::limit($slide->body_ar, 90) }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <x-state-badge :state="$slide->is_active ? 'ok' : 'idle'"
                                           :label="$slide->is_active ? 'ظاهرة' : 'موقوفة'" />
                            @if ($slide->action_label)
                                <span class="text-xs" style="color: var(--text-muted)">زرّ: {{ $slide->action_label }}</span>
                            @endif
                            @if ($slide->from_template)
                                <span class="text-xs" style="color: var(--text-muted)"><x-icon name="game" size="16" /> من قالب</span>
                            @endif
                        </div>
                    </div>

                    {{-- الأفعال في «⋯» بلمسة 44×44 — لا أزرار متلاصقة على الموبايل (2.15-ج) --}}
                    <details class="relative shrink-0">
                        <summary class="cursor-pointer rounded-xl text-sm flex items-center justify-center"
                                 style="background: var(--surface-raised); min-width: 44px; min-height: 44px">⋯</summary>
                        <div class="absolute end-0 mt-2 w-48 card p-2 z-20 text-sm space-y-1">
                            @can('onboarding.edit')
                                <button type="button" class="w-full text-start px-2 py-2 rounded hover:opacity-80"
                                        data-modal-open="slide-form"
                                        data-slide-edit="{{ $slide->id }}"
                                        data-title="{{ $slide->title_ar }}"
                                        data-body="{{ $slide->body_ar }}"
                                        data-action-label="{{ $slide->action_label }}"
                                        data-action-url="{{ $slide->action_url }}"
                                        data-active="{{ $slide->is_active ? 1 : 0 }}"><x-icon name="edit" size="16" /> تعديل</button>
                            @endcan
                            <button type="button" class="w-full text-start px-2 py-2 rounded hover:opacity-80" data-move="up">↑ فوق</button>
                            <button type="button" class="w-full text-start px-2 py-2 rounded hover:opacity-80" data-move="down">↓ تحت</button>
                        </div>
                    </details>
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            @can('onboarding.edit')
                <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">حفظ الترتيب</button>
            @endcan
        </div>
    </form>

    {{-- التفعيل والحذف: فورمات منفصلة حتى لا تتداخل مع فورم الترتيب --}}
    <div class="mt-3 grid gap-2 md:grid-cols-2">
        @foreach ($slides as $slide)
            <div class="card p-3 flex items-center justify-between gap-2 text-sm">
                <span class="truncate">{{ $slide->title_ar }}</span>
                <div class="flex items-center gap-2 shrink-0">
                    @can('onboarding.edit')
                        <form method="post" action="{{ route('admin.ops.onboarding.slides.toggle', $slide->id) }}">
                            @csrf
                            <button class="rounded-xl px-3 py-2 text-xs" style="background: var(--surface-raised); min-height: 44px">
                                {{ $slide->is_active ? 'إيقاف' : 'تفعيل' }}
                            </button>
                        </form>
                    @endcan
                    @can('onboarding.delete')
                        <form method="post" action="{{ route('admin.ops.onboarding.slides.destroy', $slide->id) }}"
                              onsubmit="return confirm('نمسح الشريحة دي؟ مش هترجع تاني.')">
                            @csrf @method('delete')
                            <button class="rounded-xl px-3 py-2 text-xs" style="background: var(--surface-raised); min-height: 44px; color: var(--color-state-danger)">حذف</button>
                        </form>
                    @endcan
                </div>
            </div>
        @endforeach
    </div>
@endif

@push('scripts')
    <script>
        // إعادة الترتيب: ↑/↓ بدل السحب — أسهل على اللمس وأدقّ بالكيبورد (2.15-ج)
        (function () {
            const list = document.getElementById('slides-list');
            const form = document.getElementById('reorder-form');
            if (!list || !form) return;

            const renumber = () => {
                list.querySelectorAll('[data-slide-row]').forEach((row, index) => {
                    const badge = row.querySelector('[data-order-badge]');
                    if (badge) badge.textContent = index + 1;
                });
            };

            list.addEventListener('click', (e) => {
                const button = e.target.closest('[data-move]');
                if (!button) return;
                const row = button.closest('[data-slide-row]');
                const sibling = button.dataset.move === 'up'
                    ? row.previousElementSibling
                    : row.nextElementSibling;
                if (!sibling) return;
                button.dataset.move === 'up'
                    ? list.insertBefore(row, sibling)
                    : list.insertBefore(sibling, row);
                renumber();
            });

            form.addEventListener('submit', () => {
                form.querySelectorAll('[data-order-input]').forEach((el) => el.remove());
                list.querySelectorAll('[data-slide-row]').forEach((row) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'order[]';
                    input.value = row.dataset.id;
                    input.setAttribute('data-order-input', '');
                    form.appendChild(input);
                });
            });
        })();
    </script>
@endpush
