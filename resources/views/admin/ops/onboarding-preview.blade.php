@extends('layouts.app')

@section('title', 'معاينة رحلة الترحيب')

@section('content')
    <x-page-header title="معاينة كما يراها المستخدم"
                   :subtitle="$journey['label']"
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'محتوى الـOnboarding', 'url' => route('admin.ops.onboarding')],
                       ['label' => 'المعاينة'],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.ops.onboarding', ['screen' => $screen]) }}"
               class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">رجوع للتحرير</a>
        </x-slot:action>
    </x-page-header>

    <div class="flex gap-2 min-w-0 overflow-x-auto no-scrollbar mb-4">
        @foreach ($screens as $key => $label)
            <a href="{{ route('admin.ops.onboarding.preview', ['screen' => $key]) }}"
               class="shrink-0 rounded-xl px-3 py-2 text-sm motion-standard"
               style="{{ $screen === $key
                    ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                    : 'background: var(--surface-raised); color: var(--text)' }}">{{ $label }}</a>
        @endforeach
    </div>

    @unless ($journey['is_enabled'])
        <div class="card p-3 mb-4 text-sm flex items-center gap-2">
            <x-state-badge state="warn" label="موقوفة" />
            <span>الشاشة دي مش مفعّلة دلوقتي — المستخدم مش هيشوفها لحدّ ما تفعّلها.</span>
        </div>
    @endunless

    @if (empty($journey['stages']))
        <x-empty message="مافيش مراحل مفعَّلة نعرضها — ضيف مرحلة الأوّل."
                 action="رجوع للتحرير" :href="route('admin.ops.onboarding', ['screen' => $screen])" />
    @else
        {{-- ⭐ نفس البوب-أب بمراحله الذي سيراه المستخدم — لا رسمًا تقريبيًّا له (2.15-د) --}}
        <div class="card p-4 md:p-8" style="background: var(--surface-sunken)">
            <div class="mx-auto w-full max-w-md card overflow-hidden animate-fadeup" data-tour>
                @foreach ($journey['stages'] as $index => $stage)
                    <div class="px-5 py-6 {{ $index === 0 ? '' : 'hidden' }}" data-stage="{{ $index }}">
                        @if ($stage->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($stage->image_path) }}"
                                 alt="" class="w-full rounded-xl mb-4" style="max-height: 220px; object-fit: cover">
                        @endif

                        <h2 class="text-lg font-extrabold">{{ $stage->title_ar }}</h2>
                        @if ($stage->body_ar)
                            <p class="text-sm mt-2" style="color: var(--text-muted)">{{ $stage->body_ar }}</p>
                        @endif

                        @if ($stage->action_label)
                            <span class="inline-flex mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                                  style="background: var(--surface-raised)">{{ $stage->action_label }}</span>
                        @endif
                    </div>
                @endforeach

                <div class="px-5 py-4 flex items-center justify-between gap-3" style="border-top: 1px solid var(--border)">
                    {{-- مؤشّر المراحل: نقاط بأرقام — الرمز مع اللون دائمًا (2.16-ب) --}}
                    <div class="flex items-center gap-1 text-xs" style="color: var(--text-muted)">
                        <span data-current>1</span> / {{ count($journey['stages']) }}
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised); min-height: 44px" data-tour-skip>
                            {{ $journey['skip_label'] }}
                        </button>
                        <button type="button" class="rounded-xl px-3 py-2 text-sm hidden" style="background: var(--surface-raised); min-height: 44px" data-tour-back>
                            {{ $journey['back_label'] }}
                        </button>
                        <button type="button" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c; min-height: 44px" data-tour-next>
                            {{ count($journey['stages']) > 1 ? $journey['next_label'] : $journey['done_label'] }}
                        </button>
                    </div>
                </div>
            </div>

            <p class="text-xs text-center mt-4" style="color: var(--text-muted)">{{ $journey['replay_hint'] }}</p>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        // المعاينة تتحرّك فعلًا: نفس تسلسل المراحل الذي سيمرّ به المستخدم
        (function () {
            const tour = document.querySelector('[data-tour]');
            if (!tour) return;

            const stages = [...tour.querySelectorAll('[data-stage]')];
            const next = tour.querySelector('[data-tour-next]');
            const back = tour.querySelector('[data-tour-back]');
            const skip = tour.querySelector('[data-tour-skip]');
            const current = tour.querySelector('[data-current]');
            const labels = @json(['next' => $journey['next_label'] ?? 'التالي', 'done' => $journey['done_label'] ?? 'يلا نبدأ']);
            let index = 0;

            const render = () => {
                stages.forEach((stage, i) => stage.classList.toggle('hidden', i !== index));
                if (current) current.textContent = index + 1;
                if (back) back.classList.toggle('hidden', index === 0);
                if (next) next.textContent = index === stages.length - 1 ? labels.done : labels.next;
            };

            next?.addEventListener('click', () => {
                if (index < stages.length - 1) { index += 1; render(); return; }
                index = 0; render();
            });
            back?.addEventListener('click', () => { if (index > 0) { index -= 1; render(); } });
            skip?.addEventListener('click', () => { index = 0; render(); });
        })();
    </script>
@endpush
