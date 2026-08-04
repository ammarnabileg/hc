{{--
    ⭐ **لاندنج بيدج البندل** (18 — البند الذي كان «🕒 قيد التفصيل لاحقًا» في القسم 22،
    وأمر المالك ببنائه). الصفحة تعرض ولا تحسب: كلّ رقمٍ فيها يأتي محسوبًا من
    `App\Services\Store\BundleLanding` لحظةَ العرض.

    ثلاثة أشياء **غائبةٌ هنا عمدًا**، ولكلٍّ نصُّه:
      · **لا شهادات عملاء ولا آراء** — لا جدول لها في المنصّة، ورأيٌ مفبرك خرقٌ
        صريح لـ«لا أرقام وهمية» (2.9 · 21.1-د).
      · **لا «ضمان استرجاع» ولا «جرّبها بلا مخاطرة»** — 19.4 قاعدةٌ نهائيّة: «لا
        استرجاع نقديّ لأيّ مدفوعات». والبديل الأمين: **وصولٌ دائم** + رابط السياسة
        **قبل** الزرّ.
      · **لا عدّاد ولا مقاعد بلا بيانات حقيقيّة** — البلوك كلّه لا يُطبَع في الـHTML
        ما لم يضبط الأدمن `available_until` أو `purchase_limit`.
--}}
@extends('layouts.app')

@use('App\Services\Store\Coins')

@section('title', $landing['headline'])
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) ($landing['promise'] ?? $item->description ?? '')), 155))

@if ($ogImage)
    {{-- صورة OG لكلّ بندل — أيّ رابط يُنشَر يظهر بطاقةً لا رابطًا أصلع (21.1-أ) --}}
    @section('og_image', $ogImage)
@endif

@if (! $indexable)
    {{-- الفهرسة تحترم إعداد النموّ **وعلَم البندل نفسه** (21.1-هـ) --}}
    @section('noindex', '1')
@endif

