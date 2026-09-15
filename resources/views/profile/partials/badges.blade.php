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
    @if ($ambassadorTitle !== '')
        {{-- لقب السفير: شرف لا حالة — ولذلك بالذهبيّ مع رمز (2.16) --}}
        <span class="status honor">
            <x-icon name="award" size="14" /> {{ $ambassadorTitle }}
        </span>
    @endif

    @if ($badges['earned']->isEmpty() && $badges['locked']->isEmpty())
        <p class="small muted">{{ setting('account.profile.badges.empty_message', 'لسّه بدري — أوّل شارة مستنّياك.') }}</p>
    @else
        {{-- شريط الشارات — حرفيًّا من ملف الهويّة (`.badge-strip`): مفتوحة بارزة، مقفولة باهتة برمز قفل --}}
        <div class="badge-strip">
            @foreach ($badges['earned'] as $badge)
                <div>
                    <button type="button" data-badge-open
                            data-badge-name="{{ $badge->name_ar }}"
                            data-badge-desc="{{ $badge->condition_text_ar }}"
                            data-badge-icon="{{ $badge->icon_path ? \Illuminate\Support\Facades\Storage::url($badge->icon_path) : '' }}"
                            data-badge-locked="0" class="badge motion-standard"
                            aria-label="{{ $badge->name_ar }} — {{ setting('account.profile.badges.unlocked_label', 'مفتوحة') }}">
                        @if ($badge->icon_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($badge->icon_path) }}"
                                 alt="{{ $badge->name_ar }}" loading="lazy" class="w-full h-full object-cover rounded-full">
                        @else
                            <x-icon name="badge" size="26" />
                        @endif
                    </button>
                    <div><strong>{{ $badge->name_ar }}</strong></div>
                </div>
            @endforeach

            @foreach ($badges['locked'] as $badge)
                <div class="locked">
                    <button type="button" data-badge-open
                            data-badge-name="{{ $badge->name_ar }}"
                            data-badge-desc="{{ $badge->condition_text_ar }}"
                            data-badge-icon="{{ $badge->icon_path ? \Illuminate\Support\Facades\Storage::url($badge->icon_path) : '' }}"
                            data-badge-locked="1" class="badge motion-standard"
                            aria-label="{{ $badge->name_ar }} — {{ setting('account.profile.badges.locked_label', 'مقفولة') }}">
                        <x-icon name="lock" size="24" />
                    </button>
                    <div><strong>{{ $badge->name_ar }}</strong></div>
                </div>
            @endforeach
        </div>
    @endif

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
