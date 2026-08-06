@php
    /** نصوص السكربت — من الإعدادات لا محروقةً في الجافاسكربت (2.13-أ) */
    $hcWords = array_merge($hcWords ?? [], [
        'volunteer_card.show.js_1' => (string) setting('volunteer_card.show.js_1', 'مشاركة'),
        'volunteer_card.show.js_2' => (string) setting('volunteer_card.show.js_2', 'اتنسخ ✓'),
        'volunteer_card.show.js_3' => (string) setting('volunteer_card.show.js_3', 'انسخ الرابط من المتصفّح'),
    ]);
@endphp

@extends('layouts.guest')

@section('title', strtr((string) setting('volunteer_card.show.section_1', 'بطاقة المتطوّع — :a1'), [':a1' => (string) ($data['name'])]))

@php
    /**
     * بطاقة المتطوّع الرقميّة (13.4-ر) — صفحة عامّة بلا تسجيل.
     * المحتوى من **القائمة المقفولة حصرًا** (12.14-د)، و**⛔ لا بيانات تواصل إطلاقًا**:
     * لا هاتف ولا بريد ولا واتساب — ولا حتى زرّ طلب.
     * وإظهار Rep إعدادٌ افتراضيّه مخفيّ.
     */
    $expired = $data['status'] !== 'valid';
    $frame = $data['is_club']
        ? 'border-color: var(--color-state-honor); box-shadow: inset 0 0 0 1px var(--color-state-honor)'
        : '';
    $dateFormat = (string) setting('volunteer.org.date_format', 'j F Y');
@endphp

