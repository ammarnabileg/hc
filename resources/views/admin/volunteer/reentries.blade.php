@extends('layouts.admin')

@section('title', setting('admin.volunteer.reentries.alaaydwn', 'العائدون'))

@section('content')
    <x-page-header
        :title="setting('admin.volunteer.reentries.alaaydwn', 'العائدون')"
        :subtitle="setting('admin.volunteer.reentries.albab_mftwh_lmn_khrj_bshrf_walamthan_yaad', 'الباب مفتوح لمن خرج بشرف — والامتحان يُعاد لإثبات جاهزيّة اليوم لا جاهزيّة الأمس.')"
        :breadcrumbs="[['label' => setting('admin.volunteer.reentries.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')], ['label' => setting('admin.volunteer.reentries.alaaydwn', 'العائدون')]]" />

    @include('admin.volunteer.partials.tabs', ['current' => 'reentries'])

    <div class="card p-3 mb-4 text-sm">
        {{ setting('admin.volunteer.reentries.alaayd_ybda_mn_bwzshn', 'العائد يبدأ من بوزشن') }} <strong>{{ $startsPosition }}</strong>،
        و{{ $examRequired ? setting('admin.volunteer.reentries.dkhwl_alamthan_mn_jdyd_shrt_la_ystthna_mnh', 'دخول الامتحان من جديد شرطٌ لا يُستثنى منه أحد') : setting('admin.volunteer.reentries.alamthan_ghyr_mlzm_halya_bqrar_idary', 'الامتحان غير مُلزَم حاليًّا بقرار إداريّ') }}.
    </div>

    <section class="space-y-3">
        @forelse ($records as $row)
            @php $record = $row['offboarding']; @endphp
            <article class="card p-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-semibold">{{ $record->user?->name }} <span class="text-xs" style="color: var(--text-muted)">#{{ $record->user?->code }}</span></div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted)">
                            {{ setting('admin.volunteer.reentries.khrj_fy', 'خرج في') }} {{ $record->completed_at?->format('Y-m-d') }} · {{ \App\Services\Admin\Volunteer\OffboardingService::types()[$record->type] ?? $record->type }}
                        </div>
                    </div>
                    <x-state-badge :state="$row['state']" :label="$row['label']" />
                </div>

                @if ($row['copy'])
                    <p class="text-sm mt-2" style="color: var(--text-muted)">{{ $row['copy'] }}</p>
                @endif

                @if ($row['state'] === 'ok')
                    @can('offboarding.approve')
                        <form method="post" action="{{ route('admin.volunteer.offboarding.reentry', $record) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.reentries.afth_mlf_awda', 'افتح ملفّ عودة') }}</button>
                        </form>
                    @endcan
                @endif
            </article>
        @empty
            <x-empty :message="setting('admin.volunteer.reentries.mfysh_halat_khrwj_mktmla_lsh', 'مفيش حالات خروج مكتملة لسّه.')" />
        @endforelse
    </section>

    @if ($open->isNotEmpty())
        <section class="card p-4 md:p-5 mt-4">
            <h2 class="font-bold mb-3">{{ setting('admin.volunteer.reentries.mlfat_awda_mftwha', 'ملفّات عودة مفتوحة') }}</h2>
            @foreach ($open as $reentry)
                <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                    <span>{{ $reentry->user?->name }}</span>
                    <span class="flex items-center gap-2">
                        <span class="text-xs" style="color: var(--text-muted)">{{ $reentry->started_at?->diffForHumans() }}</span>
                        <x-state-badge :state="$reentry->status === 'completed' ? 'ok' : 'warn'" :label="$reentry->status === 'completed' ? setting('admin.volunteer.reentries.mktml', 'مكتمل') : setting('admin.volunteer.reentries.jar', 'جارٍ')" />
                    </span>
                </div>
            @endforeach
        </section>
    @endif
@endsection
