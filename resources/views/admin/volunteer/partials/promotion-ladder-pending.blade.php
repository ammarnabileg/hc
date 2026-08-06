{{--
  سلّم الترقية الفوريّ (القسم 0 · 23-0.2): بانتظار الاعتماد — قائمو أعمال
  الدايركتور (اعتماد/ردّ بمبرّر)، وقرارات معلَّقة نوعان: تعادلٌ كامل (حسم
  بمرشّح من القائمة) وشغور مشرف عام مسار (حسم بكودٍ مباشر أو مرشّح السلّم).
  المتغيّران المتوقَّعان: $actingApprovals, $pendingDecisions.
--}}
@php
    $tieDecisions = $pendingDecisions->where('kind', 'tie');
    $trackDecisions = $pendingDecisions->where('kind', 'track_vacancy');
@endphp

@if ($actingApprovals->isNotEmpty() || $pendingDecisions->isNotEmpty())
    <section class="card p-4 md:p-5 mb-4">
        <h2 class="font-bold mb-3">{{ setting('admin.volunteer.org.promotion_ladder.pending_title', 'سلّم الترقية — بانتظار الاعتماد') }}</h2>

        @foreach ($actingApprovals as $membership)
            <div class="flex items-center justify-between gap-3 py-2 text-sm border-b" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $membership->user?->name }} <x-state-badge state="warn" :label="setting('admin.volunteer.org.promotion_ladder.acting_badge', 'قائم بأعمال')" /></div>
                    <div class="text-xs" style="color: var(--text-muted)">
                        {{ $membership->position?->name_ar }} · {{ $membership->entity?->name_ar }} · {{ setting('admin.volunteer.org.promotion_ladder.since', 'منذ') }} {{ $membership->started_at?->diffForHumans() }}
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @can('vacancies.approve')
                        <form method="post" action="{{ route('admin.volunteer.org.promotion-ladder.confirm', $membership) }}">
                            @csrf
                            <button type="submit" class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.org.promotion_ladder.confirm', 'اعتماد') }}</button>
                        </form>
                    @endcan
                    @can('promotion_ladder.reject')
                        <button type="button" class="text-xs underline" style="color: var(--color-state-danger)"
                                data-reject-acting data-id="{{ $membership->id }}">{{ setting('admin.volunteer.org.promotion_ladder.reject', 'ردّ') }}</button>
                    @endcan
                </div>
            </div>
        @endforeach

        @foreach ($tieDecisions as $decision)
            <div class="flex items-center justify-between gap-3 py-2 text-sm border-b" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ setting('admin.volunteer.org.promotion_ladder.tie_title', 'تعادلٌ كامل') }} — {{ $decision->position?->name_ar }} · {{ $decision->entity?->name_ar }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">
                        {{ setting('admin.volunteer.org.promotion_ladder.tie_candidates', 'المرشّحون:') }}
                        {{ \App\Models\User::whereIn('id', (array) $decision->candidate_user_ids)->pluck('name')->join('، ') }}
                    </div>
                </div>
                @can('promotion_ladder.approve')
                    <button type="button" class="btn rounded-xl px-3 py-1.5 text-xs font-semibold shrink-0"
                            style="background: var(--color-brand-500); color: #04201c"
                            data-decide-tie data-id="{{ $decision->id }}"
                            data-candidates='@json(\App\Models\User::whereIn("id", (array) $decision->candidate_user_ids)->get(["id", "name"]))'>{{ setting('admin.volunteer.org.promotion_ladder.decide', 'احسم') }}</button>
                @endcan
            </div>
        @endforeach

        {{-- شغور مشرف عام المسار (23-0.2): مساران لا فرق إجرائيًّا بينهما — كودٌ يُبحَث عنه --}}
        @foreach ($trackDecisions as $decision)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ setting('admin.volunteer.org.promotion_ladder.track_title', 'شغور مشرف عام مسار') }} — {{ $decision->entity?->name_ar }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">
                        {{ setting('admin.volunteer.org.promotion_ladder.track_candidates', 'مرشّحو السلّم من دايركتورات المسار:') }}
                        {{ \App\Models\User::whereIn('id', (array) $decision->candidate_user_ids)->pluck('name')->join('، ') ?: setting('admin.volunteer.org.promotion_ladder.track_no_candidates', '— لا مرشّح، والملء بكودٍ مباشر فقط.') }}
                    </div>
                </div>
                @can('promotion_ladder.approve')
                    <button type="button" class="btn rounded-xl px-3 py-1.5 text-xs font-semibold shrink-0"
                            style="background: var(--color-brand-500); color: #04201c"
                            data-resolve-track data-id="{{ $decision->id }}"
                            data-candidates='@json(\App\Models\User::whereIn("id", (array) $decision->candidate_user_ids)->get(["code", "name"]))'>{{ setting('admin.volunteer.org.promotion_ladder.track_fill', 'املأه') }}</button>
                @endcan
            </div>
        @endforeach
    </section>
@endif

@push('modals')
    @can('promotion_ladder.reject')
        <x-modal id="reject-acting-modal" :title="setting('admin.volunteer.org.promotion_ladder.reject_title', 'ردّ اعتماد القائم بأعمال')">
            <form method="post" action="{{ route('admin.volunteer.org.promotion-ladder.reject', 0) }}" id="reject-acting-form">
                @csrf
                <label class="block text-sm font-semibold mb-1" for="reject-acting-reason">{{ setting('admin.volunteer.org.promotion_ladder.reject_reason', 'المبرّر') }}</label>
                <textarea name="reason" id="reject-acting-reason" rows="3" required minlength="10" maxlength="500"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-state-danger); color: #fff">{{ setting('admin.volunteer.org.promotion_ladder.reject', 'ردّ') }}</button>
            </form>
        </x-modal>
    @endcan

    @can('promotion_ladder.approve')
        <x-modal id="decide-tie-modal" :title="setting('admin.volunteer.org.promotion_ladder.decide_title', 'حسم تعادل — قرار موثّق')">
            <form method="post" action="{{ route('admin.volunteer.org.promotion-ladder.decide', 0) }}" id="decide-tie-form">
                @csrf
                <label class="block text-sm font-semibold mb-1" for="decide-tie-winner">{{ setting('admin.volunteer.org.promotion_ladder.decide_winner', 'مين يترقّى؟') }}</label>
                <select name="winner_user_id" id="decide-tie-winner" required class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></select>

                <label class="block text-sm font-semibold mb-1" for="decide-tie-reason">{{ setting('admin.volunteer.org.promotion_ladder.decide_reason', 'المبرّر') }}</label>
                <textarea name="reason" id="decide-tie-reason" rows="3" required minlength="10" maxlength="500"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.org.promotion_ladder.decide', 'احسم') }}</button>
            </form>
        </x-modal>

        {{-- ⭐ مساران بحقلٍ واحد: اختر من القائمة (مرشّحو السلّم) أو اكتب أيّ كودٍ آخر مباشرةً (23-0.2) --}}
        <x-modal id="resolve-track-modal" :title="setting('admin.volunteer.org.promotion_ladder.track_modal_title', 'ملء شغور مشرف عام مسار')">
            <form method="post" action="{{ route('admin.volunteer.org.promotion-ladder.resolve-track', 0) }}" id="resolve-track-form">
                @csrf
                <label class="block text-sm font-semibold mb-1" for="resolve-track-code">{{ setting('admin.volunteer.org.promotion_ladder.track_code_label', 'كود المرشَّح — اختر من القائمة أو اكتب كودًا آخر') }}</label>
                <input type="text" name="winner_code" id="resolve-track-code" list="resolve-track-candidates" required
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <datalist id="resolve-track-candidates"></datalist>

                <label class="block text-sm font-semibold mb-1" for="resolve-track-reason">{{ setting('admin.volunteer.org.promotion_ladder.decide_reason', 'المبرّر') }}</label>
                <textarea name="reason" id="resolve-track-reason" rows="3" required minlength="10" maxlength="500"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.org.promotion_ladder.track_fill', 'املأه') }}</button>
            </form>
        </x-modal>
    @endcan
