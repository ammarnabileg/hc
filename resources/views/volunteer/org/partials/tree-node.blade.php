@php
    /**
     * الموبايل: قائمة شجريّة قابلة للطيّ بدل السحب (2.15-ج · 13.4-م)
     * — السحب بإصبع على شجرة كبيرة تجربة سيّئة.
     */
@endphp

<li>
    <div class="flex items-center gap-2 py-2" style="border-bottom: 1px solid var(--border)">
        <span class="org-node__avatar" aria-hidden="true">
            @if ($node['avatar'])
                <img src="{{ $node['avatar'] }}" alt="">
            @else
                {{ $node['initials'] }}
            @endif
        </span>
        <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold truncate">
                {{ $node['name'] }}
                @if ($node['honorary'])
                    <span style="color: var(--color-state-honor)">★ {{ $node['position'] }}</span>
                @elseif ($node['is_club'])
                    <span style="color: var(--color-state-honor)">★</span>
                @endif
            </div>
            <div class="text-xs" style="color: var(--text-muted)">
                @unless ($node['honorary'])
                    {{ $node['position'] }} · {{ $node['entity'] }}
                @endunless
            </div>
        </div>
        @unless ($node['honorary'])
            <x-state-badge :state="$node['rep_state']" :label="$node['rep_label']" />
        @endunless
    </div>

    @if ($node['children'])
        <details class="ms-4">
            <summary class="text-xs py-1 cursor-pointer" style="color: var(--text-muted)">
                الفريق ({{ count($node['children']) }})
            </summary>
            <ul>
                @foreach ($node['children'] as $child)
                    @include('volunteer.org.partials.tree-node', ['node' => $child])
                @endforeach
            </ul>
        </details>
    @endif
</li>
