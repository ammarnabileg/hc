{{-- شريط الخطوات (2.15-ب: الفورم الطويل يتقسّم خطوات) — رقائق أفقيّة على الموبايل (2.15-ج) --}}
<nav aria-label="خطوات التنصيب" class="mb-4 -mx-1 min-w-0 overflow-x-auto">
    <ol class="flex items-center gap-2 px-1 min-w-max">
        @foreach ($steps as $step)
            <li class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs whitespace-nowrap"
                      @if ($step['state'] === 'warn') aria-current="step" @endif
                      style="background: var(--surface-sunken); border: 1px solid var(--border)">
                    <span class="inline-flex items-center justify-center rounded-full text-[11px] w-5 h-5"
                          style="background: color-mix(in srgb, var(--color-state-{{ $step['state'] === 'warn' ? 'warn' : ($step['state'] === 'ok' ? 'ok' : 'idle') }}) 18%, transparent)">
                        {{ $step['state'] === 'ok' ? '✓' : $step['number'] }}
                    </span>
                    <span @class(['font-semibold' => $step['state'] === 'warn'])>{{ $step['label'] }}</span>
                </span>
                @unless ($loop->last)
                    <span aria-hidden="true" style="color: var(--text-muted)">‹</span>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
