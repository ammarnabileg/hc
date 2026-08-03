{{--
  عمود **النطاق**: عامّ ⇄ Override لدور/شريحة (24.3).
  ولا يقول «عامّ» إلّا حين لا Override فعلًا — فالشاشة تقول ما يفرضه الخادم.
--}}
@php
    $overrides = collect($row['overrides']);
    $roleCount = $overrides->where('scope_type', 'role')->count();
    $segmentCount = $overrides->where('scope_type', 'segment')->count();
@endphp

<span class="inline-flex flex-wrap items-center gap-1">
    @if ($overrides->isEmpty())
        <span style="color: var(--text-muted)">{{ setting('features.ui.scope.global', 'عامّ') }}</span>
    @else
        @if ($roleCount)
            <span class="rounded-full px-2 py-0.5"
                  style="background: var(--surface-sunken)">{{ setting('features.ui.scope.role', 'Override لدور') }} ({{ $roleCount }})</span>
        @endif
        @if ($segmentCount)
            <span class="rounded-full px-2 py-0.5"
                  style="background: var(--surface-sunken)">{{ setting('features.ui.scope.segment', 'Override لشريحة') }} ({{ $segmentCount }})</span>
        @endif
    @endif

    @if ($mayEdit)
        <button type="button" class="underline" data-feature-scope>{{ setting('features.ui.scope.manage', 'اضبط النطاق') }}</button>
    @endif
</span>
