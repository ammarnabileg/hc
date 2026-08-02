@props(['user' => null, 'size' => '10', 'name' => null])

@php
    use App\Services\Images\AvatarProcessor;

    $label = $name ?? ($user?->name ?? '');
    $initials = collect(preg_split('/\s+/u', trim($label)) ?: [])
        ->take((int) setting('ux.avatar.initials_count', 2))->map(fn ($w) => mb_substr($w, 0, 1))->implode('');

    // المقاس المعروض بالبكسل = وحدات Tailwind × 4 — والشاشات عالية الكثافة تحتاج الضعف
    $renderedPx = (int) $size * 4 * 2;

    // ⭐ المقاس المناسب لكلّ سياق من النسخ الثلاث (500 · 150 · 50) — 2.7
    $path = $user ? app(AvatarProcessor::class)->pick($user, $renderedPx) : null;
    $src = $path ? \Illuminate\Support\Facades\Storage::url($path) : null;
@endphp

{{-- الأفاتار بلا هالة — قاعدة صريحة في نظام التصميم (2.10.1) --}}
<span class="avatar inline-flex items-center justify-center rounded-full overflow-hidden shrink-0 font-semibold"
      style="width: {{ (int) $size * 0.25 }}rem; height: {{ (int) $size * 0.25 }}rem;
             background: var(--surface-sunken); color: var(--text-muted); font-size: {{ (int) $size * 0.1 }}rem">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $label }}" loading="lazy" decoding="async" class="w-full h-full object-cover">
    @else
        {{ $initials }}
    @endif
</span>