@push('head')
    {{-- Schema.org `Product` + `Offer` بسعرٍ وعملةٍ **وتوافرٍ حقيقيّ** (21.2-ب) --}}
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
    @php
        $owned = $landing['owned'];
        $sellable = ! $owned && $landing['available'];
        $canBuy = auth()->check() && $sellable;
        $cover = $item->cover_path ? \Illuminate\Support\Facades\Storage::url($item->cover_path) : null;
        $libraryUrl = \Illuminate\Support\Facades\Route::has('library.index') ? route('library.index') : null;

        // ⭐ نداءٌ واحد للفعل بنصٍّ واحد من الإعدادات، يتكرّر ولا يُنافَس (2.15-أ-2)
        $buyLabel = (string) setting('store.bundle.cta_label', 'احصل على الباقة كاملة');
        $priceLabel = $landing['price'] > 0
            ? Coins::label($landing['price'], $quote['currency'])
            : (string) setting('store.free_label', 'مجّانيّ');
        $typeLabels = (array) setting('store.bundle.item_type_labels', []);
    @endphp

    <x-page-header :title="$item->name_ar"
                   :breadcrumbs="[
                       ['label' => 'المتجر', 'url' => route('store.index')],
                       ['label' => 'الباقات', 'url' => route('store.bundles')],
                       ['label' => $item->name_ar],
                   ]" />

    @if (session('status'))
        <x-toast :message="session('status')" />
    @endif

    @error('checkout')
        <x-toast :message="$message" state="danger" />
    @enderror

    {{-- ============================================================ 1) الهيرو --}}
    <section class="card overflow-hidden">
        <div class="grid gap-0 lg:grid-cols-5">
            <div class="lg:col-span-3 min-w-0 p-5 sm:p-7 flex flex-col gap-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs rounded-full px-3 py-1 inline-flex items-center gap-1"
                          style="background: color-mix(in srgb, var(--color-brand-500) 15%, transparent); color: var(--color-brand-400)">
                        <x-icon name="bundle" size="14" />
                        <span>{{ setting('store.bundle.hero_badge', 'باقة متكاملة') }}</span>
                    </span>

                    @if ($owned)
                        <x-state-badge state="ok" label="تملكه بالفعل" />
                    @endif

                    {{--
                        ⭐ «وفّرت X» **محسوبة لا مكتوبة** (21.1-د)، ولا تُطبَع إطلاقًا
                        إن كانت القيمة الإجماليّة ≤ سعر الباقة — لا صفرًا ولا سالبًا.
                    --}}
                    @if ($landing['has_savings'])
                        <span class="text-xs rounded-full px-3 py-1 inline-flex items-center gap-1"
                              style="background: color-mix(in srgb, var(--color-state-honor) 18%, transparent); color: var(--color-state-honor)">
                            <x-icon name="balance-scale" size="14" />
                            <span>{{ str_replace('{amount}', Coins::label($landing['savings'], $quote['currency']), setting('store.savings.text', 'وفّرت {amount}')) }}</span>
                        </span>
                    @endif
                </div>

                {{-- ⭐ العنوان يبيع **النتيجة**؛ وإن لم يكتبه الأدمن رجع لاسم الباقة بلا فراغ --}}
                <h1 class="text-2xl sm:text-3xl font-extrabold leading-9">{{ $landing['headline'] }}</h1>

                @if ($landing['promise'])
                    <p class="text-sm leading-7" style="color: var(--text-muted)">{{ $landing['promise'] }}</p>
                @endif

                {{-- كتلة السعر: مشطوب + سعر الباقة + «وفّرت» المحسوبة (Anchoring — 18) --}}
                <div class="flex items-baseline gap-3 flex-wrap">
                    <span class="text-3xl font-extrabold">{{ $priceLabel }}</span>

                    @if ($landing['show_strikethrough'])
                        <span class="text-base line-through" style="color: var(--text-muted)">{{ Coins::fmt($landing['total_value']) }}</span>
                        <span class="text-xs rounded-full px-2 py-0.5"
                              style="background: var(--surface-sunken); color: var(--text-muted)">
                            {{ str_replace('{percent}', (string) $landing['percent_off'], setting('store.bundle.percent_off_text', 'أقلّ بـ{percent}%')) }}
                        </span>
                    @endif
                </div>

                {{-- ============ 11) حالة المالك: «معاك بالفعل» ورابط المكتبة بدل الزرّ --}}
                <div class="flex flex-wrap items-center gap-3">
                    @if ($owned)
                        <p class="text-sm">{{ setting('store.bundle.owned_text', 'الباقة دي معاك بالفعل — كلّ عناصرها مفتوحة في مكتبتك.') }}</p>
                        @if ($libraryUrl)
                            <a href="{{ $libraryUrl }}"
                               class="btn inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                               style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('store.bundle.library_link_text', 'افتح من مكتبتي') }}</a>
                        @endif
                    @elseif (! $landing['available'])
                        {{-- 2.15-أ-7: المحظور **يُخفى لا يُعطَّل** — فلا زرّ شراءٍ ميّت هنا --}}
                        <p class="text-sm" style="color: var(--color-state-warn)">{{ $landing['unavailable_text'] }}</p>
                    @elseif (auth()->check())
                        <button type="button" data-modal-open="purchase-sheet"
                                class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $buyLabel }}</button>

                        <span class="text-sm" style="color: var(--text-muted)">
                            {{ setting('store.bundle.balance_label', 'رصيدك الآن:') }} <b>{{ Coins::label($quote['balance_before'], $quote['currency']) }}</b>
                        </span>
                    @else
                        <a href="{{ route('login') }}"
                           class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                           style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('store.bundle.login_cta', 'سجّل دخولك للشراء') }}</a>
                    @endif
                </div>

                {{--
                    صفّ ثقةٍ من **حقائق** لا وعود: عدد العناصر · عدد الدروس (إن كانت
                    البيانات موجودة) · الوصول الدائم بلا اشتراك. ولا رقم بلا مصدر (2.9).
                --}}
                <ul class="flex flex-wrap gap-x-4 gap-y-2 text-xs" style="color: var(--text-muted)">
                    @foreach ($landing['facts'] as $fact)
                        <li class="inline-flex items-center gap-1 min-w-0">
                            <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon name="check" size="14" /></span>
                            <span>{{ $fact }}</span>
                        </li>
                    @endforeach

                    @if ($landing['certificate'])
                        <li class="inline-flex items-center gap-1 min-w-0">
                            <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon name="certificate" size="14" /></span>
                            <span>{{ setting('store.bundle.fact_certificate', 'شهادة معتمدة بعد اجتياز الامتحان') }}</span>
                        </li>
                    @endif
                </ul>
            </div>

            <div class="lg:col-span-2 min-w-0 flex items-center justify-center" style="background: var(--surface-sunken); min-height: 12rem">
                @if ($cover)
                    <img src="{{ $cover }}" alt="{{ $item->name_ar }}" class="w-full h-full object-cover" loading="lazy">
                @else
                    <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon name="bundle" size="72" /></span>
                @endif
            </div>
        </div>
    </section>

    {{-- ================================================ 2) مناسبة لـ / مش مناسبة لـ --}}
    @if (setting('store.bundle.blocks.fit_enabled', true) && ($landing['fit_for'] !== [] || $landing['not_fit_for'] !== []))
        <section class="grid gap-4 md:grid-cols-2 mt-5">
            @if ($landing['fit_for'] !== [])
                <div class="card p-5 min-w-0">
                    <h2 class="font-bold mb-3 inline-flex items-center gap-2">
                        <span aria-hidden="true" style="color: var(--color-state-ok)"><x-icon name="check" size="18" /></span>
                        <span>{{ setting('store.bundle.fit_title', 'الباقة دي مناسبة لـ') }}</span>
                    </h2>
                    <ul class="space-y-2 text-sm">
                        @foreach ($landing['fit_for'] as $line)
                            <li class="flex items-start gap-2 min-w-0">
                                <span aria-hidden="true" style="color: var(--color-state-ok)"><x-icon name="check" size="16" /></span>
                                <span class="min-w-0">{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($landing['not_fit_for'] !== [])
                <div class="card p-5 min-w-0">
                    <h2 class="font-bold mb-3 inline-flex items-center gap-2">
                        <span aria-hidden="true" style="color: var(--text-muted)"><x-icon name="close" size="18" /></span>
                        <span>{{ setting('store.bundle.not_fit_title', 'ومش مناسبة لـ') }}</span>
                    </h2>
                    <ul class="space-y-2 text-sm">
                        @foreach ($landing['not_fit_for'] as $line)
                            <li class="flex items-start gap-2 min-w-0">
                                <span aria-hidden="true" style="color: var(--text-muted)"><x-icon name="close" size="16" /></span>
                                <span class="min-w-0">{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>
                    {{-- ⭐ سببُ وجود هذا البلوك أصلًا: 19.4 يمنع الاسترجاع، فالبيع لمن لا تناسبه ضررٌ لا يُصلَح --}}
                    <p class="text-xs mt-3" style="color: var(--text-muted)">{{ setting('store.bundle.not_fit_note') }}</p>
                </div>
            @endif
        </section>
    @endif

    {{-- ==================================================== 3) بعد الباقة هتقدر… --}}
    @if (setting('store.bundle.blocks.outcomes_enabled', true) && $landing['outcomes'] !== [])
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-3">{{ setting('store.bundle.outcomes_title', 'بعد الباقة هتقدر…') }}</h2>
            <ul class="grid gap-2 sm:grid-cols-2 text-sm">
                @foreach ($landing['outcomes'] as $line)
                    <li class="flex items-start gap-2 min-w-0">
                        <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon name="target" size="16" /></span>
                        <span class="min-w-0">{{ $line }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ================================================= 4) اللي جوّه الباقة + البونص --}}
    @if (setting('store.bundle.blocks.includes_enabled', true) && $landing['items']->isNotEmpty())
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-1">{{ setting('store.bundle.includes_title', 'اللي جوّه الباقة') }}</h2>
            <p class="text-xs mb-4" style="color: var(--text-muted)">
                {{ setting('store.bundle.honest_note') }}
            </p>

            <ul class="space-y-4">
                @foreach ($landing['items'] as $line)
                    <li class="min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <span class="min-w-0 flex items-start gap-2">
                                <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon name="check" size="16" /></span>
                                <span class="min-w-0">
                                    <a href="{{ route('store.product', ['type' => $line['type'], 'slug' => $line['slug']]) }}"
                                       class="text-sm font-semibold hover:underline">{{ $line['title'] }}</a>
                                    <span class="block text-xs" style="color: var(--text-muted)">{{ $typeLabels[$line['type']] ?? $line['type'] }}</span>
                                </span>
                            </span>

                            {{--
                                قاعدة السعر السياقيّ (18): Override العنصر **لا يظهر
                                إلّا هنا** — في صفحة الباقة نفسها — ومعه سعره الطبيعيّ مشطوبًا.
                            --}}
                            @if ($line['value'] > 0)
                                <span class="flex items-baseline gap-2 whitespace-nowrap">
                                    <span class="text-xs" style="color: var(--text-muted)">{{ Coins::label($line['value']) }}</span>
                                    @if ($line['has_override'] && $line['list_value'] > $line['value'])
                                        <span class="text-xs line-through" style="color: var(--text-muted)">{{ Coins::fmt($line['list_value']) }}</span>
                                    @endif
                                </span>
                            @endif
                        </div>

                        {{--
                            ⭐ البونص بقالبه **المنصوص حرفيًّا** في 18، ولعنصرٍ **وسمه
                            الأدمن بونصًا** وحده — فالبونص الذي يشمل كلّ شيء لا يعني شيئًا،
                            وهو قيمةٌ مُدرَكة منفوخة أي عين ما تمنعه 2.9.
                        --}}
                        @if ($line['is_bonus'] && $line['list_value'] > 0)
                            <p class="mt-1 text-xs" style="color: var(--color-brand-400); padding-inline-start: 1.5rem">
                                {{ $bonusLines[$line['id']] }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ============================================ 5) الشهادة — بشرطها لا بمبالغة --}}
    @if (setting('store.bundle.blocks.certificate_enabled', true) && $landing['certificate'])
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-2 inline-flex items-center gap-2">
                <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon name="certificate" size="18" /></span>
                <span>{{ setting('store.bundle.certificate_title', 'شهادة معتمدة — بشرطها') }}</span>
            </h2>

            {{-- ⭐ 8: «تُصدَر الشهادة **فقط بعد اجتياز الامتحان النهائي**» — والدرجة من الامتحان نفسه لا محروقة --}}
            <p class="text-sm leading-7" style="color: var(--text-muted)">
                {{ str_replace('{score}', (string) $landing['certificate']['pass_score'], setting('store.bundle.certificate_text')) }}
            </p>

            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($landing['certificate']['courses'] as $courseName)
                    <li class="text-xs rounded-full px-3 py-1" style="background: var(--surface-sunken)">{{ $courseName }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- =================================================== 6) ميزان القيمة المحسوب --}}
    @if (setting('store.bundle.blocks.ledger_enabled', true) && $landing['show_total_value'])
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-3">{{ setting('store.bundle.ledger_title', 'ميزان القيمة') }}</h2>

            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span style="color: var(--text-muted)">{{ setting('store.bundle.total_value_label', 'القيمة الإجماليّة') }}</span>
                    <span class="line-through" style="color: var(--text-muted)">{{ Coins::label($landing['total_value']) }}</span>
                </div>

                <div class="flex items-center justify-between gap-3 font-bold">
                    <span>{{ setting('store.bundle.price_label', 'سعر الباقة') }}</span>
                    <span>{{ Coins::label($landing['price'], $quote['currency']) }}</span>
                </div>

                <div class="flex items-center justify-between gap-3 pt-2" style="color: var(--color-state-honor); border-top: 1px solid var(--border)">
                    <span>{{ setting('store.bundle.savings_label', 'اللي بتوفّره') }}</span>
                    <span>{{ Coins::label($landing['savings'], $quote['currency']) }} ({{ $landing['percent_off'] }}%)</span>
                </div>
            </div>

            {{-- نداءٌ واحد مكرّر بعد ميزان القيمة — لا زرّ ثانويّ ينافسه بصريًّا (2.15-أ-2) --}}
            @if ($canBuy)
                <button type="button" data-modal-open="purchase-sheet"
                        class="btn mt-4 inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $buyLabel }}</button>
            @endif
        </section>
    @endif

    {{--
        ============================================ 7) الإتاحة **الحقيقيّة** وحدها
        هذا البلوك كلّه **لا يوجد في الـHTML** ما لم يضبط الأدمن تاريخ نهاية أو حدّ
        شراء. لا عدّاد بلا تاريخ · لا «باقي N» بلا حدّ · ولا رقم يُكتَب باليد (21.1-د).
    --}}
    @if (setting('store.bundle.blocks.availability_enabled', true) && ($landing['countdown_ends_at'] || $landing['seats_left'] !== null))
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-2 inline-flex items-center gap-2">
                <span aria-hidden="true" style="color: var(--color-state-warn)"><x-icon name="hourglass" size="18" /></span>
                <span>{{ setting('store.bundle.countdown_title', 'نافذة الإتاحة') }}</span>
            </h2>

            @if ($landing['countdown_ends_at'])
                {{--
                    العدّاد يقرأ **تاريخ الخادم** من `datetime`، ولا يبدأ من جديد لكلّ
                    زائر ولا يُعاد ضبطه عند التحديث — الوقت ينقص لأنّ الموعد حقيقيّ.
                --}}
                <p class="text-sm" style="color: var(--text-muted)">
                    {{ str_replace('{date}', $landing['countdown_ends_at']->translatedFormat('j F Y — H:i'), setting('store.bundle.countdown_text')) }}
                </p>

                <p class="mt-3 text-2xl font-extrabold" style="font-variant-numeric: tabular-nums"
                   data-bundle-countdown data-ends-at="{{ $landing['countdown_ends_at']->toIso8601String() }}"
                   data-units="{{ json_encode((array) setting('store.bundle.countdown_units', []), JSON_UNESCAPED_UNICODE) }}">
                    <time datetime="{{ $landing['countdown_ends_at']->toIso8601String() }}">{{ $landing['countdown_ends_at']->translatedFormat('j F Y — H:i') }}</time>
                </p>
            @endif

            @if ($landing['seats_left'] !== null)
                <p class="mt-3 text-sm">
                    <span aria-hidden="true" style="color: var(--color-state-warn)"><x-icon name="seat" size="16" /></span>
                    {{ str_replace(
                        ['{left}', '{limit}'],
                        [(string) $landing['seats_left'], (string) $item->purchase_limit],
                        setting('store.bundle.seats_text'),
                    ) }}
                </p>
            @endif
        </section>
    @endif

    {{-- ================================================= 8) الأسئلة الشائعة --}}
    @if ($landing['faq'] !== [])
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-3">{{ setting('store.bundle.faq_title', 'أسئلة قبل ما تشتري') }}</h2>

            <ul class="space-y-2">
                @foreach ($landing['faq'] as $entry)
                    <li>
                        <details class="rounded-xl px-4 py-3" style="background: var(--surface-sunken)">
                            <summary class="cursor-pointer text-sm font-semibold"
                                     style="min-height: 44px; display: flex; align-items: center">{{ $entry['q'] }}</summary>
                            <p class="mt-2 text-sm leading-7" style="color: var(--text-muted)">{{ $entry['a'] ?? '' }}</p>
                        </details>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ==================================================== 9) الإغلاق --}}
    <section class="card p-5 mt-5">
        <h2 class="font-bold mb-2">{{ setting('store.bundle.after_purchase_title', 'اللي بيحصل بعد الشراء') }}</h2>
        <p class="text-sm leading-7" style="color: var(--text-muted)">{{ setting('store.bundle.after_purchase_text') }}</p>

        {{--
            ⭐ **عوض المخاطرة بلا ضمان استرجاع** (19.4): لا «جرّبها بلا مخاطرة»، بل
            وصولٌ دائم + رابطٌ **بارز** لسياسة عدم الاسترجاع **قبل** الزرّ لا بعده —
            «فلا يُفاجَأ أحد بعد الدفع» (19.4).
        --}}
        <p class="mt-3 text-sm rounded-xl px-4 py-3" style="background: var(--surface-sunken)">
            <span aria-hidden="true" style="color: var(--color-state-warn)"><x-icon name="info" size="16" /></span>
            {{ setting('store.bundle.no_refund_notice') }}
            <a href="{{ route('store.refund-policy') }}" class="font-semibold hover:underline"
               style="color: var(--color-brand-400)">{{ setting('store.refund.link_text', 'اقرأ سياسة عدم الاسترجاع') }}</a>
        </p>

        @if ($canBuy)
            <button type="button" data-modal-open="purchase-sheet"
                    class="btn mt-4 inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $buyLabel }}</button>
        @elseif ($owned && $libraryUrl)
            <a href="{{ $libraryUrl }}"
               class="btn mt-4 inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
               style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('store.bundle.library_link_text', 'افتح من مكتبتي') }}</a>
        @endif
    </section>

    {{-- ⭐ الشراء نفسه بالبوب-أب المشترك — والسعر يُحسَب في الخادم ولا يُقبَل من الطلب --}}
    @if ($canBuy)
        @include('store.partials.purchase-sheet', ['type' => $type, 'item' => $item, 'quote' => $quote])
    @endif
