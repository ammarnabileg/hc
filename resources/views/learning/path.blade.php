@extends('layouts.app')
@section('title', $path->name_ar)

@push('head')
    @include('learning.partials.styles')
@endpush

@section('content')
    <x-page-header :title="$path->name_ar"
                   :subtitle="$path->description_ar"
                   :breadcrumbs="[
                       ['label' => setting('learning.breadcrumb.root'), 'url' => route('learning.courses')],
                       ['label' => setting('learning.paths.title'), 'url' => route('learning.paths')],
                       ['label' => $path->name_ar],
                   ]" />

    <div class="card p-4 mb-5">
        @include('learning.partials.progress-bar', ['percent' => $progress['percent']])
        <p class="text-xs mt-2" style="color: var(--text-muted)">
            {{ $progress['completed'] }} / {{ $progress['total'] }} {{ setting('learning.paths.courses_unit') }}
        </p>
    </div>

    <h2 class="text-lg font-bold mb-3">{{ setting('learning.paths.contents_title') }}</h2>

    {{-- Roadmap رأسيّ مرقّم: كلّ محطّة = كارت تدريب بترتيبه المعتمَد (3.3) --}}
    <div class="roadmap space-y-3">
        @foreach ($rows as $row)
            @php
                $course = $row['course'];
                $open = $row['availability']['open'];
            @endphp

            <article class="roadmap-node card p-4"
                     data-done="{{ $row['completed'] ? 1 : 0 }}"
                     data-current="{{ ! $row['completed'] && $row['owned'] && $open ? 1 : 0 }}">
                <div class="flex items-start gap-3 flex-wrap">
                    <span class="text-xs rounded-full px-2 py-0.5 shrink-0"
                          style="background: var(--surface-sunken); color: var(--text-muted)">{{ $row['order'] }}</span>

                    <div class="flex-1 min-w-40">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if ($row['owned'])
                                <a href="{{ route('learning.course', $course) }}" class="font-bold hover:underline">{{ $course->name_ar }}</a>
                            @else
                                <span class="font-bold">{{ $course->name_ar }}</span>
                            @endif

                            @if ($row['completed'])
                                <x-state-badge state="ok" :label="setting('learning.status.completed')" />
                            @elseif (! $row['owned'])
                                <x-state-badge state="idle" :label="setting('learning.paths.not_owned')" />
                            @endif
                        </div>

                        @if ($course->description_ar)
                            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ \Illuminate\Support\Str::limit($course->description_ar, 120) }}</p>
                        @endif

                        @if ($row['owned'])
                            <div class="mt-2">
                                @include('learning.partials.progress-bar', ['percent' => $row['summary']['percent'], 'compact' => true])
                            </div>
                        @endif

                        {{-- غير المتاح يظهر بحالته وسببه، لا يُخفى (24.5) --}}
                        @unless ($open)
                            <p class="text-xs mt-2 flex items-center gap-1"
                               style="color: var(--color-state-{{ state_color($row['availability']['state'])['color'] }})">
                                <span aria-hidden="true">{{ setting('learning.icon.lock') }}</span>
                                <span>{{ $row['availability']['reason'] }}</span>
                            </p>
                        @endunless
                    </div>

                    @if ($row['owned'])
                        <a href="{{ route('learning.course', $course) }}"
                           class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard shrink-0"
                           style="background: {{ $open ? 'var(--color-brand-500)' : 'var(--surface-sunken)' }};
                                  color: {{ $open ? '#04201c' : 'var(--text-muted)' }}">
                            {{ $row['completed'] ? setting('learning.cta.review') : ($open ? setting('learning.cta.continue') : setting('learning.cta.view_state')) }}
                        </a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    {{-- بلوك شهادة المسار — والزرّ بعد 100% فقط، وقبلها بارٌ صامت (24.5) --}}
    <section class="card p-4 mt-5">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h3 class="font-bold">{{ setting('learning.paths.certificate_block_title') }}</h3>
                <p class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ $progress['unlocked_exam'] ? setting('learning.paths.exam_ready_hint') : setting('learning.paths.exam_locked_hint') }}
                </p>
            </div>

            @if ($progress['unlocked_exam'] && $exam['exists'] && $exam['url'])
                <a href="{{ $exam['url'] }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('learning.paths.exam_cta') }} — {{ $exam['price'] }} {{ setting('learning.coins.suffix') }}
                </a>
            @elseif ($certificate['exists'])
                <x-state-badge :state="$certificate['state']" :label="$certificate['label']" />
            @endif
        </div>
    </section>
@endsection
