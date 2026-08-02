@php
    /**
     * «شبكتي» عرضًا بصريًّا وهي **بتنمو** (7.6.1 · 2.9-8).
     *
     * الهدف النفسيّ المنصوص: تحويل الدعوة من «جميل» إلى **مكانة وفخر** — والشبكة
     * المرسومة تُري المستخدم ملكيّته لما بناه (Endowed Progress) بدل رقمٍ جافّ.
     *
     * 🛡️ وبلا تجميل: العقد المرسومة هي **المدعوّون الحقيقيّون** بأسمائهم وحالاتهم،
     * والعدّاد هو عدد الدعوات **المفعَّلة** فعلًا لا مجرّد التسجيلات (2.9-7).
     */
    $nodes = $invited->take((int) setting('referral.network.max_nodes', 12));
    $count = (int) ($ambassador['count'] ?? 0);
@endphp

<section class="card p-5 mb-4">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <h2 class="font-bold">{{ setting('referral.network.title', 'شبكتي') }}</h2>

        @if (($ambassador['enabled'] ?? false) && ! empty($ambassador['tier']))
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                  style="background: color-mix(in srgb, var(--color-state-honor) 16%, transparent); color: var(--color-state-honor)">
                <x-icon name="crown" size="14" />
                {{ $ambassador['tier']['label'] }}
            </span>
        @endif
    </div>

    @if ($count === 0 && $nodes->isEmpty())
        {{-- الحالة الفارغة: سطر واحد يشجّع ولا يعاتب (2.15-د · 2.17-ج) --}}
        <p class="text-sm" style="color: var(--text-muted)">{{ setting('referral.network.empty', 'شبكتك لسّه فاضية — أوّل صاحب تجيبه هيبان هنا.') }}</p>
    @else
        {{-- الرسم: أنا في النصّ والمدعوّون حولي، بـCSS خالص بلا مكتبات (2.10.1) --}}
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex flex-col items-center gap-1 shrink-0">
                <x-avatar :user="auth()->user()" size="14" />
                <span class="text-[11px]" style="color: var(--color-brand-400)">{{ setting('referral.network.me', 'إنت') }}</span>
            </span>

            <span aria-hidden="true" class="flex-1 min-w-8 h-px"
                  style="background: repeating-linear-gradient(90deg, var(--border) 0 6px, transparent 6px 12px)"></span>

            <ul class="flex flex-wrap gap-3">
                @foreach ($nodes as $referral)
                    @php $state = $service->statusOf($referral); @endphp
                    <li class="inline-flex flex-col items-center gap-1 w-16"
                        title="{{ $referral->referred?->name }} — {{ $state['label'] }}">
                        <span class="rounded-full p-0.5"
                              style="outline: 2px solid var(--color-state-{{ state_color($state['state'])['color'] }})">
                            <x-avatar :user="$referral->referred" size="10" />
                        </span>
                        <span class="text-[11px] truncate w-full text-center" style="color: var(--text-muted)">
                            {{ $referral->referred?->shortName(1) ?? setting('referral.network.unknown', 'ضيف') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- تقدّم اللقب: يبدأ من العتبة السابقة لا من صفر — تقدّم مُهدى (2.9-2) --}}
        @if (($ambassador['enabled'] ?? false) && ! empty($ambassador['next']))
            <div class="mt-4">
                <div class="flex items-center justify-between text-xs mb-1" style="color: var(--text-muted)">
                    <span>{{ setting('referral.network.next_prefix', 'باقي') }}
                        {{ $ambassador['next']['remaining'] }}
                        {{ setting('referral.network.next_suffix', 'دعوة مفعَّلة للّقب التالي') }}:
                        <strong style="color: var(--text)">{{ $ambassador['next']['label'] }}</strong></span>
                    <span class="tabular-nums">{{ $count }}/{{ $ambassador['next']['threshold'] }}</span>
                </div>
                <span class="block h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                    <span class="block h-full rounded-full motion-standard"
                          style="width: {{ $ambassador['percent'] }}%; background: var(--color-state-honor)"></span>
                </span>
            </div>
        @endif
    @endif
</section>
