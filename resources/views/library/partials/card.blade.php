@php
    /**
     * كارت الرفّ (20.1) — حرفيًّا من ملف الهويّة (`.shelf-item`/`.book-cover`):
     * غلافٌ ملوّنٌ بشريطٍ جانبيّ + أيقونة النوع، ثمّ الاسم، ثمّ حالة الإتاحة وفعلٌ واحد.
     */
    $blocked = ! $item['available'];
@endphp

<article class="shelf-item" data-library-name="{{ $item['title'] }}">
    <div class="book-cover">
        @if ($item['thumb'])
            <img src="{{ $item['thumb'] }}" alt="{{ $item['title'] }}" loading="lazy"
                 style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover">
        @else
            @include('library.components.type-icon', ['type' => $item['icon'], 'size' => 56])
        @endif
        <h3>{{ $item['title'] }}</h3>
        <span class="small muted">{{ $item['icon'] }}</span>
    </div>

    <div class="spread mt-4">
        <h3 class="truncate">{{ $item['title'] }}</h3>
        @if ($item['entitlement_id'])
            {{-- التفاصيل في بوب-أب لا صفحة جديدة (2.15-أ-6) --}}
            <button type="button" class="icon-button" data-modal-open="library-item"
                    data-item-url="{{ route('library.item', $item['entitlement_id']) }}"
                    aria-label="{{ setting('library.card.more_label', 'تفاصيل العنصر') }}">
                <x-icon name="more" size="16" />
            </button>
        @endif
    </div>

    <div class="spread mt-2">
        <x-state-badge :state="$item['availability']['state']" :label="$item['availability']['label']" />

        @unless ($blocked)
            <a href="{{ $item['action_url'] }}"
               @if ($item['icon'] === 'video' || $item['icon'] === 'audio' || $item['action_label'] === setting('library.action.download_label', 'تحميل')) target="_blank" rel="noopener" @endif
               class="btn text inline-flex items-center gap-1">
                {{ $item['action_label'] }} <x-icon name="left" size="14" />
            </a>
        @endunless
    </div>

    {{-- مشاركة كصورة بعلامة مائيّة + رابط ريفيرال (20.4) — يُخفى لمن لا يملك الصلاحيّة --}}
    <x-export-image kind="card" :title="$item['title']"
                     :subtitle="setting('library.share_image.subtitle', 'من مكتبتي على المنصّة')"
                     :rows="[[setting('library.share_image.referral_row_label', 'رابط دعوتي'), $referralLink ?? '']]" />
</article>
