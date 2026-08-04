@php
    /**
     * بطاقة منشور (13.2): عنوان · نصّ · وسائط · زرّ CTA · تاريخ.
     * تمييز غير المقروء بشريط جانبيّ + خلفية أعمق — لا بلون وحده.
     */
    use App\Services\Notifications\AnnouncementFeed;

    /*
     | ⭐ وضع المعاينة (12.6-أ): نفس البطاقة بالضبط — لكن بلا أفعالٍ تكتب في
     | القاعدة، فلا يصوّت الأدمن في استطلاعه وهو «بيتفرّج» ولا يفسد أرقامه.
     */
    $interactive = ! ($preview ?? false);
    $isRead = (bool) ($read?->read_at);
    $isAcknowledged = (bool) ($read?->acknowledged_at);
    $type = AnnouncementFeed::typeOf($announcement);
    $typeLabel = AnnouncementFeed::types()[$type] ?? '';
    $media = $announcement->media_path;
    $isVideo = $media && preg_match('/\.(mp4|webm|ogg)$/i', $media);
    $mediaUrl = $media ? (str_starts_with($media, 'http') ? $media : asset($media)) : null;
    $publishedAt = $announcement->scheduled_at ?: $announcement->created_at;
@endphp

<article class="card p-4 animate-fadeup" data-announcement="{{ $announcement->id }}"
         @if (! $isRead) data-unread="1" @endif
         @style([
             'border-inline-start: 3px solid var(--color-brand-500)' => ! $isRead,
             'background: var(--surface-sunken)' => ! $isRead,
         ])>

    <header class="flex items-start gap-2 flex-wrap">
        @if ($announcement->is_pinned)
            <span class="text-sm" title="{{ setting('announcements.card.title_1', 'منشور مثبَّت') }}" aria-label="{{ setting('announcements.card.aria_label_1', 'مثبَّت') }}"><x-icon name="placement" size="16" /></span>
        @endif

        <h2 class="font-bold flex-1 min-w-40">{{ $announcement->title }}</h2>

        @if (! $isRead)
            <span class="rounded-full px-2 py-0.5 text-xs"
                  style="background: var(--color-brand-600); color: #04201c">{{ setting('announcements.card.text_1', 'جديد') }}</span>
        @endif

        @if ($typeLabel)
            <span class="rounded-full px-2 py-0.5 text-xs"
                  style="background: var(--surface-raised); color: var(--text-muted)">{{ $typeLabel }}</span>
        @endif
    </header>

    {{-- تاريخ نسبيّ، والكامل بالضغط/الـHover (2.15-د) --}}
    <div class="mt-1 text-xs" style="color: var(--text-muted)"
         title="{{ $publishedAt?->format('Y-m-d H:i') }}">{{ $publishedAt?->diffForHumans() }}</div>

    @if ($announcement->body)
        <p class="announcement-body mt-3 text-sm leading-7 whitespace-pre-line">{{ $announcement->body }}</p>

        <button type="button" class="mt-1 text-xs" style="color: var(--color-brand-500)"
                data-announcement-details
                data-title="{{ $announcement->title }}"
                data-body="{{ $announcement->body }}"
                data-cta-url="{{ $announcement->cta_url }}"
                data-cta-label="{{ $announcement->cta_label }}">{{ setting('announcements.card.text_2', 'التفاصيل') }}</button>
    @endif

    @if ($mediaUrl)
        <div class="mt-3 overflow-hidden rounded-xl">
            @if ($isVideo)
                <video src="{{ $mediaUrl }}" controls class="w-full" preload="none"></video>
            @else
                <img src="{{ $mediaUrl }}" alt="{{ $announcement->title }}" class="w-full" loading="lazy">
            @endif
        </div>
    @endif

    @if ($announcement->cta_url)
        <a href="{{ $announcement->cta_url }}" target="_blank" rel="noopener"
           class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold mt-3 motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ $announcement->cta_label ?: (string) setting('announcements.card.expr_1', 'افتح') }}</a>
    @endif

    {{--
     | استطلاع داخل المنشور (12.6-أ): عامّ النتيجة أو مخفيّها.
     | ⛔ المخفيّ **لا رقم له هنا إطلاقًا** — `$poll['results']` تكون `null`
     | فلا تُطبَع الأعداد ولا تُخبَّأ بـCSS ولا تُمرَّر في `data-` (2.9).
     --}}
    @if ($poll)
        <section class="mt-3 rounded-xl p-3" style="background: var(--surface-raised); border: 1px solid var(--border)"
                 aria-label="{{ setting('announcements.card.aria_label_2', 'استطلاع') }}">
            <div class="text-sm font-semibold">{{ $poll['question'] }}</div>

            <div class="mt-2 space-y-2">
                @foreach ($poll['options'] as $index => $option)
                    @php $isChoice = $poll['choice'] === $index; @endphp

                    @if ($poll['closed'] || $poll['choice'] !== null)
                        <div class="text-sm flex items-center justify-between gap-2 rounded-xl px-3 py-2"
                             @style([
                                 'min-height: 44px',
                                 'background: var(--surface-sunken)',
                                 'border: 1px solid var(--color-brand-500)' => $isChoice,
                             ])>
                            <span>{{ $option }} @if ($isChoice)<span class="text-xs">✓ {{ setting('announcements.card.text_3', 'اختيارك') }}</span>@endif</span>
                            @if ($poll['results'])
                                <span class="text-xs" style="color: var(--text-muted)">{{ $poll['results']['counts'][$index] ?? 0 }}</span>
                            @endif
                        </div>
                    @elseif ($interactive)
                        <form method="post" action="{{ route('announcements.poll', $announcement) }}" data-ajax-form>
                            @csrf
                            <input type="hidden" name="option_index" value="{{ $index }}">
                            <button type="submit" class="btn w-full text-start rounded-xl px-3 py-2 text-sm motion-standard"
                                    style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border)">{{ $option }}</button>
                        </form>
                    @else
                        <button type="button" class="btn w-full text-start rounded-xl px-3 py-2 text-sm"
                                style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border)">{{ $option }}</button>
                    @endif
                @endforeach
            </div>

            <div class="mt-2 text-xs" style="color: var(--text-muted)">
                @if ($poll['results'])
                    {{ setting('announcements.card.text_4', 'إجماليّ الأصوات') }} {{ $poll['results']['total'] }}
                @else
                    {{ $poll['hidden_notice'] }}
                @endif
            </div>
        </section>
    @endif

    {{-- تفاعل إيموجي: يظهر فقط لو الأدمن سمح به لهذا المنشور (13.2) --}}
    @if ($announcement->reactions_enabled && ! $interactive)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            @foreach ($reactions as $emoji)
                <span class="btn rounded-full px-3 py-1 text-sm" style="background: var(--surface-raised)">{{ $emoji }}</span>
            @endforeach
        </div>
    @elseif ($announcement->reactions_enabled)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            @foreach ($reactions as $emoji)
                <form method="post" action="{{ route('announcements.react', $announcement) }}" data-ajax-form>
                    @csrf
                    <input type="hidden" name="reaction" value="{{ $emoji }}">
                    <button type="submit" class="btn rounded-full px-3 py-1 text-sm motion-standard"
                            aria-label="{{ setting('announcements.card.aria_label_3', 'تفاعل') }} {{ $emoji }}"
                            @style([
                                'background: var(--surface-raised)',
                                'background: var(--color-brand-600); color: #04201c' => ($read?->reaction) === $emoji,
                            ])>
                        {{ $emoji }}
                        @if (! empty($counts[$emoji]))
                            <span class="text-xs opacity-70">{{ $counts[$emoji] }}</span>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    <footer class="mt-3 flex flex-wrap items-center gap-2">
        @if ($announcement->requires_acknowledge && ! $interactive)
            <span class="btn rounded-xl px-4 py-2 text-sm font-bold"
                  style="background: var(--color-brand-500); color: #04201c">{{ $ackLabel }}</span>
        @elseif ($announcement->requires_acknowledge)
            @if ($isAcknowledged)
                <x-state-badge state="ok" label="{{ setting('announcements.card.label_1', 'أقررتَ بقراءته') }}" />
            @else
                <form method="post" action="{{ route('announcements.acknowledge', $announcement) }}" data-ajax-form>
                    @csrf
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-bold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ $ackLabel }}</button>
                </form>
            @endif

            @if ($announcement->acknowledge_xp > 0 && ! $isAcknowledged)
                <span class="text-xs" style="color: var(--text-muted)">+{{ $announcement->acknowledge_xp }} XP {{ setting('announcements.card.text_5', 'مرّة واحدة') }}</span>
            @endif
        @endif

        @if (! $isRead && $interactive)
            <form method="post" action="{{ route('announcements.read', $announcement) }}" data-ajax-form class="ms-auto">
                @csrf
                <button type="submit" class="btn rounded-xl px-3 py-2 text-xs motion-standard"
                        style="background: var(--surface-raised); border: 1px solid var(--border)">{{ setting('announcements.card.text_6', 'تعليم كمقروء') }}</button>
            </form>
        @endif
    </footer>
</article>
