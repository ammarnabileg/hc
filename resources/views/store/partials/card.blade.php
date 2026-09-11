@use('App\Services\Store\Coins')

@php
    /** كارت الشبكة الموحّدة (17 · 24.5): الغلاف · الاسم · السعر بعملته · الشارات. */
    $cover = $card['cover'] ? \Illuminate\Support\Facades\Storage::url($card['cover']) : null;
    // تسميات الأنواع من الإعدادات — المفتاح ثابتٌ في الكود والنصّ وحده يُدار (2.13)
    $typeLabels = (array) setting('store.card.type_labels', ['course' => 'تدريب', 'bundle' => 'باقة', 'product' => 'منتج', 'path' => 'مسار']);

    /*
     | شارة الإتاحة الزمنيّة (16 ⟵ 5): «نادي الفجر» 5→7 ص كان يظهر في الشبكة
     | الساعة 09:00 كأنّه مفتوح — والدستور يقول إنّه **مقفول** خارج ساعاته.
     | والقرار كلّه في الخادم (`StoreCatalog::availability`) بساعة المستخدم؛
     | و`null` تعني «بلا نافذة ولا فترات» فلا شارة أصلًا ولا يتغيّر شكل الكارت.
     */
    $availability = $card['availability'] ?? null;

    $availabilityLabel = $availability === null ? null : match (true) {
        $availability['open'] && $availability['closes_at'] !== null => str_replace(
            '{time}', $availability['closes_at'],
            (string) setting('store.availability.open_until_text', 'متاح الآن حتى {time}'),
        ),
        $availability['open'] => (string) setting('store.availability.open_badge', 'متاح الآن'),
        $availability['opens_at'] !== null => str_replace(
            '{time}', $availability['opens_at'],
            (string) setting('store.availability.opens_at_text', 'مغلق الآن — يفتح {time}'),
        ),
        default => (string) setting('store.availability.closed_badge', 'مغلق حاليًّا'),
    };
@endphp

<a href="{{ route('store.product', ['type' => $card['type'], 'slug' => $card['slug']]) }}"
   class="card overflow-hidden flex flex-col motion-standard hover:opacity-95 animate-fadeup">

    <div class="aspect-[16/10] w-full flex items-center justify-center overflow-hidden"
         style="background: var(--surface-sunken)">
        @if ($cover)
            <img src="{{ $cover }}" alt="{{ $card['title'] }}" class="w-full h-full object-cover" loading="lazy">
        @else
            <span class="text-3xl" aria-hidden="true"><x-icon :name="$card['type'] === 'bundle' ? 'bundle' : ($card['type'] === 'course' ? 'training' : 'article')" size="32" /></span>
        @endif
    </div>

    <div class="p-4 flex flex-col gap-2 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs rounded-full px-2 py-0.5"
                  style="background: var(--surface-sunken); color: var(--text-muted)">
                {{ $typeLabels[$card['type']] ?? setting('store.card.type_fallback_label', 'عنصر') }}
            </span>

            {{-- شارة «تملكه بالفعل» — ولا يُخفى ما اشتراه (24.5) --}}
            @if ($card['owned'])
                <x-state-badge state="ok" :label="setting('store.owned_badge', 'تملكه بالفعل')" />
            @endif

            {{-- شارة الإتاحة الزمنيّة — لا تظهر إلّا لعنصرٍ له نافذة/فترات (16 ⟵ 5) --}}
            @if ($availabilityLabel !== null)
                <x-state-badge :state="$availability['open'] ? 'ok' : $availability['state']"
                               :label="$availabilityLabel" />
            @endif

            {{-- شارة الخصم/الباقة بقيمتها الحقيقيّة — بلا ندرة مزيّفة (2.9) --}}
            @if ($card['savings'] > 0)
                <span class="text-xs rounded-full px-2 py-0.5 inline-flex items-center gap-1"
                      style="background: color-mix(in srgb, var(--color-brand-500) 15%, transparent); color: var(--color-brand-400)">
                    <span aria-hidden="true"><x-icon name="edit" size="16" /></span>
                    <span>{{ str_replace('{amount}', Coins::label($card['savings'], $card['currency']), setting('store.savings.text', 'وفّرت {amount}')) }}</span>
                </span>
            @endif
        </div>

        <h3 class="font-bold leading-6 line-clamp-2">{{ $card['title'] }}</h3>

        <div class="mt-auto pt-2 flex items-baseline gap-2">
            @if (! $card['sellable'])
                <span class="text-sm" style="color: var(--text-muted)">{{ setting('store.path.note_text', 'يُفتَح ضمن الباقات') }}</span>
            @elseif ($card['price'] <= 0)
                <span class="font-extrabold">{{ setting('store.free_label', 'مجّانيّ') }}</span>
            @else
                <span class="font-extrabold">{{ Coins::label($card['price'], $card['currency']) }}</span>
                @if ($card['list_price'] > $card['price'])
                    <span class="text-xs line-through" style="color: var(--text-muted)">{{ Coins::fmt($card['list_price']) }}</span>
                @endif
            @endif
        </div>
    </div>
</a>
