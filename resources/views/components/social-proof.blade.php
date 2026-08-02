@props(['context' => 'lesson', 'count' => 0, 'icon' => 'people'])

@php
    /*
     | ⭐ الدليل الاجتماعيّ الحيّ (2.9-7) — **عدّادات حقيقيّة** بحدودها المعتمَدة:
     | الدرس 20 · نادي الخامسة 10 · الحروب 3 · نسبة الليدر بورد 20.
     |
     | فوق الحدّ نعرض الرقم، وتحته **نؤطّر بالريادة** («كن أوّل من ينهي هذا
     | الدرس اليوم») — فلا نكذب ولا نضخّم رقمًا صغيرًا (الحارس الأخلاقيّ في 2.9).
     */
    $proof = app(App\Services\Engagement\SocialProof::class)->frame($context, (int) $count);
@endphp

@if ($proof['text'] !== '')
    <p {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs']) }}
       style="color: {{ $proof['lead'] ? 'var(--color-brand-500)' : 'var(--text-muted)' }}"
       data-social-proof="{{ $context }}" data-social-lead="{{ $proof['lead'] ? '1' : '0' }}">
        <x-icon :name="$proof['lead'] ? 'trophy' : $icon" size="14" />
        <span>{{ $proof['text'] }}</span>
    </p>
@endif
