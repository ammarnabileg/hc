@extends('layouts.app')
@section('title', setting('learning.paths.title'))

@push('head')
    @include('learning.partials.styles')
@endpush

@section('content')
    @php
        $storeUrl = \Illuminate\Support\Facades\Route::has('store.index') ? route('store.index') : null;
    @endphp

    <x-page-header :title="setting('learning.paths.title')"
                   :subtitle="$counts['active'].' '.setting('learning.status.active').' · '.$counts['completed'].' '.setting('learning.status.completed')"
                   :breadcrumbs="[
                       ['label' => setting('learning.breadcrumb.root'), 'url' => route('learning.courses')],
                       ['label' => setting('learning.paths.title')],
                   ]" />

    <x-filters :action="route('learning.paths')">
        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('learning.filter.status') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('learning.filter.all') }}</option>
                <option value="active" @selected($filters['status'] === 'active')>{{ setting('learning.status.active') }}</option>
                <option value="completed" @selected($filters['status'] === 'completed')>{{ setting('learning.status.completed') }}</option>
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
    </x-filters>

    @if ($cards === [])
        <x-empty :message="setting('learning.empty.paths')"
                 :action="$storeUrl ? setting('learning.empty.paths_cta') : null"
                 :href="$storeUrl" />
    @else
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as $card)
                @php
                    $path = $card['path'];
                    $progress = $card['progress'];
                    $cover = $path->cover_path ? \Illuminate\Support\Facades\Storage::url($path->cover_path) : null;
                @endphp

                <article class="card overflow-hidden animate-fadeup flex flex-col">
                    <a href="{{ route('learning.path', $path) }}" class="block motion-standard hover:opacity-90">
                        <div class="aspect-[16/9] w-full flex items-center justify-center" style="background: var(--surface-sunken)">
                            @if ($cover)
                                <img src="{{ $cover }}" alt="{{ $path->name_ar }}" class="w-full h-full object-cover" loading="lazy">
                            @else
                                <span style="color: var(--text-muted)"><x-icon :name="setting('learning.icon.path', 'path')" size="36" /></span>
                            @endif
                        </div>
                    </a>

                    <div class="p-4 flex flex-col gap-3 flex-1">
                        <a href="{{ route('learning.path', $path) }}" class="font-bold hover:underline">{{ $path->name_ar }}</a>

                        <p class="text-xs" style="color: var(--text-muted)">
                            {{ $progress['total'] }} {{ setting('learning.paths.courses_unit') }}
                            · {{ $progress['completed'] }} {{ setting('learning.status.completed') }}
                        </p>

                        @include('learning.partials.progress-bar', ['percent' => $progress['percent']])

                        <div class="mt-auto pt-1">
                            {{--
                              ⭐ [امتحان شهادة المسار] بسعره بالكوينز يظهر بعد 100% فقط،
                              وقبلها بارٌ صامت بلا CTA — لا تلويح بما لم يُستحقّ بعد.
                            --}}
                            @if ($progress['unlocked_exam'] && $card['exam']['exists'] && $card['exam']['url'])
                                <a href="{{ $card['exam']['url'] }}"
                                   class="btn inline-flex items-center justify-center w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                   style="background: var(--color-brand-500); color: #04201c">
                                    {{ setting('learning.paths.exam_cta') }} — {{ $card['exam']['price'] }} {{ setting('learning.coins.suffix') }}
                                </a>
                            @elseif ($card['certificate']['exists'])
                                <x-state-badge :state="$card['certificate']['state']" :label="$card['certificate']['label']" />
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
