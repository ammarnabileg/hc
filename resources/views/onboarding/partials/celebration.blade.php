@php
    /**
     * احتفال رحلة التسجيل (2.14): ثلاثة مستويات لا رابع —
     * 1 خفيف بلا صوت · 2 كونفيتي خفيف · 3 شاشة كاملة.
     *
     * CSS-first بلا أصول جديدة ولا مكتبات (2.14-ب). **الصوت وحده** يخضع لتوجل
     * المستخدم، و«**الأنيميشن حاضر دائمًا**» (2.3 · 2.14-ب) — ولا توجّل يطفئه
     * ولا `prefers-reduced-motion` (مرفوض بالاسم في 2.3).
     *
     * والكونفيتي من مصدرٍ واحد `<x-confetti>` — عددًا ومدّةً وشدّةً من
     * `setting()` (2.13 · 2.14-ب «مصدر واحد للاحتفالات … لا نسخ متعدّدة»).
     */
    $tier = (int) ($celebration['tier'] ?? 3);
    $confetti = match ($tier) {
        3 => 'peak',
        2 => 'light',
        default => null,
    };
@endphp

@if ($confetti)
    <x-confetti :variant="$confetti" />
@endif

@if (! empty($celebration['sound']) && ! empty($celebration['sound_path']))
    <audio autoplay src="{{ \Illuminate\Support\Facades\Storage::url($celebration['sound_path']) }}"></audio>
@endif
