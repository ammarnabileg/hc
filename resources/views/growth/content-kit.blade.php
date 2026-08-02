@extends('layouts.app')

@section('title', setting('growth.volunteer_kit.title', 'حزمة المحتوى'))

@section('content')
    <x-page-header :title="setting('growth.volunteer_kit.title', 'حزمة المحتوى')"
                   :subtitle="setting('growth.volunteer_kit.subtitle', 'خُد الرابط والصور والنصوص الجاهزة وانشرها.')" />

    {{-- 1) الكارت الأسبوعيّ (21.2-د): معلومة/نصيحة تدور بدوريّة معتمَدة، بلا مصمّم --}}
    <section class="card p-4 mb-4" aria-label="الكارت الأسبوعيّ">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
            <div>
                <h2 class="font-extrabold">{{ setting('growth.weekly_card.title', 'نصيحة الأسبوع') }}</h2>
                <p class="text-xs mt-0.5" style="color: var(--text-muted)">
                    {{ $card['from']->translatedFormat('j F') }} — {{ $card['to']->translatedFormat('j F Y') }}
                    · بيتغيّر كلّ {{ $periodDays }} يوم
                </p>
            </div>

            <a href="{{ $cardUrl }}" download="weekly-card.svg"
               class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">نزّل الصورة</a>
        </div>

        <img src="{{ $cardUrl }}" alt="{{ $card['tip'] }}" loading="lazy"
             class="w-full rounded-xl" style="border: 1px solid var(--border)">
    </section>

    {{-- 2) رابط الدعوة الشخصيّ موسومًا بـUTM (21.2-هـ · 21.2-ح) --}}
    <section class="card p-4 mb-4" aria-label="رابط الدعوة">
        <h2 class="font-extrabold mb-1">{{ setting('growth.volunteer_kit.link_label', 'رابط دعوتك') }}</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted)">
            {{ setting('growth.volunteer_kit.link_hint', 'كلّ مَن يسجّل من الرابط ده بيتحسبلك — والرابط موسوم علشان نعرف عائد كلّ قناة.') }}
        </p>

        <div class="flex items-center gap-2 flex-wrap">
            <code class="flex-1 min-w-48 rounded-xl px-3 py-2 text-xs break-all"
                  style="background: var(--surface-sunken)">{{ $link }}</code>
            <button type="button" data-copy="{{ $link }}"
                    class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">انسخ</button>
        </div>
    </section>

    {{-- 3) نصوص جاهزة قابلة للتعديل — بلا مبالغة ولا ندرة مزيّفة (2.9) --}}
    <section class="mb-4" aria-label="نصوص جاهزة">
        <h2 class="font-extrabold mb-2">{{ setting('growth.volunteer_kit.scripts_label', 'نصوص جاهزة') }}</h2>

        <div class="grid gap-3 md:grid-cols-3">
            @foreach ($scripts as $script)
                <div class="card p-3 flex flex-col gap-2">
                    <p class="text-sm font-semibold">{{ $script['title'] }}</p>
                    <textarea rows="5" class="w-full rounded-xl px-3 py-2 text-xs leading-6"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                              data-script>{{ $script['body'] }}</textarea>
                    <button type="button" data-copy-textarea
                            class="rounded-xl px-3 py-2 text-xs motion-standard" style="background: var(--surface-sunken)">
                        انسخ النصّ
                    </button>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 4) قوالب الاستوديو (12.14) — نستهلكها ولا نبني استوديو ثانيًا --}}
    <section aria-label="قوالب الصور">
        <h2 class="font-extrabold mb-2">{{ setting('growth.volunteer_kit.templates_label', 'قوالب الصور') }}</h2>

        @if ($templates->isEmpty())
            <x-empty :message="setting('growth.volunteer_kit.templates_empty', 'مافيش قوالب متاحة لك دلوقتي.')" />
        @else
            <div class="grid gap-3 md:grid-cols-4">
                @foreach ($templates as $template)
                    <div class="card p-3">
                        <p class="text-sm font-semibold truncate">{{ $template->name }}</p>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $template->width_px }}×{{ $template->height_px }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection

@push('scripts')
    <script>
        // ردّ فوريّ لكلّ فعل (2.17-أ): «اتنسخ ✓» على الزرّ نفسه
        const flash = (button, label) => {
            const original = button.textContent;
            button.textContent = label;
            setTimeout(() => { button.textContent = original; }, 1800);
        };

        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(button.dataset.copy);
                    flash(button, 'اتنسخ ✓');
                } catch (error) {
                    flash(button, 'انسخه يدويًّا');
                }
            });
        });

        document.querySelectorAll('[data-copy-textarea]').forEach((button) => {
            button.addEventListener('click', async () => {
                const field = button.parentElement.querySelector('[data-script]');
                try {
                    await navigator.clipboard.writeText(field.value);
                    flash(button, 'اتنسخ ✓');
                } catch (error) {
                    field.select();
                    flash(button, 'انسخه يدويًّا');
                }
            });
        });
    </script>
@endpush
