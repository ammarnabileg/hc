{{--
    ⭐ **لاندنج بيدج البندل** (18 — البند الذي كان «🕒 قيد التفصيل لاحقًا» في القسم 22،
    وأمر المالك ببنائه).

    ⚠️ **قاعدة هذا الملفّ الوحيدة: لا `setting()` هنا ولا حساب.** كلّ نصّ يأتي من
    `$landing['texts'][…]` وكلّ سكشن من `$landing['sections'][…]` — ومصدرهما
    `BundleLanding` بقاعدة وراثةٍ واحدة (قيمة البندل ⟵ الإعداد العامّ). ولو قرأ
    سطرٌ هنا إعدادًا مباشرةً لصار ذلك النصّ **غير قابل للتعديل من إعدادات البندل**،
    وهو عين ما أمر المالك بمنعه — ويحرسه `BundleLandingInheritanceTest`.

    وثلاثة أشياء **غائبةٌ هنا عمدًا**، ولكلٍّ نصُّه:
      · **لا شهادات عملاء ولا آراء** — لا جدول لها في المنصّة، ورأيٌ مفبرك خرقٌ
        صريح لـ«لا أرقام وهمية» (2.9 · 21.1-د).
      · **لا «ضمان استرجاع» ولا «جرّبها بلا مخاطرة»** — 19.4 قاعدةٌ نهائيّة: «لا
        استرجاع نقديّ لأيّ مدفوعات». والبديل الأمين: **وصولٌ دائم** + رابط السياسة
        **قبل** الزرّ.
      · **لا عدّاد ولا مقاعد بلا بيانات حقيقيّة** — لا يُطبَع في الـHTML شيءٌ منهما
        ما لم يضبط الأدمن `available_until` أو `purchase_limit`.
--}}
@extends('layouts.app')

@use('App\Services\Store\Coins')

@php
    $t = $landing['texts'];
    $show = $landing['sections'];
    // فتات الخبز ووسم الملكيّة — آخر نصّين كانا محروقين في هذا القالب (2.13)
    $crumbs = $landing['breadcrumbs'];
@endphp

@section('title', $t['hero.headline'])
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $t['hero.promise']), 155))

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

    {{--
        ⭐ **الكود المخصّص في `<head>`** — العامّ أوّلًا ثمّ الخاصّ بالبندل.
        ⚠️ يُطبَع **خامًّا** (`{!! !!}`) عن قصد: أمرُ المالك «مسموح أضيف فيهم أي
        حاجة»، والهروب هنا يحوّل الوسم إلى نصٍّ ظاهر فيُبطِل الحقل كلّه. ولذلك
        بالضبط حصرناه بيد **مالك المنصّة وحده** على الخادم، ووراء **بوّابة موافقة**
        (`BundleLanding::mayInject`) — فما وصل هنا فقد اجتاز الاثنين معًا.
    --}}
    @foreach ($injections['head'] as $snippet)
        {!! $snippet !!}
    @endforeach
@endpush

