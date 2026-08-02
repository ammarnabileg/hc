{{-- «شاشة أوّل مرّة» على أهمّ الشاشات فقط منعًا للزحام (2.15-د) --}}
<div class="card p-3 mb-4 text-xs" style="color: var(--text-muted)">
    الشاشة المفعَّلة تظهر للمستخدم <strong>بوب-أب بمراحل</strong> أوّل زيارة، و{{ setting('onboarding.first_time.replay_hint', 'زرّ «؟» يعيد الشرح وقت ما تحبّ.') }}
</div>

<form method="post" action="{{ route('admin.ops.onboarding.first-time') }}" class="card p-4 space-y-3">
    @csrf

    @foreach ($screens as $key => $label)
        @php
            $count = $counts[$key]['active'] ?? 0;
            $hasTemplate = isset($templates[$key]);
        @endphp
        <label class="flex items-start gap-3 p-2 rounded-xl" style="background: var(--surface-sunken); min-height: 44px">
            <input type="checkbox" name="screens[]" value="{{ $key }}" class="mt-1"
                   @checked(in_array($key, $enabled, true))>
            <span class="min-w-0">
                <span class="block text-sm font-semibold">{{ $label }}</span>
                <span class="block text-xs mt-1" style="color: var(--text-muted)">
                    {{ $count }} مرحلة مفعَّلة
                    @if ($hasTemplate) · <x-icon name="game" size="16" /> فيه قالب جاهز @endif
                    @if ($count === 0) · محتاجة محتوى قبل ما تفعّلها @endif
                </span>
            </span>
            <span class="ms-auto shrink-0">
                <x-state-badge :state="$count > 0 ? 'ok' : 'warn'" :label="$count > 0 ? 'جاهزة' : 'فاضية'" />
            </span>
        </label>
    @endforeach

    @can('onboarding.edit')
        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">حفظ الشاشات المفعَّلة</button>
    @endcan
</form>

<div class="card p-4 mt-4">
    <h2 class="text-sm font-bold mb-2">القوالب الجاهزة</h2>
    <p class="text-xs mb-3" style="color: var(--text-muted)">القالب نقطة بداية — بيتضاف كمراحل تقدر تعدّلها أو تمسحها.</p>

    <div class="space-y-2">
        @forelse ($templates as $key => $stages)
            <div class="flex items-center justify-between gap-2 p-2 rounded-xl" style="background: var(--surface-sunken)">
                <span class="text-sm min-w-0 truncate">{{ $screens[$key] ?? $key }} · {{ count($stages) }} مرحلة</span>
                @can('onboarding.create')
                    <form method="post" action="{{ route('admin.ops.onboarding.template') }}" class="shrink-0">
                        @csrf
                        <input type="hidden" name="screen" value="{{ $key }}">
                        <button class="rounded-xl px-3 py-2 text-xs" style="background: var(--surface-raised); min-height: 44px">استخدم القالب</button>
                    </form>
                @endcan
            </div>
        @empty
            <p class="text-sm" style="color: var(--text-muted)">مافيش قوالب جاهزة لسه — ضيفها من إعدادات الـOnboarding.</p>
        @endforelse
    </div>
</div>
