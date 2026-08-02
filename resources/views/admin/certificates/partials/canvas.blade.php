@php
    /**
     * لوحة عرض الشهادة (12.5-ب): خلفيّة + طبقات نصّ حرّة فوقها.
     *
     * ⭐ الوحدات هنا **نفس وحدات الراسم على الخادم**: الإحداثيّات كسور 0…1
     * وحجم الخطّ بكسل على عرض 1754 — فما تراه هنا هو ما يخرج في الصورة النهائيّة.
     *
     * ولماذا `dir="ltr"` على اللوحة؟ لأنّ محور X في الراسم يُقاس من **اليسار**،
     * فلو تركناها ترث RTL انقلبت كلّ المواضع أفقيًّا وخالفت الشهادة الصادرة.
     * والنصّ نفسه يبقى عربيًّا باتّجاهه الطبيعيّ (`dir="auto"`).
     *
     * @var array $layers · @var object $template · @var array $sample
     * @var int   $canvasWidth  عرض العرض على الشاشة بالبكسل
     * @var bool  $editable      هل الطبقات قابلة للسحب؟ (المصمّم فقط)
     */
    $canvasWidth = $canvasWidth ?? 900;
    $reference = \App\Services\Admin\Content\TemplateDesigner::REFERENCE_WIDTH;
    $scale = $canvasWidth / $reference;
    $editable = $editable ?? false;
    $designer = app(\App\Services\Admin\Content\TemplateDesigner::class);
    $background = $template->background_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($template->background_path)
        : null;
@endphp

<div dir="ltr" class="relative overflow-hidden rounded-xl select-none"
     data-canvas data-scale="{{ $scale }}"
     style="width: {{ $canvasWidth }}px; max-width: 100%;
            aspect-ratio: {{ $template->width_px ?: $reference }} / {{ $template->height_px ?: 1240 }};
            background: {{ $background ? 'url('.$background.') center/cover no-repeat' : 'var(--surface-sunken)' }};
            border: 1px solid var(--border)">

    @unless ($background)
        {{-- التصميم الافتراضيّ يعمل من أوّل يوم بلا رفع خلفيّة (12.5-ب) --}}
        <div class="absolute inset-4 rounded-lg pointer-events-none"
             style="border: 2px solid var(--color-state-honor)"></div>
    @endunless

    @if ($editable)
        {{-- شبكة المحاذاة (Snap) — تظهر وتختفي بزرّ في المصمّم --}}
        <div class="absolute inset-0 pointer-events-none hidden" data-grid
             style="background-image:
                linear-gradient(to right, color-mix(in srgb, var(--color-brand-500) 25%, transparent) 1px, transparent 1px),
                linear-gradient(to bottom, color-mix(in srgb, var(--color-brand-500) 25%, transparent) 1px, transparent 1px);
                background-size: {{ $grid ?? 5 }}% {{ $grid ?? 5 }}%"></div>
    @endif

    @foreach ($layers as $layer)
        @php
            $value = $designer->renderLayer($layer, $sample);
            $shift = match ($layer['align']) { 'start' => '-100%', 'end' => '0%', default => '-50%' };
        @endphp

        @if ($layer['type'] === 'qr')
            <div class="absolute grid place-items-center" data-layer="{{ $layer['id'] }}"
                 @if ($editable) data-locked="{{ $layer['locked'] ? '1' : '0' }}" @endif
                 style="left: {{ $layer['x'] * 100 }}%; top: {{ $layer['y'] * 100 }}%;
                        width: {{ $layer['size'] * 100 }}%; aspect-ratio: 1;
                        transform: translate(-50%, -50%);
                        background: #fff; color: #000; font-size: 12px; font-weight: 700;
                        cursor: {{ $editable && ! $layer['locked'] ? 'grab' : 'default' }};
                        opacity: {{ $layer['visible'] ? 1 : 0.35 }}">QR</div>
        @elseif ($value !== '' || $editable)
            <div dir="auto" class="absolute whitespace-nowrap" data-layer="{{ $layer['id'] }}"
                 @if ($editable) data-locked="{{ $layer['locked'] ? '1' : '0' }}" @endif
                 style="left: {{ $layer['x'] * 100 }}%; top: {{ $layer['y'] * 100 }}%;
                        transform: translate({{ $shift }}, -100%) rotate({{ -1 * $layer['rotate'] }}deg);
                        font-size: {{ round($layer['size'] * $scale, 2) }}px;
                        font-weight: {{ $layer['bold'] ? 800 : 400 }};
                        color: {{ $layer['color'] }};
                        opacity: {{ $layer['visible'] ? 1 : 0.35 }};
                        cursor: {{ $editable && ! $layer['locked'] ? 'grab' : 'default' }}">{{ $value !== '' ? $value : $layer['label'] }}</div>
        @endif
    @endforeach
</div>
