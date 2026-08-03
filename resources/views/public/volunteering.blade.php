@extends('layouts.app')
@section('title', $heroTitle)
@section('meta_description', $heroBody)

@section('content')
    {{--
      صفحة «تطوّع معنا» (13.4-أ) + شاشة حالة المتقدّم (13.4-ج).
      كلّ نصٍّ هنا مصدرُه `volunteer_page.*` أو `volunteer.journey.*` — **ما يكتبه
      الأدمن هو ما يُعرَض**، ولا نصّ محروق في الصفحة (2.13).
    --}}
    <x-page-header :title="$heroTitle" :subtitle="$heroBody" />

    @if ($heroMedia)
        <figure class="card overflow-hidden mb-4">
            <img src="{{ $heroMedia }}" alt="{{ $heroTitle }}" loading="lazy" class="w-full h-auto" style="max-width: 100%">
        </figure>
    @endif

    {{-- ⭐ إحصائيّات حيّة + إبراز الأثر (13.4-أ) — أرقام حقيقيّة والإزاحة إعداد --}}
    @if ($stats['enabled'])
        <section class="grid grid-cols-2 gap-3 mb-4">
            <x-kpi :label="$stats['volunteers_label']" :value="$stats['volunteers']" icon="contribution" />
            <x-kpi :label="$stats['trainees_label']" :value="$stats['trainees']" icon="training" />
        </section>

        @if ($impactLine)
            <p class="card p-4 mb-4 text-sm text-center">{{ $impactLine }}</p>
        @endif
    @endif

    {{-- ⭐ شريط التقدّم الدائم + بطاقة «حالتي» (13.4-ج) --}}
    @if ($candidate || $status['progress']['percent'] > 0)
        <section class="card p-4 md:p-5 mb-4" aria-label="{{ $statusTitle }}">
            <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                <h2 class="font-bold">{{ $statusTitle }}</h2>
                <x-state-badge :state="$status['status_state']" :label="$status['status_label']" />
            </div>

            {{-- Stepper: على الموبايل رقائق أفقيّة قابلة للتمرير بلا تمرير للصفحة (2.15-ج) --}}
            <ol class="flex gap-2 min-w-0 overflow-x-auto pb-1" style="scrollbar-width: thin">
                @foreach ($status['steps'] as $step)
                    <li class="shrink-0 rounded-full px-3 py-2 text-xs flex items-center gap-1"
                        @if ($step['current']) aria-current="step" @endif
                        style="min-height: 44px;
                               background: {{ $step['done'] ? 'color-mix(in srgb, var(--color-state-ok) 15%, transparent)' : 'var(--surface-sunken)' }};
                               color: {{ $step['done'] ? 'var(--color-state-ok)' : 'var(--text-muted)' }};
                               {{ $step['current'] ? 'outline: 2px solid var(--color-brand-500)' : '' }}">
                        @if ($step['done'])
                            <x-icon name="check" size="14" />
                        @endif
                        <span>{{ $step['label'] }}</span>
                    </li>
                @endforeach
            </ol>

            <p class="mt-3 text-sm">{{ $status['next'] }}</p>

            @if (! $candidate && $status['progress']['total'] > 0)
                {{-- نسبة التأهيليّ («باقي القليل» — 13.4-ب) --}}
                <div class="mt-3">
                    <div class="flex items-center justify-between text-xs mb-1" style="color: var(--text-muted)">
                        <span>تقدّمك في المسار التأهيليّ</span>
                        <span>{{ $status['progress']['percent'] }}%</span>
                    </div>
                    <div class="h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                        <div class="h-2 rounded-full" style="width: {{ $status['progress']['percent'] }}%; background: var(--color-brand-500)"></div>
                    </div>
                </div>
            @endif

            {{-- ⭐ رابط المقابلة + عدّاد للموعد (13.4-ج) --}}
            @if ($status['interview'])
                <div class="mt-4 rounded-xl p-3" style="background: var(--surface-sunken)">
                    <h3 class="text-sm font-bold">{{ $interviewTitle }}</h3>
                    <p class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ $status['interview']->scheduled_at?->translatedFormat('l j F Y — H:i') }}
                    </p>
                    <p class="mt-1 text-sm font-semibold"
                       data-countdown-to="{{ $status['interview']->scheduled_at?->toIso8601String() }}">
                        {{ $status['interview']->scheduled_at?->diffForHumans() }}
                    </p>

                    @if ($status['interview']->external_link)
                        <a href="{{ $status['interview']->external_link }}" target="_blank" rel="noopener"
                           class="btn inline-flex items-center justify-center mt-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                           style="background: var(--color-brand-500); color: #04201c">{{ $interviewCta }}</a>
                    @else
                        <p class="mt-2 text-xs" style="color: var(--text-muted)">{{ $interviewNoLink }}</p>
                    @endif
                </div>
            @endif

            {{-- ⭐ طلب التسكين: لازم يدخل يوافق أو يرفض داخل المهلة (13.4-هـ) --}}
            @if ($status['placement'])
                <div class="mt-4 rounded-xl p-3" style="background: color-mix(in srgb, var(--color-brand-500) 10%, transparent)">
                    <h3 class="text-sm font-bold">وصلك طلب تسكين</h3>
                    <p class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ $status['placement']->entity?->name_ar }} · {{ $status['placement']->position?->name_ar }}
                        — الردّ خلال {{ $responseHours }} ساعة.
                    </p>

                    <div class="mt-2 flex flex-wrap gap-2">
                        <form method="post" action="{{ route('volunteering.placement.respond', $status['placement']) }}">
                            @csrf
                            <input type="hidden" name="decision" value="accepted">
                            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c; min-height: 44px">أوافق</button>
                        </form>
                        <form method="post" action="{{ route('volunteering.placement.respond', $status['placement']) }}">
                            @csrf
                            <input type="hidden" name="decision" value="rejected">
                            <button type="submit" class="rounded-xl px-4 py-2 text-sm motion-standard"
                                    style="background: var(--surface-sunken); min-height: 44px">مش دلوقتي</button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- ⭐ «جدّد استعدادك» بمهلة تبريد تمنع التكرار المتلاحق (13.4-هـ) --}}
            @if ($candidate)
                <div class="mt-4 pt-3" style="border-top: 1px solid var(--border)">
                    <p class="text-xs mb-2" style="color: var(--text-muted)">{{ $renewHint }}</p>
                    @if ($canRenew)
                        <form method="post" action="{{ route('volunteering.renew') }}">
                            @csrf
                            <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--surface-sunken); min-height: 44px">{{ $renewCta }}</button>
                        </form>
                    @elseif ($renewAt)
                        <p class="text-xs" style="color: var(--text-muted)">
                            التجديد الجاي متاح يوم {{ $renewAt->translatedFormat('j F Y') }}.
                        </p>
                    @endif
                </div>
            @endif
        </section>
    @endif

    {{-- التبريد بعد الخروج والإقصاء يسبقان كلّ شيء (13.4-س · ق) --}}
    @if ($gate['excluded'])
        <x-empty
            :message="$gate['message']"
            action="صفحة الدعم"
            :href="\Illuminate\Support\Facades\Route::has('complaints.index') ? route('complaints.index') : '#'" />
    @elseif ($gate['until'])
        <div class="card p-6 text-center mb-4">
            <p class="text-sm">{{ $gate['message'] }}</p>
        </div>
    @else
        {{-- ⭐ ميثاق المتطوّع: الموافقة عليه **قبل** بدء التأهيليّ (13.4-أ) --}}
        @if (! $charterAccepted && $charterText)
            <section class="card p-5 mb-4" id="charter">
                <h2 class="font-bold mb-2">{{ $charterTitle }}</h2>
                <div class="text-sm leading-relaxed" style="color: var(--text-muted)">{!! nl2br(e($charterText)) !!}</div>

                <form method="post" action="{{ route('volunteering.charter') }}" class="mt-4">
                    @csrf
                    <label class="flex items-start gap-2 text-sm" style="min-height: 44px">
                        <input type="checkbox" name="agree" value="1" required class="mt-1">
                        <span>{{ $charterAgreeLabel }}</span>
                    </label>
                    <button type="submit"
                            class="btn inline-flex items-center justify-center mt-3 rounded-xl px-6 py-3 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ $charterAgreeLabel }}</button>
                </form>
            </section>
        @endif

        {{--
          فعل رئيسيّ واحد بارز (2.15-أ-2) — ووجهته **المسار التأهيليّ نفسه**
          لا قائمة المسارات العامّة. والزرّ **ظاهرٌ دائمًا** (13.4-أ: «CTA بارز
          متكرّر»)، لكنّه قبل الموافقة على الميثاق ينقل إلى الميثاق لا إلى
          المسار — فالبوّابة محفوظة والتوجيه واضح بلا حائطٍ صامت.
        --}}
        <div class="card p-6 text-center mb-6">
            @if ($canEnterPipeline && $charterAccepted)
                <form method="post" action="{{ route('volunteering.next') }}">
                    @csrf
                    <button type="submit"
                            class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ $nextCta }}</button>
                </form>
            @elseif (! $candidate)
                @php
                    $ctaHref = ! $charterAccepted && $charterText
                        ? '#charter'
                        : ($qualifyingPath && \Illuminate\Support\Facades\Route::has('learning.path')
                            ? route('learning.path', $qualifyingPath->slug)
                            : (\Illuminate\Support\Facades\Route::has('learning.paths') ? route('learning.paths') : '#'));
                @endphp
                <a href="{{ $ctaHref }}"
                   class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ $ctaLabel }}</a>

                @if (! $charterAccepted && $charterText)
                    <p class="mt-2 text-xs" style="color: var(--text-muted)">{{ $gate['message'] }}</p>
                @endif
            @endif
        </div>
    @endif

    {{-- ⭐ الأسئلة الشائعة بأكورديون (13.4-أ) --}}
    @if ($blocks['faq']->isNotEmpty())
        <section class="mb-4">
            <h2 class="font-bold mb-2">{{ $faqTitle }}</h2>
            <div class="space-y-2">
                @foreach ($blocks['faq'] as $item)
                    <details class="card p-4">
                        <summary class="cursor-pointer font-semibold select-none" style="min-height: 44px">{{ $item['title'] }}</summary>
                        <div class="mt-2 text-sm leading-relaxed" style="color: var(--text-muted)">{!! nl2br(e($item['body'])) !!}</div>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ⭐ قصص المتطوّعين كروت (13.4-أ) --}}
    @if ($blocks['story']->isNotEmpty())
        <section class="mb-4">
            <h2 class="font-bold mb-2">{{ $storiesTitle }}</h2>
            <div class="grid md:grid-cols-2 gap-3">
                @foreach ($blocks['story'] as $item)
                    <article class="card p-4">
                        <h3 class="font-semibold mb-1">{{ $item['title'] }}</h3>
                        <p class="text-sm leading-relaxed" style="color: var(--text-muted)">{!! nl2br(e($item['body'])) !!}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- إبراز الأثر والأقسام الحرّة --}}
    @foreach (['impact', 'section'] as $group)
        @foreach ($blocks[$group] as $item)
            <section class="card p-5 mb-4">
                <h2 class="font-bold mb-2">{{ $item['title'] }}</h2>
                <div class="text-sm leading-relaxed" style="color: var(--text-muted)">{!! nl2br(e($item['body'])) !!}</div>
            </section>
        @endforeach
    @endforeach

    {{-- سطر شرفيّ اختياريّ (13.4-ص-ب) — بلا أيّ مؤشّر تشغيليّ --}}
    @if ($honorary)
        <section class="card p-4 mb-4 flex items-center gap-3"
                 style="{{ $honorary['frame'] === 'gold' ? 'border-color: var(--color-state-honor)' : '' }}
                        {{ $honorary['frame'] === 'dashed' ? 'border-style: dashed' : '' }}">
            <x-avatar :user="$honorary['user']" size="10" />
            <div class="min-w-0">
                <div class="font-semibold truncate">{{ $honorary['user']->name }}</div>
                <div class="text-xs" style="color: var(--text-muted)">{{ $honorary['label'] }}</div>
            </div>
        </section>
    @endif

    @unless ($hasContent)
        <x-empty :message="$emptyMessage" />
    @endunless
@endsection

@section('mobile_action')
    @if (! $gate['excluded'] && ! $gate['until'] && $canEnterPipeline)
        <form method="post" action="{{ route('volunteering.next') }}">
            @csrf
            <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ $nextCta }}</button>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        // عدّاد نازل لموعد المقابلة — والرقم النهائيّ ظاهر في كلّ الأحوال (2.17-أ)
        document.querySelectorAll('[data-countdown-to]').forEach((el) => {
            const target = new Date(el.dataset.countdownTo).getTime();

            if (Number.isNaN(target)) {
                return;
            }

            const tick = () => {
                const left = target - Date.now();

                if (left <= 0) {
                    el.textContent = 'الموعد حالًا';

                    return;
                }

                const d = Math.floor(left / 86400000);
                const h = Math.floor((left % 86400000) / 3600000);
                const m = Math.floor((left % 3600000) / 60000);
                el.textContent = (d ? d + ' يوم و' : '') + h + ' ساعة و' + m + ' دقيقة';
            };

            tick();
            setInterval(tick, 30000);
        });
    </script>
@endpush
