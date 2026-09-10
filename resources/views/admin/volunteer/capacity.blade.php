@extends('layouts.admin')

@section('title', setting('admin.volunteer.capacity.tqryr_alsaa', 'تقرير السعة'))

@section('content')
    <x-page-header
        :title="setting('admin.volunteer.capacity.tqryr_alsaa', 'تقرير السعة')"
        :subtitle="setting('admin.volunteer.capacity.akthr_alaqsam_tkhma_wakthrha_fragha_lfth_qsm', 'أكثر الأقسام تخمةً وأكثرها فراغًا — لفتح قسم جديد أو دمج قسمين.')"
        :breadcrumbs="[
            ['label' => setting('admin.volunteer.capacity.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')],
            ['label' => setting('admin.volunteer.capacity.alhykl_walsaa', 'الهيكل والسعة'), 'url' => route('admin.volunteer.org')],
            ['label' => setting('admin.volunteer.capacity.tqryr_alsaa', 'تقرير السعة')],
        ]" />

    <x-filters :action="route('admin.volunteer.org.capacity')">
        <div>
            <label class="block text-xs mb-1" for="f-track" style="color: var(--text-muted)">{{ setting('admin.volunteer.capacity.almsar', 'المسار') }}</label>
            <select id="f-track" name="track" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.volunteer.capacity.alkl', 'الكلّ') }}</option>
                @foreach ($tracks as $track)
                    <option value="{{ $track->id }}" @selected($filters['track'] === $track->id)>{{ $track->name_ar }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">{{ setting('admin.volunteer.capacity.fltr', 'فلتر') }}</button>
    </x-filters>

    <section class="card p-4 md:p-5">
        <h2 class="font-bold mb-3">{{ setting('admin.volunteer.capacity.ishghal_alkyanat', 'إشغال الكيانات') }}</h2>
        @forelse ($rows->sortByDesc('percent') as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $row['entity']->name_ar }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">{{ $row['entity']->track?->name_ar }} {{ setting('admin.volunteer.capacity.shwaghr', '· شواغر') }} {{ $row['vacancies'] }}</div>
                </div>
                <x-state-badge :state="$row['state']" :label="$row['members'].'/'.$row['cap'].' · '.$row['percent'].'%'" />
            </div>
        @empty
            {{-- تمييز «مفيش كيانات أصلًا» عن «فلتر المسار ما طابقش حاجة» (24.2) —
                 القسم التالي (نطاق الإشراف) بلا فلترٍ خاصٍّ به فلم يُلمَس. --}}
            <x-empty :message="setting('admin.volunteer.capacity.mfysh_kyanat_fy_alntaq_dh', 'مفيش كيانات في النطاق ده.')"
                     :filtered="$filters['track'] !== 0" />
        @endforelse
    </section>

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-1">{{ setting('admin.volunteer.capacity.ntaq_alishraf_lkl_mswwl', 'نطاق الإشراف لكلّ مسؤول') }}</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('admin.volunteer.capacity.altjawz_tnbyh_fqt_bla_mna_wla_mbrr_ilzamy', 'التجاوز تنبيه فقط — بلا منع ولا مبرّر إلزاميّ.') }}</p>

        @forelse ($spans->sortByDesc('count')->take((int) setting('volunteer.org.span_rows', 30)) as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $row['membership']->user?->name }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">
                        {{ $row['membership']->position?->name_ar }} · {{ $row['membership']->entity?->name_ar }}
                    </div>
                </div>
                <span class="flex items-center gap-2 shrink-0">
                    <span class="text-xs" style="color: var(--text-muted)">
                        {{ $row['min'] ?? '—' }} / {{ $row['default'] ?? '—' }} / {{ $row['max'] ?? setting('admin.volunteer.capacity.bla_hd', 'بلا حدّ') }}
                    </span>
                    <x-state-badge :state="$row['state']" :label="$row['count'].setting('admin.volunteer.capacity.thth', ' تحته')" />
                </span>
            </div>
        @empty
            <x-empty :message="setting('admin.volunteer.capacity.mfysh_adwyat_nshta', 'مفيش عضويّات نشطة.')" />
        @endforelse
    </section>
@endsection
