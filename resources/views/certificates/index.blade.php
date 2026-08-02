@extends('layouts.app')

@section('title', setting('certificates.labels.my_certificates', 'شهاداتي'))

@section('content')
    <x-page-header :title="setting('certificates.labels.my_certificates', 'شهاداتي')"
                   :subtitle="$certificates->count().' '.setting('certificates.labels.certificate_plural', 'شهادة')"
                   :breadcrumbs="[
                       ['label' => setting('certificates.labels.learning', 'تعلّمي'), 'url' => \Illuminate\Support\Facades\Route::has('learning.courses') ? route('learning.courses') : url('/dashboard')],
                       ['label' => setting('certificates.labels.my_certificates', 'شهاداتي')],
                   ]" />

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
        {{-- رفّ أوسمة (24.5): الشهادة وسامٌ لا صفٌّ في جدول --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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

                <article class="card p-4 animate-fadeup">
                    <img src="{{ route('certificates.image', $certificate->code) }}" loading="lazy"
                         alt="{{ $certificate->data_snapshot['certificate_name'] ?? $certificate->code }}"
                         class="w-full rounded-xl mb-3" style="border: 1px solid var(--border)">

                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="font-bold truncate">{{ $certificate->data_snapshot['certificate_name'] ?? $certificate->certificate_type?->name_ar }}</h2>
                            <p class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ $certificate->certificate_type?->name_ar }} ·
                                {{ $certificate->issued_at?->format(setting('certificates.render.date_format', 'Y/m/d')) }}
                            </p>
                            <p class="text-xs mt-1 tabular-nums" style="color: var(--text-muted)">#{{ $certificate->code }}</p>
                        </div>
                        <x-state-badge :state="$state" :label="$statusLabel" />
                    </div>

                    {{-- الشهادة المنتهية لا تُخفى: تُعرَض بتاريخ إصدارها وتاريخ انتهائها (13.4-ق) --}}
                    @if ($certificate->status === 'expired')
                        <p class="text-xs mt-2" style="color: var(--text-muted)">
                            {{ setting('certificates.status.expired_line', 'انتهى العمل بيها في') }}
                            {{ $certificate->expired_at?->format(setting('certificates.render.date_format', 'Y/m/d')) }}
                            — {{ setting('certificates.status.expired_hint', 'بعد دخولك امتحانًا أحدث. وهي مش ملغاة.') }}
                        </p>
                    @elseif ($certificate->status === 'revoked')
                        <p class="text-xs mt-2" style="color: var(--text-muted)">
                            {{ $certificate->revoked_reason ?: setting('certificates.status.revoked_hint', 'ملغاة — والإلغاء لا يقع إلّا على تزويرٍ مثبَت.') }}
                        </p>
                    @endif

                    <div class="mt-3 flex items-center gap-2">
                        <button type="button" data-modal-open="cert-{{ $certificate->id }}"
                                class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">
                            {{ setting('certificates.labels.open', 'افتح') }}
                        </button>
                        <x-state-badge state="honor" :label="$certificate->certificate_type?->accreditation?->name_ar ?? setting('certificates.accreditation.default_name', 'اعتماد المنصّة')" />
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
