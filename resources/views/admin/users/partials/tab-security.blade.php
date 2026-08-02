@php
    /*
     | تاب الأمان (12.1): **رابط مباشر لتغيير كلمة المرور قابل للنسخ بضغطة** —
     | لأنّ فريق الدعم بيقابل ناسًا كتير نسيت كلمة السرّ — ومعه **الجلسات النشطة**
     | وزرّ إنهائها كلّها. وكلّ فعل بصلاحيّته، وما لا يملكه المشاهد يُخفى (2.15-أ-7).
     */
    $actions = collect($directory->securityActions(auth()->user(), $user))->keyBy('key');
@endphp

<div class="grid gap-4 lg:grid-cols-2">

    {{-- رابط تغيير كلمة السرّ — يظهر مرّة واحدة بعد توليده بزرّ نسخ (12.1-الأمان) --}}
    @if ($actions->has('password-link'))
        <section class="card p-4">
            <h3 class="font-bold text-sm mb-1">رابط تغيير كلمة السرّ</h3>
            <p class="text-xs mb-3" style="color: var(--text-muted)">
                {{ setting('admin.moderation.link_hint', 'الرابط ده بيظهر مرّة واحدة — انسخه دلوقتي.') }}
            </p>

            @if (session('moderation_password_link'))
                <div class="flex items-center gap-2 mb-3">
                    <input type="text" readonly value="{{ session('moderation_password_link') }}"
                           data-copy-source
                           class="w-full min-w-0 rounded-xl px-3 py-2 text-xs"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)" dir="ltr">
                    <button type="button" data-copy-button
                            class="btn shrink-0 rounded-xl px-3 py-2 text-xs font-semibold motion-standard"
                            style="min-block-size: 44px; background: var(--color-brand-500); color:#04201c">نسخ</button>
                </div>
            @endif

            <form method="post" action="{{ route('admin.users.password-link', $user) }}">
                @csrf
                <button class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                        style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    {{ $actions['password-link']['label'] }}
                </button>
            </form>

            @if ($actions->has('verify-email'))
                <form method="post" action="{{ route('admin.users.verify-email', $user) }}" class="mt-2">
                    @csrf
                    <button class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                            style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        {{ $actions['verify-email']['label'] }}
                    </button>
                </form>
            @endif
        </section>
    @endif

    {{-- الجلسات النشطة + إنهاء كلّ الجلسات (12.1-متقدّم-4) --}}
    @if ($actions->has('sessions'))
        <section class="card p-4">
            <h3 class="font-bold text-sm mb-1">الجلسات النشطة</h3>
            <p class="text-xs mb-3" style="color: var(--text-muted)">
                {{ setting('admin.users.sessions_hint', 'الأجهزة المفتوح عليها الحساب دلوقتي — وإنهاء الجلسات بيقفلها كلّها.') }}
            </p>

            @if ($devices->isEmpty())
                <p class="text-sm mb-3" style="color: var(--text-muted)">مافيش جلسات مفتوحة.</p>
            @else
                <ul class="divide-y mb-3" style="border-color: var(--border)">
                    @foreach ($devices as $device)
                        <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                            <span class="flex-1 min-w-0 truncate">{{ $device->device_label ?? $device->user_agent ?? 'جهاز غير معروف' }}</span>
                            <span class="text-xs" style="color: var(--text-muted)">{{ $device->last_active_at?->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="post" action="{{ route('admin.users.sessions.end', $user) }}">
                @csrf
                <button class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                        style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    {{ $actions['sessions']['label'] }}
                </button>
            </form>
        </section>
    @endif
</div>

@push('scripts')
    <script>
        /* نسخ رابط تغيير كلمة السرّ — وردّ فوريّ على الزرّ (2.17-أ) */
        (function () {
            const field = document.querySelector('[data-copy-source]');
            const button = document.querySelector('[data-copy-button]');
            if (!field || !button) return;

            button.addEventListener('click', function () {
                field.select();
                field.setSelectionRange(0, field.value.length);

                const done = () => {
                    const label = button.textContent;
                    button.textContent = 'اتنسخ ✓';
                    setTimeout(() => { button.textContent = label; }, 2000);
                };

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(field.value).then(done, done);
                } else {
                    document.execCommand('copy');
                    done();
                }
            });
        })();
    </script>
@endpush
