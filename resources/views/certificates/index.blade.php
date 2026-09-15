@extends('layouts.app')

@section('title', setting('certificates.labels.my_certificates', 'شهاداتي'))

@section('content')
    <nav class="text-xs mb-2 flex flex-wrap items-center gap-1" style="color: var(--text-muted)">
        <a href="{{ \Illuminate\Support\Facades\Route::has('learning.courses') ? route('learning.courses') : url('/dashboard') }}"
           class="hover:underline">{{ setting('certificates.labels.learning', 'تعلّمي') }}</a>
        <span aria-hidden="true">‹</span>
        <span>{{ setting('certificates.labels.my_certificates', 'شهاداتي') }}</span>
    </nav>

    {{-- Hero حرفيًّا من ملف الهويّة (`certificatesPage()`: «شهاداتي · إنجازات تستحق أن تبقى») --}}
    <section class="hero">
        <img class="hero-art" src="{{ asset('images/identity/editorial-engraving.webp') }}" alt="">
        <div class="hero-copy">
            <span class="eyebrow">{{ setting('certificates.labels.learning', 'تعلّمي') }}</span>
            <h1>{{ setting('certificates.labels.my_certificates', 'شهاداتي') }}</h1>
            <p>{{ setting('certificates.hero.subtitle', 'إنجازات تستحق أن تبقى.') }}</p>
        </div>
    </section>

    <div class="spread mb-4">
        <span class="small muted">{{ $certificates->count() }} {{ setting('certificates.labels.certificate_plural', 'شهادة') }}</span>
    </div>

    {{-- ثلاثة فلاتر ظاهرة لا أكثر (2.15-أ-4): النوع · السنة · بحث --}}
    <x-filters :action="route('learning.certificates')">
        <label class="text-sm">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('certificates.labels.type', 'النوع') }}</span>
            <select name="type" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('certificates.labels.all', 'الكلّ') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->key }}" @selected($filters['type'] === $type->key)>{{ $type->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('certificates.labels.year', 'السنة') }}</span>
            <select name="year" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('certificates.labels.all', 'الكلّ') }}</option>
                @foreach ($years as $year)
                    <option value="{{ $year }}" @selected($filters['year'] === (string) $year)>{{ $year }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('certificates.labels.search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="{{ setting('certificates.labels.search_hint', 'بالكود أو الاسم') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">
            {{ setting('certificates.labels.apply', 'طبّق') }}
        </button>
    </x-filters>

    @if ($certificates->isEmpty())
        <x-empty :message="setting('certificates.labels.empty', 'أوّل شهادة على بُعد تدريب واحد.')"
                 :action="setting('certificates.labels.empty_action', 'روح لتدريباتي')"
                 :href="\Illuminate\Support\Facades\Route::has('learning.courses') ? route('learning.courses') : url('/dashboard')" />
    @else
        {{-- رفّ أوسمة (24.5) — حرفيًّا `.shelf-item`: الشهادة وسامٌ لا صفٌّ في جدول --}}
        <div class="grid3">
            @foreach ($certificates as $certificate)
                @php
                    $state = match ($certificate->status) {
                        'valid' => 'ok',
                        'expired' => 'idle',
                        default => 'danger',
                    };
                    $statusLabel = match ($certificate->status) {
                        'valid' => setting('certificates.status.valid_label', 'سارية'),
                        'expired' => setting('certificates.status.expired_label', 'منتهية'),
                        default => setting('certificates.status.revoked_label', 'ملغاة'),
                    };
                @endphp

                <article class="shelf-item">
                    <img src="{{ route('certificates.image', $certificate->code) }}" loading="lazy"
                         alt="{{ $certificate->data_snapshot['certificate_name'] ?? $certificate->code }}"
                         class="w-full rounded-xl" style="border: 1px solid var(--line)">

                    <div class="spread mt-3">
                        <h3 class="truncate">{{ $certificate->data_snapshot['certificate_name'] ?? $certificate->certificate_type?->name_ar }}</h3>
                        <x-state-badge :state="$state" :label="$statusLabel" />
                    </div>
                    <div class="small muted mt-1">
                        {{ $certificate->certificate_type?->name_ar }} ·
                        {{ $certificate->issued_at?->format(setting('certificates.render.date_format', 'Y/m/d')) }} ·
                        <bdi>#{{ $certificate->code }}</bdi>
                    </div>

                    {{-- الشهادة المنتهية لا تُخفى: تُعرَض بتاريخ إصدارها وتاريخ انتهائها (13.4-ق) --}}
                    @if ($certificate->status === 'expired')
                        <p class="small muted mt-2">
                            {{ setting('certificates.status.expired_line', 'انتهى العمل بيها في') }}
                            {{ $certificate->expired_at?->format(setting('certificates.render.date_format', 'Y/m/d')) }}
                            — {{ setting('certificates.status.expired_hint', 'بعد دخولك امتحانًا أحدث. وهي مش ملغاة.') }}
                        </p>
                    @elseif ($certificate->status === 'revoked')
                        <p class="small muted mt-2">
                            {{ $certificate->revoked_reason ?: setting('certificates.status.revoked_hint', 'ملغاة — والإلغاء لا يقع إلّا على تزويرٍ مثبَت.') }}
                        </p>
                    @endif

                    <x-state-badge state="honor" :label="$certificate->certificate_type?->accreditation?->name_ar ?? setting('certificates.accreditation.default_name', 'اعتماد المنصّة')" class="mt-2" />

                    <div class="mt-4">
                        <button type="button" data-modal-open="cert-{{ $certificate->id }}" class="btn text inline-flex items-center gap-1">
                            {{ setting('certificates.labels.open', 'افتح') }} <x-icon name="left" size="14" />
                        </button>
                    </div>
                </article>
            @endforeach
        </div>

        @push('modals')
            @foreach ($certificates as $certificate)
                @include('certificates.partials.certificate-modal', ['certificate' => $certificate])
            @endforeach
        @endpush
    @endif
@endsection
