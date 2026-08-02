@php
    // المسارات الخمسة وعتباتها من 10.1 — والمستويات مفتوحة بلا سقف بنفس المعادلة
    $canSee = $visibility->canSee('achievements', $viewer, $owner, $level);
@endphp

@if (! $canSee)
    <x-empty :message="setting('account.profile.achievements.hidden_message', 'الإنجازات مش متاحة على البروفايل ده.')" />
@else
    {{-- ⭐ بطاقة الإنجاز قابلة للاستخراج كصورة (12.14-هـ) --}}
    <div class="flex justify-end mb-3">
        <x-export-image
            kind="card"
            :title="$owner->shortName().' — '.setting('account.profile.achievements.export_title', 'إنجازاتي')"
            :subtitle="setting('account.profile.achievements.export_subtitle', 'مستوى الحساب').' '.$owner->level"
            :rows="collect($achievements)->values()->map(fn ($track, $i) => [
                'rank' => $track['level'],
                'u' => $owner->id,
                'name' => $track['label'],
                'value' => number_format($track['value']).' '.$track['unit'],
            ])->all()" />
    </div>

    <div class="space-y-3">
        @foreach ($achievements as $track)
            <section class="card p-4 animate-fadeup">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold text-sm">{{ $track['label'] }}</h2>
                    <span class="text-xs rounded-full px-2 py-0.5"
                          style="background: var(--surface-sunken); color: var(--text-muted)">{{ setting('account.profile.achievements.level_prefix', 'مستوى') }} {{ $track['level'] }}</span>
                </div>

                <div class="mt-3 h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                    <div class="h-full motion-standard" style="width: {{ $track['percent'] }}%; background: var(--color-brand-500)"></div>
                </div>

                <div class="mt-2 flex items-center justify-between text-xs" style="color: var(--text-muted)">
                    <span>{{ number_format($track['value']) }} {{ $track['unit'] }}</span>
                    <span>{{ setting('account.profile.achievements.next_prefix', 'الجاي عند') }} {{ number_format($track['next_threshold']) }}</span>
                </div>
            </section>
        @endforeach
    </div>
@endif
