@extends('layouts.guest')

@section('title', 'بطاقة المتطوّع — '.$data['name'])

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
                <x-state-badge :state="$expired ? 'idle' : 'ok'" :label="$expired ? 'منتهية' : 'سارية'" />
            </div>

            <div class="flex items-center gap-3">
                <x-avatar :user="$data['user']" size="16" :name="$data['name']" />
                <div class="min-w-0">
                    <div class="font-extrabold text-lg truncate">{{ $data['name'] }}</div>
                    <div class="text-sm" style="color: var(--text-muted)">{{ $data['position'] }}</div>
                    @if ($data['is_club'])
                        <div class="text-xs mt-1" style="color: var(--color-state-honor)">★ نادي التميّز</div>
                    @endif
                </div>
            </div>

            {{-- إظهار Rep من الإعدادات وافتراضيّه مخفيّ (13.4-ر-ب) --}}
            @if ($data['rep_label'])
                <div class="mt-3"><x-state-badge :state="$data['rep_state']" :label="$data['rep_label']" /></div>
            @endif

            <dl class="mt-4 text-sm grid grid-cols-2 gap-y-3">
                <dt style="color: var(--text-muted)">القسم</dt>
                <dd class="text-end font-semibold">{{ $data['department'] ?: '—' }}</dd>

                <dt style="color: var(--text-muted)">المسار</dt>
                <dd class="text-end font-semibold">{{ $data['track'] ?: '—' }}</dd>

                <dt style="color: var(--text-muted)">مدّة الخدمة</dt>
                <dd class="text-end font-semibold">{{ $data['service_duration'] }}</dd>

                <dt style="color: var(--text-muted)">الدولة/المحافظة</dt>
                <dd class="text-end font-semibold">
                    {{ trim(($data['country'] ?: '').(($data['country'] && $data['governorate']) ? ' — ' : '').($data['governorate'] ?: '')) ?: '—' }}
                </dd>

                <dt style="color: var(--text-muted)">تاريخ الانضمام</dt>
                <dd class="text-end font-semibold">
                    {{ $data['joined_at']?->translatedFormat($dateFormat) ?? '—' }}
                </dd>
            </dl>

            <div class="mt-5 pt-4 flex items-center justify-between gap-3" style="border-top: 1px solid var(--border)">
                <a href="{{ $verifyUrl }}" class="shrink-0" aria-label="صفحة التحقّق">
                    @include('cards.partials.qr', ['qr' => $qr, 'size' => 96])
                </a>
                <div class="text-xs text-end" style="color: var(--text-muted)">
                    <div>{{ setting('volunteer_card.verify_hint', 'امسح الكود للتحقّق من البطاقة') }}</div>
                    <button type="button" data-card-share="{{ url()->current() }}"
                            class="btn mt-2 rounded-xl px-3 py-2 text-xs font-semibold"
                            style="background: var(--color-brand-500); color:#04201c">مشاركة</button>
                </div>
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
        const done = (msg) => { e.currentTarget.textContent = msg; setTimeout(() => { e.currentTarget.textContent = 'مشاركة'; }, 2000); };
        try {
            if (navigator.share) { await navigator.share({ url: url }); return; }
            await navigator.clipboard.writeText(url);
            done('اتنسخ ✓');
        } catch (err) { done('انسخ الرابط من المتصفّح'); }
    });
    </script>
@endsection
