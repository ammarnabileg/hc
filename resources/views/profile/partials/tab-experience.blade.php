@php
    // «خبراتي» = الـCV معروضًا بشكل احترافيّ (القسم 9 · 10.0-أ)
    $canSee = $visibility->canSee('experience', $viewer, $owner, $level);
    $data = $experience['data'];
@endphp

@if (! $canSee)
    <x-empty message="الخبرات مش متاحة على البروفايل ده." />
@elseif (! $experience['cv'])
    <x-empty message="لسّه مفيش سيرة ذاتيّة هنا."
             :action="$isOwner ? 'ابدأ سيرتك' : null"
             :href="$isOwner && \Illuminate\Support\Facades\Route::has('cv.index') ? route('cv.index') : null" />
@else
    <div class="space-y-3">
        @if (! empty($data['summary']))
            <section class="card p-4">
                <h2 class="font-bold text-sm mb-2">نبذة</h2>
                <p class="text-sm leading-7">{{ $data['summary'] }}</p>
            </section>
        @endif

        @foreach ([
            'experiences' => 'الخبرات',
            'education' => 'التعليم',
            'skills' => 'المهارات',
            'languages' => 'اللغات',
        ] as $key => $label)
            @if (! empty($data[$key]) && is_array($data[$key]))
                <section class="card p-4">
                    <h2 class="font-bold text-sm mb-2">{{ $label }}</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach ($data[$key] as $row)
                            <li class="flex items-start gap-2">
                                <span aria-hidden="true" style="color: var(--color-brand-500)">•</span>
                                <span>{{ is_array($row) ? implode(' — ', array_filter($row, 'is_scalar')) : $row }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach

        <p class="text-xs" style="color: var(--text-muted)">اكتمال السيرة {{ $experience['cv']->completion_percent }}%</p>
    </div>
@endif