@section('content')
    @php
        $owned = $landing['owned'];
        $canBuy = auth()->check() && ! $owned && $landing['available'];
        $cover = $item->cover_path ? \Illuminate\Support\Facades\Storage::url($item->cover_path) : null;
        $libraryUrl = \Illuminate\Support\Facades\Route::has('library.index') ? route('library.index') : null;
        // ⭐ نداءٌ واحد للفعل بنصٍّ واحد، يتكرّر ولا يُنافَس (2.15-أ-2)
        $buyLabel = $t['hero.cta'];
        $priceLabel = $landing['price'] > 0 ? Coins::label($landing['price'], $quote['currency']) : $t['hero.free_label'];
    @endphp

    <x-page-header :title="$item->name_ar"
                   :breadcrumbs="[
                       ['label' => $crumbs['store'], 'url' => route('store.index')],
                       ['label' => $crumbs['bundles'], 'url' => route('store.bundles')],
                       ['label' => $item->name_ar],
                   ]" />

    @if (session('status'))
        <x-toast :message="session('status')" />
    @endif

    @error('checkout')
        <x-toast :message="$message" state="danger" />
    @enderror

    {{-- ============================================================ 1) الهيرو --}}
    @if ($show['hero'])
        <section class="card overflow-hidden">
            <div class="grid gap-0 lg:grid-cols-5">
                <div class="lg:col-span-3 min-w-0 p-5 sm:p-7 flex flex-col gap-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs rounded-full px-3 py-1 inline-flex items-center gap-1"
                              style="background: color-mix(in srgb, var(--color-brand-500) 15%, transparent); color: var(--color-brand-400)">
                            <x-icon name="bundle" size="14" />
                            <span>{{ $t['hero.badge'] }}</span>
                        </span>

                        @if ($owned)
                            <x-state-badge state="ok" :label="$t['hero.owned_badge']" />
                        @endif

                        {{--
                            ⭐ «وفّرت X» **محسوبة لا مكتوبة** (21.1-د)، ولا تُطبَع إطلاقًا
                            إن كانت القيمة الإجماليّة ≤ سعر الباقة — لا صفرًا ولا سالبًا.
                        --}}
                        @if ($landing['has_savings'])
                            <span class="text-xs rounded-full px-3 py-1 inline-flex items-center gap-1"
                                  style="background: color-mix(in srgb, var(--color-state-honor) 18%, transparent); color: var(--color-state-honor)">
                                <x-icon name="balance-scale" size="14" />
                                <span>{{ str_replace('{amount}', Coins::label($landing['savings'], $quote['currency']), $t['hero.savings']) }}</span>
                            </span>
                        @endif
                    </div>

                    {{-- ⭐ العنوان يبيع **النتيجة**؛ وإن لم يكتبه الأدمن رجع لاسم الباقة بلا فراغ --}}
                    <h1 class="text-2xl sm:text-3xl font-extrabold leading-9">{{ $t['hero.headline'] }}</h1>

                    @if ($t['hero.promise'] !== '')
                        <p class="text-sm leading-7" style="color: var(--text-muted)">{{ $t['hero.promise'] }}</p>
                    @endif

                    {{-- كتلة السعر: مشطوب + سعر الباقة + «وفّرت» المحسوبة (Anchoring — 18) --}}
                    <div class="flex items-baseline gap-3 flex-wrap">
                        <span class="text-3xl font-extrabold">{{ $priceLabel }}</span>

                        @if ($landing['show_strikethrough'])
                            <span class="text-base line-through" style="color: var(--text-muted)">{{ Coins::fmt($landing['total_value']) }}</span>
                            <span class="text-xs rounded-full px-2 py-0.5"
                                  style="background: var(--surface-sunken); color: var(--text-muted)">
                                {{ str_replace('{percent}', (string) $landing['percent_off'], $t['hero.percent_off']) }}
                            </span>
                        @endif
                    </div>

                    {{-- ============ 11) حالة المالك: «معاك بالفعل» ورابط المكتبة بدل الزرّ --}}
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($owned)
                            <p class="text-sm">{{ $t['hero.owned_text'] }}</p>
                            @if ($libraryUrl)
                                <a href="{{ $libraryUrl }}"
                                   class="btn inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                                   style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $t['hero.library_link'] }}</a>
                            @endif
                        @elseif (! $landing['available'])
                            {{-- 2.15-أ-7: المحظور **يُخفى لا يُعطَّل** — فلا زرّ شراءٍ ميّت هنا --}}
                            <p class="text-sm" style="color: var(--color-state-warn)">{{ $landing['unavailable_text'] }}</p>
                        @elseif (auth()->check())
                            <button type="button" data-modal-open="purchase-sheet"
                                    class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $buyLabel }}</button>

                            <span class="text-sm" style="color: var(--text-muted)">
                                {{ $t['hero.balance_label'] }} <b>{{ Coins::label($quote['balance_before'], $quote['currency']) }}</b>
                            </span>
                        @else
                            <a href="{{ route('login') }}"
                               class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                               style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $t['hero.login_cta'] }}</a>
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
                                <span>{{ $t['hero.fact_certificate'] }}</span>
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
    @endif

    {{-- ================================================ 2) مناسبة لـ / مش مناسبة لـ --}}
    @if ($show['fit'] && ($landing['fit_for'] !== [] || $landing['not_fit_for'] !== []))
        <section class="grid gap-4 md:grid-cols-2 mt-5">
            @if ($landing['fit_for'] !== [])
                <div class="card p-5 min-w-0">
                    <h2 class="font-bold mb-3 inline-flex items-center gap-2">
                        <span aria-hidden="true" style="color: var(--color-state-ok)"><x-icon name="check" size="18" /></span>
                        <span>{{ $t['fit.title'] }}</span>
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
                        <span>{{ $t['fit.not_title'] }}</span>
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
                    <p class="text-xs mt-3" style="color: var(--text-muted)">{{ $t['fit.note'] }}</p>
                </div>
            @endif
        </section>
    @endif

    {{-- ==================================================== 3) بعد الباقة هتقدر… --}}
    @if ($show['outcomes'] && $landing['outcomes'] !== [])
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-3">{{ $t['outcomes.title'] }}</h2>
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
    @if ($show['includes'] && $landing['items']->isNotEmpty())
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-1">{{ $t['includes.title'] }}</h2>
            <p class="text-xs mb-4" style="color: var(--text-muted)">{{ $t['includes.honest_note'] }}</p>

            <ul class="space-y-4">
                @foreach ($landing['items'] as $line)
                    <li class="min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <span class="min-w-0 flex items-start gap-2">
                                <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon name="check" size="16" /></span>
                                <span class="min-w-0">
                                    <a href="{{ route('store.product', ['type' => $line['type'], 'slug' => $line['slug']]) }}"
                                       class="text-sm font-semibold hover:underline">{{ $line['title'] }}</a>
                                    <span class="block text-xs" style="color: var(--text-muted)">{{ $landing['item_type_labels'][$line['type']] ?? $line['type'] }}</span>
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
                                {{ $landing['bonus_lines'][$line['id']] }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ============================================ 5) الشهادة — بشرطها لا بمبالغة --}}
    @if ($show['certificate'] && $landing['certificate'])
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-2 inline-flex items-center gap-2">
                <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon name="certificate" size="18" /></span>
                <span>{{ $t['certificate.title'] }}</span>
            </h2>

            {{-- ⭐ 8: «تُصدَر الشهادة **فقط بعد اجتياز الامتحان النهائي**» — والدرجة من الامتحان نفسه لا محروقة --}}
            <p class="text-sm leading-7" style="color: var(--text-muted)">
                {{ str_replace('{score}', (string) $landing['certificate']['pass_score'], $t['certificate.text']) }}
            </p>

            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($landing['certificate']['courses'] as $courseName)
                    <li class="text-xs rounded-full px-3 py-1" style="background: var(--surface-sunken)">{{ $courseName }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- =================================================== 6) ميزان القيمة المحسوب --}}
    @if ($show['ledger'] && $landing['show_total_value'])
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-3">{{ $t['ledger.title'] }}</h2>

            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span style="color: var(--text-muted)">{{ $t['ledger.total_value_label'] }}</span>
                    <span class="line-through" style="color: var(--text-muted)">{{ Coins::label($landing['total_value']) }}</span>
                </div>

                <div class="flex items-center justify-between gap-3 font-bold">
                    <span>{{ $t['ledger.price_label'] }}</span>
                    <span>{{ Coins::label($landing['price'], $quote['currency']) }}</span>
                </div>

                <div class="flex items-center justify-between gap-3 pt-2" style="color: var(--color-state-honor); border-top: 1px solid var(--border)">
                    <span>{{ $t['ledger.savings_label'] }}</span>
                    <span>{{ Coins::label($landing['savings'], $quote['currency']) }} ({{ $landing['percent_off'] }}%)</span>
                </div>
            </div>

            {{-- نداءٌ واحد مكرّر بعد ميزان القيمة — ولا زرّ ثانويّ ينافسه بصريًّا (2.15-أ-2) --}}
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
    @if ($show['availability'] && ($landing['countdown_ends_at'] || $landing['seats_left'] !== null))
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-2 inline-flex items-center gap-2">
                <span aria-hidden="true" style="color: var(--color-state-warn)"><x-icon name="hourglass" size="18" /></span>
                <span>{{ $t['availability.title'] }}</span>
            </h2>

            @if ($landing['countdown_ends_at'])
                {{--
                    العدّاد يقرأ **تاريخ الخادم**، ولا يبدأ من جديد لكلّ زائر ولا
                    يُعاد ضبطه عند التحديث — الوقت ينقص لأنّ الموعد حقيقيّ (2.9-10).
                --}}
                <p class="text-sm" style="color: var(--text-muted)">
                    {{ str_replace('{date}', $landing['countdown_ends_at']->translatedFormat('j F Y — H:i'), $t['availability.countdown_text']) }}
                </p>

                <p class="mt-3 text-2xl font-extrabold" style="font-variant-numeric: tabular-nums"
                   data-bundle-countdown data-ends-at="{{ $landing['countdown_ends_at']->toIso8601String() }}"
                   data-units="{{ json_encode($landing['countdown_units'], JSON_UNESCAPED_UNICODE) }}">
                    <time datetime="{{ $landing['countdown_ends_at']->toIso8601String() }}">{{ $landing['countdown_ends_at']->translatedFormat('j F Y — H:i') }}</time>
                </p>
            @endif

            @if ($landing['seats_left'] !== null)
                <p class="mt-3 text-sm">
                    <span aria-hidden="true" style="color: var(--color-state-warn)"><x-icon name="seat" size="16" /></span>
                    {{ str_replace(
                        ['{left}', '{limit}'],
                        [(string) $landing['seats_left'], (string) $item->purchase_limit],
                        $t['availability.seats_text'],
                    ) }}
                </p>
            @endif
        </section>
    @endif

    {{-- ================================================= 8) الأسئلة الشائعة --}}
    @if ($show['faq'] && $landing['faq'] !== [])
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-3">{{ $t['faq.title'] }}</h2>

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
    @if ($show['closing'])
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-2">{{ $t['closing.title'] }}</h2>
            <p class="text-sm leading-7" style="color: var(--text-muted)">{{ $t['closing.text'] }}</p>

            {{--
                ⭐ **عوض المخاطرة بلا ضمان استرجاع** (19.4): لا «جرّبها بلا مخاطرة»، بل
                وصولٌ دائم + رابطٌ **بارز** لسياسة عدم الاسترجاع **قبل** الزرّ لا بعده —
                «فلا يُفاجَأ أحد بعد الدفع» (19.4).
            --}}
            <p class="mt-3 text-sm rounded-xl px-4 py-3" style="background: var(--surface-sunken)">
                <span aria-hidden="true" style="color: var(--color-state-warn)"><x-icon name="info" size="16" /></span>
                {{ $t['closing.no_refund_notice'] }}
                <a href="{{ route('store.refund-policy') }}" class="font-semibold hover:underline"
                   style="color: var(--color-brand-400)">{{ $t['closing.refund_link'] }}</a>
            </p>

            @if ($canBuy)
                <button type="button" data-modal-open="purchase-sheet"
                        class="btn mt-4 inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $buyLabel }}</button>
            @elseif ($owned && $libraryUrl)
                <a href="{{ $libraryUrl }}"
                   class="btn mt-4 inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $t['hero.library_link'] }}</a>
            @endif
        </section>
    @endif

    {{-- ⭐ الشراء نفسه بالبوب-أب المشترك — والسعر يُحسَب في الخادم ولا يُقبَل من الطلب --}}
    @if ($canBuy)
        @include('store.partials.purchase-sheet', ['type' => $type, 'item' => $item, 'quote' => $quote])
    @endif