@endpush

@push('scripts')
    <script>
        document.addEventListener('click', (e) => {
            const rejectBtn = e.target.closest('[data-reject-acting]');
            if (rejectBtn) {
                const form = document.getElementById('reject-acting-form');
                form.action = '{{ route('admin.volunteer.org.promotion-ladder.reject', 0) }}'.replace(/0$/, rejectBtn.dataset.id);
                const modal = document.getElementById('reject-acting-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                return;
            }

            const decideBtn = e.target.closest('[data-decide-tie]');
            if (decideBtn) {
                const form = document.getElementById('decide-tie-form');
                form.action = '{{ route('admin.volunteer.org.promotion-ladder.decide', 0) }}'.replace(/0$/, decideBtn.dataset.id);

                const select = document.getElementById('decide-tie-winner');
                select.innerHTML = '';
                (JSON.parse(decideBtn.dataset.candidates || '[]')).forEach((c) => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    select.appendChild(opt);
                });

                const modal = document.getElementById('decide-tie-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                return;
            }

            const trackBtn = e.target.closest('[data-resolve-track]');
            if (trackBtn) {
                const form = document.getElementById('resolve-track-form');
                form.action = '{{ route('admin.volunteer.org.promotion-ladder.resolve-track', 0) }}'.replace(/0$/, trackBtn.dataset.id);

                const list = document.getElementById('resolve-track-candidates');
                list.innerHTML = '';
                (JSON.parse(trackBtn.dataset.candidates || '[]')).forEach((c) => {
                    const opt = document.createElement('option');
                    opt.value = c.code;
                    opt.textContent = c.name;
                    list.appendChild(opt);
                });

                document.getElementById('resolve-track-code').value = '';
                const modal = document.getElementById('resolve-track-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        });
    </script>
@endpush
