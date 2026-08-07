@extends('layouts.admin')

@section('title', setting('admin.volunteer.investigations.index.lgna_althqyq', 'لجنة التحقيق'))

@section('content')
    <x-page-header
        :title="setting('admin.volunteer.investigations.index.lgna_althqyq', 'لجنة التحقيق')"
        :subtitle="setting('admin.volunteer.investigations.index.tfayl_bdgha_wahda_lmshrf_aam_alttwa_walmqad', 'تفعيل بضغطة واحدة لمشرف عام التطوّع — والمقعدان يتعيّنان آليًّا.')"
        :breadcrumbs="[['label' => setting('admin.volunteer.investigations.index.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')], ['label' => setting('admin.volunteer.investigations.index.lgna_althqyq', 'لجنة التحقيق')]]">
    </x-page-header>

    {{-- الطابور: مسودّات آليّة بانتظار «يحتاج قرارك» --}}
    <section class="mb-6">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.volunteer.investigations.index.yhtaj_qrark', 'يحتاج قرارك') }}</h2>
        <div class="space-y-3">
            @forelse ($queue as $referral)
                <article class="card p-4 flex items-center justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-semibold">{{ setting('admin.volunteer.investigations.index.mswda_tlqaya', 'مسودّة تلقائيّة') }} #{{ $referral->id }}</div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted)">{{ $referral->note }}</div>
                    </div>
                    @can('investigations.create')
                        <form method="post" action="{{ route('admin.volunteer.investigations.activate', $referral->id) }}">
                            @csrf
                            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.investigations.index.fal_bdgha_wahda', 'فعِّل بضغطة واحدة') }}</button>
                        </form>
                    @endcan
                </article>
            @empty
                <x-empty :message="setting('admin.volunteer.investigations.index.mfysh_mswdat_mftwha', 'مفيش مسودّات مفتوحة — كلّ حاجة تحت السيطرة.')" />
            @endforelse
        </div>
    </section>

    {{-- الملفّات الجارية --}}
    <section class="mb-6">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.volunteer.investigations.index.almlfat_algarya', 'الملفّات الجارية') }}</h2>
        <div class="space-y-3">
            @forelse ($cases as $case)
                <a href="{{ route('admin.volunteer.investigations.show', $case) }}" class="card p-4 flex items-center justify-between gap-3 flex-wrap motion-standard">
                    <div class="min-w-0">
                        <div class="font-semibold">{{ $case->user?->name }} <span class="text-xs" style="color: var(--text-muted)">#{{ $case->user?->code }}</span></div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted)">
                            {{ setting('admin.volunteer.investigations.index.alablayn', 'الأبلاين:') }} {{ $case->seatUpline?->name ?? '—' }}
                            · {{ setting('admin.volunteer.investigations.index.qsm_almttwan', 'قسم المتطوّعين:') }} {{ $case->seatDept?->name ?? '—' }}
                        </div>
                    </div>
                    <x-state-badge state="warn" :label="$case->status" />
                </a>
            @empty
                <x-empty :message="setting('admin.volunteer.investigations.index.mfysh_mlfat_gary', 'مفيش ملفّات جارية.')" />
            @endforelse
        </div>
    </section>

    {{-- الأرشيف --}}
    <section>
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.volunteer.investigations.index.alarshyf', 'الأرشيف') }}</h2>
        <div class="space-y-2">
            @forelse ($closed as $case)
                <a href="{{ route('admin.volunteer.investigations.show', $case) }}" class="card p-3 flex items-center justify-between gap-3 text-sm motion-standard">
                    <span>{{ $case->user?->name }} <span class="text-xs" style="color: var(--text-muted)">#{{ $case->user?->code }}</span></span>
                    <span class="text-xs" style="color: var(--text-muted)">{{ $case->closed_at?->format('Y-m-d') }}</span>
                </a>
            @empty
                <x-empty :message="setting('admin.volunteer.investigations.index.mfysh_mlfat_mrshfa_bad', 'مفيش ملفّات مؤرشفة بعد.')" />
            @endforelse
        </div>
    </section>
@endsection