@section('content')
    <div class="w-full max-w-sm">
        <div class="card p-5" style="{{ $frame }}">
            <div class="flex items-center justify-between gap-2 mb-4">
                <span class="text-xs" style="color: var(--text-muted)">#{{ $data['code'] }}</span>
                <x-state-badge :state="$expired ? 'idle' : 'ok'" :label="$expired ? (string) setting('volunteer_card.show.label_1', 'منتهية') : (string) setting('volunteer_card.show.label_2', 'سارية')" />
            </div>

            <div class="flex items-center gap-3">
                <x-avatar :user="$data['user']" size="16" :name="$data['name']" />
                <div class="min-w-0">
                    <div class="font-extrabold text-lg truncate">{{ $data['name'] }}</div>
                    <div class="text-sm" style="color: var(--text-muted)">{{ $data['position'] }}</div>
                    @if ($data['is_club'])
                        <div class="text-xs mt-1" style="color: var(--color-state-honor)">★ {{ setting('volunteer_card.show.text_1', 'نادي التميّز') }}</div>
                    @endif
                </div>
            </div>

            {{-- إظهار Rep من الإعدادات وافتراضيّه مخفيّ (13.4-ر-ب) --}}
            @if ($data['rep_label'])
                <div class="mt-3"><x-state-badge :state="$data['rep_state']" :label="$data['rep_label']" /></div>
            @endif

            <dl class="mt-4 text-sm grid grid-cols-2 gap-y-3">
                <dt style="color: var(--text-muted)">{{ setting('volunteer_card.show.text_2', 'القسم') }}</dt>
                <dd class="text-end font-semibold">{{ $data['department'] ?: '—' }}</dd>

                <dt style="color: var(--text-muted)">{{ setting('volunteer_card.show.text_3', 'المسار') }}</dt>
                <dd class="text-end font-semibold">{{ $data['track'] ?: '—' }}</dd>

                <dt style="color: var(--text-muted)">{{ setting('volunteer_card.show.text_4', 'مدّة الخدمة') }}</dt>
                <dd class="text-end font-semibold">{{ $data['service_duration'] }}</dd>

                <dt style="color: var(--text-muted)">{{ setting('volunteer_card.show.text_5', 'الدولة/المحافظة') }}</dt>
                <dd class="text-end font-semibold">
                    {{ trim(($data['country'] ?: '').(($data['country'] && $data['governorate']) ? ' — ' : '').($data['governorate'] ?: '')) ?: '—' }}
                </dd>

                <dt style="color: var(--text-muted)">{{ setting('volunteer_card.show.text_6', 'تاريخ الانضمام') }}</dt>
                <dd class="text-end font-semibold">
                    {{ $data['joined_at']?->translatedFormat($dateFormat) ?? '—' }}
                </dd>
            </dl>

            <div class="mt-5 pt-4 flex items-center justify-between gap-3" style="border-top: 1px solid var(--border)">
                <a href="{{ $verifyUrl }}" class="shrink-0" aria-label="{{ setting('volunteer_card.show.aria_label_1', 'صفحة التحقّق') }}">
                    @include('cards.partials.qr', ['qr' => $qr, 'size' => 96])
                </a>
                <div class="text-xs text-end" style="color: var(--text-muted)">
                    <div>{{ setting('volunteer_card.verify_hint', 'امسح الكود للتحقّق من البطاقة') }}</div>
                    <button type="button" data-card-share="{{ url()->current() }}"
                            class="btn mt-2 rounded-xl px-3 py-2 text-xs font-semibold"
                            style="background: var(--color-brand-500); color:#04201c">{{ setting('volunteer_card.show.text_7', 'مشاركة') }}</button>
                </div>
            </div>
        </div>

        {{-- صورة البطاقة الفعليّة — نسختان جاهزتان + QR الدعوة اختياريًّا (13.4-ر-د · هـ) --}}
        <div class="card p-4 mt-3 text-sm">
            <label class="flex items-center gap-2 mb-3">
                <input type="checkbox" id="card-invite-toggle" class="w-4 h-4">
                {{ setting('volunteer_card.show.invite_toggle', 'أضِف QR دعوتي على الصورة') }}
            </label>
            <div class="flex flex-wrap gap-2">
                <a id="card-image-badge" href="{{ route('card.image', [$card->code, 'badge']) }}" download
                   class="btn rounded-xl px-3 py-2 text-xs font-semibold" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    {{ setting('volunteer_card.show.download_badge', 'تحميل بادج الفعاليّات') }}
                </a>
                <a id="card-image-story" href="{{ route('card.image', [$card->code, 'story']) }}" download
                   class="btn rounded-xl px-3 py-2 text-xs font-semibold" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    {{ setting('volunteer_card.show.download_story', 'تحميل صورة للنشر') }}
                </a>
            </div>
        </div>

        <p class="text-center text-xs mt-3" style="color: var(--text-muted)">
            {{ config('app.name') }}
        </p>
    </div>

    <script>
    /* مشاركة البطاقة — كلّ بطاقة تُنشَر قناة اكتساب (21.1) */
    document.querySelector('[data-card-share]')?.addEventListener('click', async (e) => {
        const url = e.currentTarget.dataset.cardShare;
        const done = (msg) => { e.currentTarget.textContent = msg; setTimeout(() => { e.currentTarget.textContent = @json($hcWords['volunteer_card.show.js_1']); }, 2000); };
        try {
            if (navigator.share) { await navigator.share({ url: url }); return; }
            await navigator.clipboard.writeText(url);
            done(@json($hcWords['volunteer_card.show.js_2']));
        } catch (err) { done(@json($hcWords['volunteer_card.show.js_3'])); }
    });

    // QR الدعوة اختياريّ فوق صورة البطاقة — يُضاف بـ?invite=1 (13.4-ر-هـ)
    document.getElementById('card-invite-toggle')?.addEventListener('change', (e) => {
        ['card-image-badge', 'card-image-story'].forEach((id) => {
            const link = document.getElementById(id);
            if (!link) return;
            const url = new URL(link.href);
            if (e.currentTarget.checked) { url.searchParams.set('invite', '1'); } else { url.searchParams.delete('invite'); }
            link.href = url.toString();
        });
    });
    </script>
@endsection
