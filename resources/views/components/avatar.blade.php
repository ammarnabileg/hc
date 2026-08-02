@props(['user' => null, 'size' => '10', 'name' => null])

@php
    $label = $name ?? ($user?->name ?? '');
    $initials = collect(preg_split('/\s+/u', trim($label)) ?: [])
        ->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    $src = $user?->avatar_path ? \Illuminate\Support\Facades\Storage::url($user->avatar_path) : null;
@endphp

{{-- الأفاتار بلا هالة — قاعدة صريحة في نظام التصميم (2.10.1) --}}
<span class="avatar inline-flex items-center justify-center rounded-full overflow-hidden shrink-0 font-semibold"
      style="width: {{ (int) $size * 0.25 }}rem; height: {{ (int) $size * 0.25 }}rem;
             background: var(--surface-sunken); color: var(--text-muted); font-size: {{ (int) $size * 0.1 }}rem">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $label }}" class="w-full h-full object-cover">
    @else
        {{ $initials }}
    @endif
</span>
