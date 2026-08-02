{{-- الألعاب (24.2 · 7.5): تفعيلها وتسعيرها بالتذاكر ومكافآتها بالـXP --}}

<section class="card p-4 md:p-5">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
        <h2 class="font-bold">كتالوج الألعاب</h2>
        @can('games.create')
            <button type="button" data-modal-open="game-modal"
                    class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">+ لعبة</button>
        @endcan
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @forelse ($data['games'] as $game)
            <article class="rounded-xl p-3" style="background: var(--surface-sunken)">
                <div class="flex items-start gap-3">
                    {{-- الغلاف SVG مرسوم — بلا أيّ مكتبة أيقونات (2.16-ج) --}}
                    <span class="shrink-0 inline-flex items-center justify-center rounded-xl"
                          style="width: 42px; height: 42px; background: var(--surface); border: 1px solid var(--border)">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                             style="color: var(--color-brand-500)">
                            <rect x="2.5" y="7" width="19" height="10" rx="4" />
                            <path d="M7 10.5v3M5.5 12h3" />
                            <circle cx="16" cy="11.2" r=".9" fill="currentColor" stroke="none" />
                            <circle cx="18" cy="13.4" r=".9" fill="currentColor" stroke="none" />
                        </svg>
                    </span>

                    <div class="min-w-0">
                        <div class="font-semibold text-sm">{{ $game->name_ar }}</div>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ (int) $game->entryCost() }} <x-icon name="ticket" size="16" /> للدخول · {{ (int) $game->xp_reward }} XP
                            · {{ (int) $game->sessions_count }} جلسة
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 mt-3">
                    <x-state-badge
                        :state="['active' => 'ok', 'soon' => 'warn', 'paused' => 'idle'][$game->status] ?? 'idle'"
                        :label="$data['statuses'][$game->status] ?? $game->status" />

                    @can('games.edit')
                        <button type="button" class="text-xs underline" data-game-edit
                                data-id="{{ $game->id }}" data-key="{{ $game->key }}"
                                data-name="{{ $game->name_ar }}" data-cost="{{ $game->ticket_cost === null ? '' : (int) $game->ticket_cost }}"
                                data-xp="{{ (int) $game->xp_reward }}" data-status="{{ $game->status }}"
                                data-limit="{{ $game->daily_limit }}" data-soon="{{ $game->soon_text }}">تعديل</button>
                    @endcan
                </div>
            </article>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-empty message="لم تُضَف ألعاب بعد — القسم قابل للتوسّع." />
            </div>
        @endforelse
    </div>
</section>

