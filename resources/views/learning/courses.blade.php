@extends('layouts.app')
@section('title', setting('learning.courses.title'))

@push('head')
    @include('learning.partials.styles')
@endpush

@section('content')
    @php
        $storeUrl = \Illuminate\Support\Facades\Route::has('store.index') ? route('store.index') : null;
        $certificatesUrl = \Illuminate\Support\Facades\Route::has('learning.certificates') ? route('learning.certificates') : null;
        $resumeUrl = $resume ? route('learning.course', $resume['course']) : null;
    @endphp

    <x-page-header :title="setting('learning.courses.title')"
                   :subtitle="setting('learning.courses.subtitle').' · '.$counts['active'].' '.setting('learning.status.active').' · '.$counts['completed'].' '.setting('learning.status.completed')"
                   :breadcrumbs="[
                       ['label' => setting('learning.breadcrumb.root'), 'url' => route('learning.courses')],
                       ['label' => setting('learning.courses.title')],
                   ]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد بارز، والباقي في «⋯» (2.15-أ-2) --}}
            @if ($resumeUrl)
                <a href="{{ $resumeUrl }}"
                   class="btn hidden md:inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('learning.cta.resume') }}</a>
            @endif

            @if ($storeUrl || $certificatesUrl)
                <details class="relative">
                    <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm list-none"
                             style="background: var(--surface-raised)" aria-label="{{ setting('learning.more.label') }}">⋯</summary>
                    <div class="absolute end-0 mt-1 card p-1 min-w-44 z-40">
                        @if ($storeUrl)
                            <a href="{{ $storeUrl }}" class="block rounded-lg px-3 py-2 text-sm motion-standard">{{ setting('learning.cta.store') }}</a>
                        @endif
                        @if ($certificatesUrl)
                            <a href="{{ $certificatesUrl }}" class="block rounded-lg px-3 py-2 text-sm motion-standard">{{ setting('learning.cta.certificates') }}</a>
                        @endif
                    </div>
                </details>
            @endif
        </x-slot:action>
    </x-page-header>

    {{-- بأيّ ساعةٍ تُقاس مواعيدك؟ — سؤال يسبق كلّ «مفتوح/مقفول» (5) --}}
    <div class="mb-4">
        @include('learning.partials.timezone-chip', ['clock' => $clock, 'timezones' => $timezones])
    </div>

    {{-- ثلاثة فلاتر ظاهرة: الحالة · المسار · بحث — والباقي مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('learning.courses')">
        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('learning.filter.status') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('learning.filter.all') }}</option>
                <option value="not_started" @selected($filters['status'] === 'not_started')>{{ setting('learning.status.not_started') }}</option>
                <option value="active" @selected($filters['status'] === 'active')>{{ setting('learning.status.active') }}</option>
                <option value="completed" @selected($filters['status'] === 'completed')>{{ setting('learning.status.completed') }}</option>
            </select>
        </label>

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('learning.filter.path') }}</span>
            <select name="path" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('learning.filter.all') }}</option>
                @foreach ($paths as $path)
                    <option value="{{ $path->id }}" @selected((string) $filters['path'] === (string) $path->id)>{{ $path->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="block flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('learning.filter.search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="{{ setting('learning.filter.search_placeholder') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('learning.filter.apply') }}</button>

        <x-slot:advanced>
            <label class="block">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('learning.filter.sort') }}</span>
                <select name="sort" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="recent" @selected($filters['sort'] === 'recent')>{{ setting('learning.filter.sort_recent') }}</option>
                    <option value="name" @selected($filters['sort'] === 'name')>{{ setting('learning.filter.sort_name') }}</option>
                </select>
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($cards === [])
        {{-- الحالة الفارغة = سطر واحد + زرّ واحد (2.15-د) --}}
        <x-empty :message="setting('learning.empty.courses')"
                 :action="$storeUrl ? setting('learning.empty.courses_cta') : null"
                 :href="$storeUrl" />
    @else
        {{-- صفوف تدريبات — حرفيًّا من ملف الهويّة المرجعيّ (`courseRows()`) لا كروت شبكة --}}
        <div class="spread mb-2">
            <span class="small muted">{{ count($cards) }} {{ setting('learning.courses.count_label', 'تدريبات') }}</span>
        </div>
        <div class="stagger">
            @foreach ($cards as $i => $card)
                @php
                    $summary = $card['summary'];
                    $availability = $card['availability'];
                    $open = $availability['open'];
                    $statusLabel = [
                        'not_started' => setting('learning.status.not_started'),
                        'active' => setting('learning.status.active'),
                        'completed' => setting('learning.status.completed'),
                    ][$card['status']];
                @endphp
                <article class="course-row" style="--i: {{ $i }}">
                    <div class="thumb"><x-icon :name="setting('learning.icon.course', 'course')" size="30" /></div>

                    <div class="min-w-0">
                        <h3 class="truncate">{{ $card['course']->name_ar }}</h3>
                        @if ($summary['current_title'])
                            <p class="small truncate">{{ $summary['section_title'] }} · {{ $summary['current_title'] }}</p>
                        @endif
                        <div class="small muted mt-2">
                            {{ (int) $card['enrollment']->xp_earned }} {{ setting('learning.xp.suffix') }} · {{ $statusLabel }}
                        </div>
                    </div>

                    <div class="course-progress">
                        <div class="spread small mb-2">
                            <span>{{ setting('dashboard.overview.progress_label', 'التقدم') }}</span>
                            <b>{{ (int) $summary['percent'] }}%</b>
                        </div>
                        <div class="progress" role="progressbar" aria-valuenow="{{ (int) $summary['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                            <span style="--value: {{ (int) $summary['percent'] }}%"></span>
                        </div>
                    </div>

                    <div class="course-status">
                        @unless ($open)
                            <x-state-badge :state="$availability['state']" :label="$availability['reason']" />
                        @else
                            <x-state-badge :state="$card['deadline']['state']" :label="$card['deadline']['label']" />
                        @endunless
                        @if ($card['exam']['exists'])
                            <p class="small mt-2"><x-state-badge :state="$card['exam']['state']" :label="$card['exam']['label']" /></p>
                        @endif
                    </div>

                    <a href="{{ route('learning.course', $card['course']) }}" class="icon-button course-open"
                       aria-label="{{ $open ? setting('learning.cta.continue') : setting('learning.cta.view_state') }} {{ $card['course']->name_ar }}">
                        <x-icon :name="$open ? 'left' : 'lock'" size="18" />
                    </a>
                </article>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
    @include('learning.partials.clock-scripts')
@endpush

@if ($resume)
    @section('mobile_action')
        <a href="{{ route('learning.course', $resume['course']) }}"
           class="btn flex items-center justify-center w-full rounded-xl px-4 py-3 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('learning.cta.resume') }}</a>
    @endsection
@endif
