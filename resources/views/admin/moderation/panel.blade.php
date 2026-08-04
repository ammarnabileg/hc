@php
    /*
     | أدوات احتواء الحساب المسيء (12.1) — داخل تاب «متقدّم» في صفحة المستخدم.
     | كلّ فعل بصلاحيّته على المسار، و**ما لا يملكه المشاهد يُخفى** لا يُعطَّل (2.15-أ-7).
     */
    $viewer = auth()->user();
    $actions = collect($directory->moderationActions($viewer, $user))->keyBy('key');
    $contained = in_array($user->status, ['banned', 'suspended'], true);
@endphp

@if ($actions->isNotEmpty())
    <section class="card p-4 mt-4 lg:col-span-2" style="border-color: var(--color-state-warn)">
        <div class="flex flex-wrap items-center gap-2 mb-1">
            <x-state-badge state="warn" :label="setting('admin.moderation.badge', 'احتواء الحساب')" />
            <h3 class="font-bold text-sm">{{ setting('admin.moderation.title', 'أدوات الاحتواء') }}</h3>
        </div>

        <p class="text-xs mb-4" style="color: var(--text-muted)">
            {{ setting('admin.moderation.hint', 'كلّ فعل هنا بيتسجّل في سجلّ التدقيق باسمك ووقته.') }}
        </p>

        {{-- الحالة الحاليّة: السبب الظاهر للمستخدم ومتى ينتهي التعليق --}}
        @if ($contained)
            <div class="rounded-xl px-3 py-2 mb-4 text-sm" style="background: var(--surface-sunken)">
                <span class="font-semibold">{{ \App\Services\Admin\UserDirectory::STATUSES[$user->status] ?? $user->status }}</span>
                @if ($user->containment_reason)
                    — {{ $user->containment_reason }}
                @endif
                @if ($user->suspended_until)
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">
                        {{ setting('admin.moderation.panel.byrja_tlqayya', 'بيرجع تلقائيًّا') }} {{ \Illuminate\Support\Carbon::parse($user->suspended_until)->translatedFormat('Y-m-d H:i') }}
                    </span>
                @endif
            </div>
        @endif

        <div class="grid gap-3 md:grid-cols-2">
            {{-- حظر بحالة ورسالة --}}
            @if ($actions->has('ban'))
                <form method="post" action="{{ route('admin.users.ban', $user) }}" class="space-y-2">
                    @csrf
                    <label class="block text-xs" style="color: var(--text-muted)">{{ setting('admin.moderation.panel.sbb_alhzr_hyshwfh_almstkhdm', 'سبب الحظر (هيشوفه المستخدم)') }}</label>
                    <input list="moderation-reasons" name="reason" required
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <button class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-state-danger); color:#fff">{{ $actions['ban']['label'] }}</button>
                </form>
            @endif

            {{-- تعليق مؤقّت بمدّة — بيرجع لوحده بعدها --}}
            @if ($actions->has('suspend'))
                <form method="post" action="{{ route('admin.users.suspend', $user) }}" class="space-y-2">
                    @csrf
                    <label class="block text-xs" style="color: var(--text-muted)">{{ setting('admin.moderation.panel.sbb_altalyq_walmda_balayam', 'سبب التعليق والمدّة بالأيّام') }}</label>
                    <div class="flex gap-2">
                        <input list="moderation-reasons" name="reason" required
                               class="w-full min-w-0 rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <input type="number" name="days" min="1"
                               max="{{ (int) setting('admin.moderation.suspend_max_days', 90) }}"
                               value="{{ (int) setting('admin.moderation.suspend_default_days', 7) }}" required
                               class="shrink-0 rounded-xl px-3 py-2 text-sm" style="inline-size: 5.5rem; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </div>
                    <button class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-state-warn); color:#241d00">{{ $actions['suspend']['label'] }}</button>
                </form>
            @endif

            <datalist id="moderation-reasons">
                @foreach (app(\App\Services\Security\UserModeration::class)->reasons() as $reason)
                    <option value="{{ $reason }}"></option>
                @endforeach
            </datalist>

            {{-- الأفعال المباشرة: كلّ واحد فورم مستقلّ بمساحة لمس 44×44 على الموبايل --}}
            @foreach (['release' => 'admin.users.release', 'impersonate' => 'admin.users.impersonate'] as $key => $route)
                @if ($actions->has($key))
                    <form method="post" action="{{ route($route, $user) }}" class="self-end">
                        @csrf
                        <button class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                                style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            {{ $actions[$key]['label'] }}
                        </button>
                    </form>
                @endif
            @endforeach

            {{-- تصدير بيانات المستخدم كملفّ — رابط تنزيل محروس بصلاحيّته (12.1-متقدّم-6) --}}
            @if ($actions->has('export'))
                <a href="{{ route('admin.users.export', $user) }}"
                   class="btn self-end inline-flex items-center justify-center w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                   style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    {{ $actions['export']['label'] }}
                </a>
            @endif
        </div>
    </section>
@endif
