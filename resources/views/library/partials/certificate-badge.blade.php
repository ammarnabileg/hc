{{--
  الشهادة وسامٌ على الرفّ لا كارتًا كباقي العناصر (20.1 — Peak-End) —
  حرفيًّا من ملف الهويّة (`.shelf-item` > `.certificate` الزخرفيّة).
--}}
<article class="shelf-item" data-cert-name="{{ $item['title'] }}" data-library-item="certificate-badge">
    <div class="certificate">
        <span class="brand-dot"></span>
        <small>{{ config('app.name') }}</small>
        <h3>{{ $item['title'] }}</h3>
        <x-state-badge :state="$item['availability']['state']" :label="$item['availability']['label']" />
    </div>

    <h3 class="mt-3 truncate">{{ $item['title'] }}</h3>

    <div class="mt-4">
        <a href="{{ $item['action_url'] }}" target="_blank" rel="noopener" class="btn text inline-flex items-center gap-1">
            {{ $item['action_label'] }} <x-icon name="left" size="14" />
        </a>
    </div>

    {{-- مشاركة كصورة بعلامة مائيّة + رابط ريفيرال (20.4) — يُخفى لمن لا يملك الصلاحيّة --}}
    <x-export-image kind="card" :title="$item['title']"
                     :subtitle="setting('library.share_image.subtitle', 'من مكتبتي على المنصّة')"
                     :rows="[[setting('library.share_image.referral_row_label', 'رابط دعوتي'), $referralLink ?? '']]" />
</article>
