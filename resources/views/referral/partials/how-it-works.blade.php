@php
    /**
     * «كيف يعمل؟» بأربع خطوات (7.6.2).
     *
     * لماذا لازمة؟ لأنّ شرط صرف المكافأة ليس بديهيًّا (7.6): الدعوة لا تُحتسَب
     * بمجرّد التسجيل بل بعد **استكمال بيانات المدعوّ + اعتماد الأدمن**. وشرطٌ
     * مخفيّ يُنتِج وعدًا يبدو مخلوفًا؛ فذكرُه صراحةً هو عين منع الـDark Patterns.
     *
     * والنصوص كلّها إعدادات (2.13) — لا سطر محروق.
     */
    $percent = (string) setting('referral.commission_percent', 7);
    $steps = (array) setting('referral.how.steps', []);
    $icons = ['link', 'people', 'check', 'ticket'];
@endphp

@if ($steps)
    <section class="card p-5 mb-4">
        <h2 class="font-bold mb-4">{{ setting('referral.how.title', 'كيف يعمل؟') }}</h2>

        <ol class="grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($steps as $index => $step)
                <li class="rounded-2xl p-4" style="background: var(--surface-sunken)">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-7 h-7 inline-flex items-center justify-center rounded-full text-xs font-extrabold tabular-nums"
                              style="background: var(--color-brand-500); color: #04201c">{{ $index + 1 }}</span>
                        <span style="color: var(--color-brand-400)">
                            <x-icon :name="$icons[$index] ?? 'spark'" size="18" />
                        </span>
                    </div>

                    <p class="text-sm font-semibold">{{ $step['title'] ?? '' }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ str_replace(':percent', $percent, (string) ($step['body'] ?? '')) }}
                    </p>
                </li>
            @endforeach
        </ol>
    </section>
@endif