@endsection

{{-- ========================== 10) الشريط اللاصق على الموبايل: السعر + الزرّ --}}
@section('mobile_action')
    @if ($landing['sections']['sticky'])
        @php $st = $landing['texts']; @endphp

        @if ($landing['owned'])
            @if (\Illuminate\Support\Facades\Route::has('library.index'))
                <a href="{{ route('library.index') }}"
                   class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
                   style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $st['hero.library_link'] }}</a>
            @endif
        @elseif ($landing['available'] && auth()->check())
            <div class="flex items-center gap-3 min-w-0">
                <span class="text-sm min-w-0">
                    <span class="block text-xs" style="color: var(--text-muted)">{{ $st['sticky.price_label'] }}</span>
                    <b>{{ $landing['price'] > 0 ? \App\Services\Store\Coins::label($landing['price'], $quote['currency']) : $st['hero.free_label'] }}</b>
                </span>
                <button type="button" data-modal-open="purchase-sheet"
                        class="btn flex-1 inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $st['hero.cta'] }}</button>
            </div>
        @elseif ($landing['available'])
            <a href="{{ route('login') }}"
               class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
               style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $st['hero.login_cta'] }}</a>
        @endif
    @endif
@endsection

@if ($landing['countdown_ends_at'])
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
@endif

@push('scripts')
    {{--
        ⭐ **الكود المخصّص قبل `</body>` مباشرةً** — و`@stack('scripts')` آخر ما في
        الصفحة قبل الوسم الخاتم، وهذه الدفعة آخر دفعةٍ في الملفّ فتخرج أخيرًا.
        العامّ أوّلًا ثمّ الخاصّ، وكلاهما **خامٌّ بلا تعقيم** (أمر المالك) بعد
        اجتياز حارس المالك على الخادم **وبوّابة الموافقة** (21.3-د · 2.9).
    --}}
    @foreach ($injections['body_end'] as $snippet)
        {!! $snippet !!}
    @endforeach
@endpush
