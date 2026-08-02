@php
    use App\Http\Controllers\Ui\ProfileExtrasController;

    /*
     | سكشن الشارات (10) — **تحت الهيدر مباشرةً**:
     | المفعَّلة بشكل مميّز وغير المفعَّلة **مقفولة**، والضغط على شارة يفتح
     | بوب-أب فيه صورتها واسمها ووصفها.
     | ⭐ ولقب السفير (7.6.1) يظهر هنا كلقبٍ بلا شارة — ألقاب لا أوسمة (2.9-8).
     */
    $badges = ProfileExtrasController::badgesFor($owner);
    $ambassadorTitle = trim((string) ($owner->ambassador_title ?? ''));
@endphp

@if ($badges['earned']->isNotEmpty() || $badges['locked']->isNotEmpty() || $ambassadorTitle !== '')
    <section class="card p-4 mb-4">
        <div class="flex items-center justify-between gap-2 mb-3">
            <h2 class="font-bold text-sm">{{ setting('account.profile.badges.title', 'الشارات') }}</h2>

            @if ($ambassadorTitle !== '')
                {{-- لقب السفير: شرف لا حالة — ولذلك بالذهبيّ مع رمز (2.16) --}}
                <span class="rounded-full px-3 py-1 text-xs font-bold"
                      style="background: color-mix(in srgb, var(--color-state-honor) 18%, transparent); color: var(--color-state-honor)">
                    ★ {{ $ambassadorTitle }}
                </span>
            @endif
        </div>

        @if ($badges['earned']->isEmpty() && $badges['locked']->isEmpty())
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('account.profile.badges.empty_message', 'لسّه بدري — أوّل شارة مستنّياك.') }}</p>
        @else
            <div class="flex flex-wrap gap-3">
                @foreach ($badges['earned'] as $badge)
                    <button type="button" data-badge-open
                            data-badge-name="{{ $badge->name_ar }}"
                            data-badge-desc="{{ $badge->condition_text_ar }}"
                            data-badge-icon="{{ $badge->icon_path ? \Illuminate\Support\Facades\Storage::url($badge->icon_path) : '' }}"
                            data-badge-locked="0"
                            class="flex flex-col items-center gap-1 motion-standard"
                            style="min-width: 64px; min-height: 44px"
                            aria-label="{{ $badge->name_ar }} — {{ setting('account.profile.badges.unlocked_label', 'مفتوحة') }}">
                        <span class="inline-flex items-center justify-center rounded-full overflow-hidden"
                              style="width: 56px; height: 56px; background: color-mix(in srgb, var(--color-state-honor) 16%, transparent)">
                            @if ($badge->icon_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($badge->icon_path) }}"
                                     alt="{{ $badge->name_ar }}" loading="lazy" class="w-full h-full object-cover">
                            @else
                                <span style="color: var(--color-state-honor)" aria-hidden="true">★</span>
                            @endif
                        </span>
                        <span class="text-[11px] text-center">{{ $badge->name_ar }}</span>
                    </button>
                @endforeach

                @foreach ($badges['locked'] as $badge)
                    <button type="button" data-badge-open
                            data-badge-name="{{ $badge->name_ar }}"
                            data-badge-desc="{{ $badge->condition_text_ar }}"
                            data-badge-icon="{{ $badge->icon_path ? \Illuminate\Support\Facades\Storage::url($badge->icon_path) : '' }}"
                            data-badge-locked="1"
                            class="flex flex-col items-center gap-1 motion-standard"
                            style="min-width: 64px; min-height: 44px"
                            aria-label="{{ $badge->name_ar }} — {{ setting('account.profile.badges.locked_label', 'مقفولة') }}">
                        <span class="inline-flex items-center justify-center rounded-full overflow-hidden"
                              style="width: 56px; height: 56px; background: var(--surface-sunken); filter: grayscale(1); opacity: .55">
                            @if ($badge->icon_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($badge->icon_path) }}"
                                     alt="" loading="lazy" class="w-full h-full object-cover">
                            @else
                                {{-- قفل SVG مرسوم داخل المشروع — الرمز مع اللون دائمًا (2.16-ب) --}}
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"
                                     style="color: var(--text-muted)">
                                    <rect x="5" y="10" width="14" height="10" rx="2" />
                                    <path d="M8 10V7a4 4 0 018 0v3" />
                                </svg>
                            @endif
                        </span>
                        <span class="text-[11px] text-center" style="color: var(--text-muted)">{{ $badge->name_ar }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </section>

    <x-modal id="badge-detail" :title="setting('account.profile.badges.modal_title', 'الشارة')">
        <div class="text-center space-y-3">
            <img data-badge-detail-icon src="" alt="" class="mx-auto rounded-full object-cover hidden"
                 style="width: 96px; height: 96px">
            <h3 data-badge-detail-name class="font-bold"></h3>
            <p data-badge-detail-desc class="text-sm" style="color: var(--text-muted)"></p>
            <p data-badge-detail-state class="text-xs"></p>
        </div>
    </x-modal>
@endif