@endsection

{{-- ========================== 10) الشريط اللاصق على الموبايل: السعر + الزرّ --}}
@section('mobile_action')
    @if ($landing['owned'])
        @if (\Illuminate\Support\Facades\Route::has('library.index'))
            <a href="{{ route('library.index') }}"
               class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
               style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('store.bundle.library_link_text', 'افتح من مكتبتي') }}</a>
        @endif
    @elseif ($landing['available'] && auth()->check())
        <div class="flex items-center gap-3 min-w-0">
            <span class="text-sm min-w-0">
                <span class="block text-xs" style="color: var(--text-muted)">{{ setting('store.bundle.sticky_price_label', 'سعر الباقة') }}</span>
                <b>{{ $landing['price'] > 0 ? \App\Services\Store\Coins::label($landing['price'], $quote['currency']) : setting('store.free_label', 'مجّانيّ') }}</b>
            </span>
            <button type="button" data-modal-open="purchase-sheet"
                    class="btn flex-1 inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c; min-height: 44px">
                {{ setting('store.bundle.cta_label', 'احصل على الباقة كاملة') }}
            </button>
        </div>
    @elseif ($landing['available'] && ! auth()->check())
        <a href="{{ route('login') }}"
           class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('store.bundle.login_cta', 'سجّل دخولك للشراء') }}</a>
    @endif
