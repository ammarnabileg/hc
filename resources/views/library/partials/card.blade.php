@php
    /** كارت الرفّ (20.1): ثامبنيل · أيقونة النوع · الاسم · زرّ رئيسيّ واحد · حالة الإتاحة */
    $blocked = ! $item['available'];
@endphp

<article class="card p-3 flex flex-col gap-2 animate-fadeup">
    <div class="relative rounded-xl overflow-hidden aspect-4/3" style="background: var(--surface-sunken)">
        @if ($item['thumb'])
            <img src="{{ $item['thumb'] }}" alt="{{ $item['title'] }}" loading="lazy" class="w-full h-full object-cover">
        @else
            <div class="w-full h-full flex items-center justify-center" style="color: var(--text-muted)">
                @include('library.components.type-icon', ['type' => $item['icon'], 'size' => 34])
            </div>
        @endif

        <span class="absolute top-2 start-2 rounded-full p-1.5 flex items-center"
              style="background: color-mix(in srgb, var(--surface) 80%, transparent); color: var(--color-brand-400)"
              title="{{ $item['icon'] }}">
            @include('library.components.type-icon', ['type' => $item['icon'], 'size' => 16, 'label' => $item['icon']])
        </span>
    </div>

    <h3 class="text-sm font-semibold leading-6 line-clamp-2" title="{{ $item['title'] }}">{{ $item['title'] }}</h3>

    <div class="flex items-center justify-between gap-2">
        <x-state-badge :state="$item['availability']['state']" :label="$item['availability']['label']" />

        @if ($item['entitlement_id'])
            {{-- التفاصيل في بوب-أب لا صفحة جديدة (2.15-أ-6) --}}
            <button type="button" class="text-xs rounded-lg px-2 py-1 motion-standard"
                    style="background: var(--surface-sunken); color: var(--text-muted)"
                    data-modal-open="library-item"
                    data-item-url="{{ route('library.item', $item['entitlement_id']) }}"
                    aria-label="{{ setting('library.card.more_label', 'تفاصيل العنصر') }}">⋯</button>
        @endif
    </div>

    @if ($blocked)
        <span class="text-xs rounded-xl px-3 py-2 text-center" style="background: var(--surface-sunken); color: var(--text-muted)">
            {{ $item['availability']['label'] }}
        </span>
    @else
        <a href="{{ $item['action_url'] }}"
           @if ($item['icon'] === 'video' || $item['icon'] === 'audio' || $item['action_label'] === setting('library.action.download_label', 'تحميل')) target="_blank" rel="noopener" @endif
           class="btn flex items-center justify-center rounded-xl px-3 py-2 text-sm font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ $item['action_label'] }}</a>
    @endif

    {{-- مشاركة كصورة بعلامة مائيّة + رابط ريفيرال (20.4) — يُخفى لمن لا يملك الصلاحيّة --}}
    <x-export-image kind="card" :title="$item['title']"
                     :subtitle="setting('library.share_image.subtitle', 'من مكتبتي على المنصّة')"
                     :rows="[[setting('library.share_image.referral_row_label', 'رابط دعوتي'), $referralLink ?? '']]" />
</article>
