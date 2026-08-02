@php
    /**
     * مركز الإشعارات (2.8): ثلاثة تابات — الكلّ · المنصّة · التطوّع.
     * وتاب التطوّع لا يظهر إلا للمتطوّعين.
     */
    $u = auth()->user();
    $items = $u->notificationsFeed()->latest()->limit(20)->get();
@endphp

<div class="hidden absolute end-0 mt-2 w-[22rem] max-w-[92vw] modal-shell card z-50" data-bell-panel>
    <div class="modal-head px-4 py-3 flex items-center gap-2" style="border-bottom: 1px solid var(--border)">
        <strong class="text-sm flex-1">الإشعارات</strong>
        <button type="button" class="text-xs" style="color: var(--color-brand-500)" data-mark-all>تعليم الكلّ كمقروء</button>
    </div>

    <div class="px-4 pt-3 flex gap-2">
        <button class="rounded-full px-3 py-1 text-xs" data-bell-tab="all"
                style="background: var(--color-brand-500); color:#04201c">الكلّ</button>
        <button class="rounded-full px-3 py-1 text-xs" data-bell-tab="platform"
                style="background: var(--surface-sunken)">المنصّة</button>
        @volunteer
            <button class="rounded-full px-3 py-1 text-xs" data-bell-tab="volunteer"
                    style="background: var(--surface-sunken)">التطوّع</button>
        @endvolunteer
    </div>

    <div class="modal-body px-2 py-2" style="max-height: 60vh">
        @forelse ($items as $n)
            <a href="{{ $n->url ?: '#' }}" data-layer="{{ $n->layer }}"
               class="flex gap-2 rounded-xl px-2 py-2 motion-standard"
               @style(['background: var(--surface-sunken)' => ! $n->read_at])>
                <span class="text-sm">{{ $n->layer === 'volunteer' ? '🤝' : '🔵' }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm truncate">{{ $n->title }}</span>
                    <span class="block text-xs" style="color: var(--text-muted)">{{ $n->created_at?->diffForHumans() }}</span>
                </span>
                @if ($n->deadline_at)
                    <x-state-badge :state="$n->deadline_at->isPast() ? 'danger' : ($n->deadline_at->diffInHours() < 6 ? 'warn' : 'ok')"
                                   :label="$n->deadline_at->diffForHumans()" />
                @endif
            </a>
        @empty
            <p class="text-sm text-center py-6" style="color: var(--text-muted)">مفيش إشعارات جديدة</p>
        @endforelse
    </div>
</div>