@endsection

@push('scripts')
    <script>
        /*
         | عدّاد **صادق** (2.9-10): يقرأ الموعد المكتوب في `data-ends-at` — وهو
         | `bundles.available_until` من الخادم — ولا يخترع مدّةً لكلّ زائر ولا
         | يُعاد ضبطه عند التحديث. وبلا موعدٍ لا يوجد العنصر في الصفحة أصلًا،
         | فالسكربت لا يجد ما يشغّله.
         */
        (function () {
            document.querySelectorAll('[data-bundle-countdown]').forEach(function (node) {
                var endsAt = Date.parse(node.dataset.endsAt);
                if (!endsAt) return;

                var units = {};
                try { units = JSON.parse(node.dataset.units || '{}'); } catch (e) { units = {}; }

                function tick() {
                    var left = endsAt - Date.now();

                    if (left <= 0) {
                        // انتهى الموعد فعلًا ⟵ العدّاد يختفي، ولا يُعاد تشغيله
                        node.remove();
                        return;
                    }

                    var s = Math.floor(left / 1000);
                    var parts = [
                        [Math.floor(s / 86400), units.days],
                        [Math.floor(s % 86400 / 3600), units.hours],
                        [Math.floor(s % 3600 / 60), units.minutes],
                        [s % 60, units.seconds],
                    ];

                    node.textContent = parts
                        .filter(function (p) { return p[1]; })
                        .map(function (p) { return p[0] + ' ' + p[1]; })
                        .join(' · ');

                    setTimeout(tick, 1000);
                }

                tick();
            });
        })();
    </script>
@endpush
