@php
    /**
     * Paywall نفسيّ (16): الشهادة لحظة ذروة — وعند القفل رسالة **نفور من الخسارة**
     * تذكّر بما أنجزه فعلًا ثمّ تفتح الطريق للشراء بالسعر النهائيّ.
     *
     * بلا Dark Patterns (2.9): السعر معلن، ولا عدّاد وهميّ، ولا نصّ يعاتب.
     */
    $price = (float) ($paywall['price'] ?? 0);
@endphp

<div class="card p-4 mb-4" style="border-color: var(--color-brand-500)" role="status" data-paywall>
    <div class="flex items-start gap-3">
        {{-- أيقونة قفل مرسومة بهويّة المنصّة — بلا أيّ مكتبة أيقونات (2.16-ج) --}}
        <span class="shrink-0 mt-0.5" style="color: var(--color-brand-400)" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="10" width="16" height="10" rx="2.5" />
                <path d="M8 10V7.5a4 4 0 0 1 8 0V10" />
                <path d="M12 14v2.5" />
            </svg>
        </span>

        <div class="min-w-0 space-y-2">
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="font-bold">{{ setting('learning.paywall.title') }}</h2>
                <x-state-badge state="honor" :label="setting('learning.paywall.badge')" />
            </div>

            <p class="text-sm">{{ $paywall['headline'] }}</p>
            <p class="text-sm" style="color: var(--text-muted)">{{ $paywall['reason'] }}</p>

            <div class="flex items-center gap-3 flex-wrap pt-1">
                @if ($paywall['buy_url'])
                    <a href="{{ $paywall['buy_url'] }}"
                       class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">
                        {{ str_replace('{price}', \App\Services\Store\Coins::label($price), setting('learning.paywall.cta')) }}
                    </a>
                @endif

                @if (\Illuminate\Support\Facades\Route::has('learning.certificates'))
                    <a href="{{ route('learning.certificates') }}" class="text-sm hover:underline"
                       style="color: var(--color-brand-400)">{{ setting('learning.paywall.certificate_link') }}</a>
                @endif
            </div>
        </div>
    </div>
</div>