{{-- تاب سجلّ الجلسات (24.2) --}}
<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-3">سجلّ الجلسات</h2>

    @if ($data['sessions']->isEmpty())
        <x-empty message="لا جلسات لعب مسجَّلة بعد." />
    @else
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="color: var(--text-muted)">
                        <th class="p-2 text-start">المستخدم</th>
                        <th class="p-2 text-start">اللعبة</th>
                        <th class="p-2 text-start">التذاكر</th>
                        <th class="p-2 text-start">XP</th>
                        <th class="p-2 text-start">التاريخ</th>
                        <th class="p-2 text-start">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['sessions'] as $session)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-2">{{ $session->user?->name }}</td>
                            <td class="p-2">{{ $session->game?->name_ar }}</td>
                            <td class="p-2 tabular-nums">{{ (int) $session->tickets_spent }}</td>
                            <td class="p-2 tabular-nums">{{ (int) $session->xp_awarded }}</td>
                            <td class="p-2">{{ $session->created_at?->diffForHumans() }}</td>
                            <td class="p-2">
                                <x-state-badge :state="$session->status === 'reversed' ? 'danger' : 'ok'"
                                               :label="$session->status === 'reversed' ? 'معكوسة' : 'سليمة'" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- على الموبايل كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="md:hidden space-y-2">
            @foreach ($data['sessions'] as $session)
                <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
                    <div class="font-semibold text-sm">{{ $session->user?->name }} — {{ $session->game?->name_ar }}</div>
                    <p class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ (int) $session->tickets_spent }} <x-icon name="ticket" size="16" /> · {{ (int) $session->xp_awarded }} XP
                        · {{ $session->created_at?->diffForHumans() }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</section>

{{-- بلوك الإعدادات (24.2) --}}
<section class="card p-4 md:p-5 mt-4">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
        <h2 class="font-bold">إعدادات قسم الألعاب</h2>
        @can('games.manage')
            <form method="post" action="{{ route('admin.gamification.games.reset') }}">
                @csrf
                <button type="submit" class="rounded-xl px-3 py-1.5 text-xs motion-standard"
                        style="background: var(--surface-sunken); color: var(--text); border: 1px solid var(--border)">
                    <x-icon name="refresh" size="16" /> Reset للافتراضيّ
                </button>
            </form>
        @endcan
    </div>

    <form method="post" action="{{ route('admin.gamification.games.settings.save') }}" class="space-y-3">
        @csrf

        @foreach ($data['settings'] as $key => $row)
            <label class="flex flex-col sm:flex-row sm:items-center gap-2">
                <span class="sm:w-72 text-sm">
                    {{ $row['label'] }}
                    @if ($row['modified'])
                        <span class="text-xs" style="color: var(--color-state-warn)">▲ معدَّل</span>
                    @endif
                </span>

                @if ($row['type'] === 'bool')
                    <select name="settings[{{ $key }}]" class="rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                        <option value="1" @selected($row['value'] === '1')>مفعّل</option>
                        <option value="0" @selected($row['value'] !== '1')>موقوف</option>
                    </select>
                @else
                    <input type="{{ $row['type'] === 'number' ? 'number' : 'text' }}"
                           name="settings[{{ $key }}]" value="{{ $row['value'] }}"
                           placeholder="{{ $row['default'] }}"
                           class="flex-1 rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                @endif
            </label>
        @endforeach

        @can('games.edit')
            <button type="submit" class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c; min-height: 44px">احفظ</button>
        @endcan
    </form>
</section>

@can('games.create')
    @push('modals')
        <x-modal id="game-modal" title="لعبة">
            <form method="post" action="{{ route('admin.gamification.games.save') }}" class="space-y-3 text-sm" id="game-form">
                @csrf
                <input type="hidden" name="id" data-field="id">

                <label class="block">
                    <span class="block text-sm mb-1">المفتاح (إنجليزيّ)</span>
                    <input type="text" name="key" required maxlength="48" data-field="key"
                           class="w-full rounded-xl px-3 py-3 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                </label>

                <label class="block">
                    <span class="block text-sm mb-1">الاسم بالعربيّة</span>
                    <input type="text" name="name_ar" required maxlength="120" data-field="name"
                           class="w-full rounded-xl px-3 py-3 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="block">
                        <span class="block text-sm mb-1">تكلفة الدخول (تذاكر)</span>
                        <input type="number" step="1" min="0" name="ticket_cost" data-field="cost"
                               {{-- الـPlaceholder هو التكلفة العامّة الحاكمة: الحقل الفارغ يعني «اتبع العامّ» لا صفر --}}
                               placeholder="{{ (int) (new \App\Models\Game)->entryCost() }}"
                               class="w-full rounded-xl px-3 py-3 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                    </label>

                    <label class="block">
                        <span class="block text-sm mb-1">مكافأة XP</span>
                        <input type="number" step="1" min="0" name="xp_reward" data-field="xp"
                               class="w-full rounded-xl px-3 py-3 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                    </label>

                    <label class="block">
                        <span class="block text-sm mb-1">حدّ يوميّ لكلّ مستخدم</span>
                        <input type="number" step="1" min="0" name="daily_limit" data-field="limit"
                               class="w-full rounded-xl px-3 py-3 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                    </label>
                </div>

                <label class="block">
                    <span class="block text-sm mb-1">الحالة</span>
                    <select name="status" data-field="status" class="w-full rounded-xl px-3 py-3 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                        @foreach ($data['statuses'] as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="block text-sm mb-1">نصّ «قريبًا»</span>
                    <input type="text" name="soon_text" maxlength="160" data-field="soon"
                           placeholder="{{ $data['settings']['games.soon_text']['value'] }}"
                           class="w-full rounded-xl px-3 py-3 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                </label>
            </form>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                            style="background: var(--surface-sunken); color: var(--text); min-height: 44px">إلغاء</button>
                    <button type="submit" form="game-form"
                            class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c; min-height: 44px">احفظ</button>
                </div>
            </x-slot:footer>
        </x-modal>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-game-edit]');
                if (!btn) return;
                const form = document.getElementById('game-form');
                const set = (field, value) => {
                    const el = form.querySelector(`[data-field="${field}"]`);
                    if (el) el.value = value ?? '';
                };
                set('id', btn.dataset.id);
                set('key', btn.dataset.key);
                set('name', btn.dataset.name);
                set('cost', btn.dataset.cost);
                set('xp', btn.dataset.xp);
                set('limit', btn.dataset.limit);
                set('status', btn.dataset.status);
                set('soon', btn.dataset.soon);
                document.querySelector('[data-modal-open="game-modal"]')?.click();
            });
        </script>
    @endpush
@endcan
