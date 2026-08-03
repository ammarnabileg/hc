@extends('layouts.admin')

@section('title', 'حلقات النموّ')

@section('content')
    <x-page-header title="حلقات النموّ"
                   subtitle="كلّ رقم ونصّ في حلقات النموّ والاكتساب والتتبّع — من هنا لا من الكود."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'حلقات النموّ'],
                   ]" />

    <x-tabs :current="$tab" :tabs="collect($tabs)->map(fn ($meta, $key) => [
        'key' => $key,
        'label' => $meta['label'],
        'url' => route('admin.growth.index', ['tab' => $key]),
    ])->values()->all()" />

    <p class="text-sm mb-4" style="color: var(--text-muted)">{{ $tabs[$tab]['hint'] }}</p>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 card p-4 space-y-4">
            @forelse ($settings as $setting)
                @include('admin.settings.partials.field', [
                    'setting' => $setting,
                    'registry' => $registry,
                    'endpoint' => route('admin.growth.setting.save'),
                ])
            @empty
                <x-empty message="الإعدادات دي لسّه ما اتزرعتش — شغّل سيدر مجال النموّ." />
            @endforelse
        </div>

        <aside class="space-y-4">
            @if ($tab === 'loops')
                <div class="card p-4">
                    <h2 class="font-bold text-sm mb-2">حقول «أكمل ملفك»</h2>
                    <ul class="text-xs space-y-1" style="color: var(--text-muted)">
                        @foreach ($completionFields as $key => $label)
                            <li><code>{{ $key }}</code> — {{ $label }}</li>
                        @endforeach
                    </ul>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        عدّلها من <code>growth.profile_completion.fields</code>.
                    </p>
                </div>
            @endif

            @if ($tab === 'reach')
                <div class="card p-4">
                    <h2 class="font-bold text-sm mb-2">قوالب صور الروابط</h2>
                    <ul class="text-xs space-y-1" style="color: var(--text-muted)">
                        @foreach ($ogTypes as $type)
                            @php $template = $ogRenderer->template($type); @endphp
                            <li class="flex items-center gap-2">
                                <span class="inline-block w-3 h-3 rounded-full" style="background: {{ $template['accent'] }}"></span>
                                <code>{{ $type }}</code> — {{ $template['label'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="card p-4">
                    <h2 class="font-bold text-sm mb-2">نصائح الكارت الأسبوعيّ</h2>
                    <ol class="text-xs space-y-1 list-decimal ps-4" style="color: var(--text-muted)">
                        @foreach ($tips as $tip)
                            <li>{{ \Illuminate\Support\Str::limit($tip, 70) }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if ($tab === 'ads')
                <div class="card p-4">
                    <h2 class="font-bold text-sm mb-2">الأحداث الثمانية</h2>
                    <ul class="text-xs space-y-1" style="color: var(--text-muted)">
                        @foreach ($events as $key => $meta)
                            <li class="flex items-center justify-between gap-2">
                                <span>{{ $meta['label'] }}</span>
                                <code class="opacity-70">ads.events.{{ $key }}</code>
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-xs mt-3" style="color: var(--text-muted)">
                        رفض المستخدم بيوقف البكسل وأحداث الخادم له فعليًّا — مش شكليًّا.
                        و<code>ads.tracking.enabled</code> بيوقّف التتبّع كلّه بمفتاح واحد.
                    </p>
                </div>
            @endif
        </aside>
    </div>

    @include('admin.settings.partials.autosave-script')
@endsection
