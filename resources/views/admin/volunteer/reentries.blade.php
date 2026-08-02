@extends('layouts.app')

@section('title', 'العائدون')

@section('content')
    <x-page-header
        title="العائدون"
        subtitle="الباب مفتوح لمن خرج بشرف — والامتحان يُعاد لإثبات جاهزيّة اليوم لا جاهزيّة الأمس."
        :breadcrumbs="[['label' => 'التطوّع', 'url' => route('admin.volunteer.index')], ['label' => 'العائدون']]" />

    @include('admin.volunteer.partials.tabs', ['current' => 'reentries'])

    <div class="card p-3 mb-4 text-sm">
        العائد يبدأ من بوزشن <strong>{{ $startsPosition }}</strong>،
        و{{ $examRequired ? 'دخول الامتحان من جديد شرطٌ لا يُستثنى منه أحد' : 'الامتحان غير مُلزَم حاليًّا بقرار إداريّ' }}.
    </div>

    <section class="space-y-3">
        @forelse ($records as $row)
            @php $record = $row['offboarding']; @endphp
            <article class="card p-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-semibold">{{ $record->user?->name }} <span class="text-xs" style="color: var(--text-muted)">#{{ $record->user?->code }}</span></div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted)">
                            خرج في {{ $record->completed_at?->format('Y-m-d') }} · {{ \App\Services\Admin\Volunteer\OffboardingService::TYPES[$record->type] ?? $record->type }}
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
                                    style="background: var(--color-brand-500); color: #04201c">افتح ملفّ عودة</button>
                        </form>
                    @endcan
                @endif
            </article>
        @empty
            <x-empty message="مفيش حالات خروج مكتملة لسّه." />
        @endforelse
    </section>

    @if ($open->isNotEmpty())
        <section class="card p-4 md:p-5 mt-4">
            <h2 class="font-bold mb-3">ملفّات عودة مفتوحة</h2>
            @foreach ($open as $reentry)
                <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                    <span>{{ $reentry->user?->name }}</span>
                    <span class="flex items-center gap-2">
                        <span class="text-xs" style="color: var(--text-muted)">{{ $reentry->started_at?->diffForHumans() }}</span>
                        <x-state-badge :state="$reentry->status === 'completed' ? 'ok' : 'warn'" :label="$reentry->status === 'completed' ? 'مكتمل' : 'جارٍ'" />
                    </span>
                </div>
            @endforeach
        </section>
    @endif
@endsection
