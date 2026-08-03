@extends('layouts.app')

@section('title', $package->name)

@php
    /**
     * بنود الحزمة (24.4): جدول 6 أعمدة، وعلى الموبايل كروت رأسيّة بلا تمرير أفقيّ (2.15-ج).
     * ونفس الشبكة تخدم العرضين: `md:grid-cols-6` سطر على الديسكتوب، وكارت على الموبايل.
     */
@endphp

@section('content')
    <x-page-header
        :title="$package->name"
        :subtitle="'المَعلَم الأمّ: '.($package->milestone?->name ?? '—').' · الكيان: '.($package->entity?->name_ar ?? '—')"
        :breadcrumbs="[
            ['label' => 'الأهداف والمَعالِم', 'url' => route('volunteer.goals')],
            ['label' => 'حزم العمل', 'url' => route('volunteer.packages')],
            ['label' => $package->name],
        ]">
        <x-slot:action>
            @if ($canObject)
                <button type="button" data-modal-open="version-diff"
                        class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    اعتراض على نسخة الاعتماد
                </button>
            @endif
        </x-slot:action>
    </x-page-header>

    @if ($errors->any())
        {{-- القيود تُشرَح لحظة كسرها: ماذا حدث + ماذا تفعل (2.17-ب) --}}
        <div class="card p-3 mb-4" style="border: 1px solid var(--color-state-danger)">
            @foreach ($errors->all() as $error)
                <p class="text-sm flex items-start gap-2"><span aria-hidden="true">◉</span><span>{{ $error }}</span></p>
            @endforeach
        </div>
    @endif

    <x-filters :action="route('volunteer.packages.show', $package)">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">حالة المهامّ</span>
            <select name="status" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="ابحث باسم البند…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty message="الحزمة بلا بنود بعد" action="ارجع لقائمة الحزم" :href="route('volunteer.packages')" />
    @else
        {{-- رأس الجدول يظهر على الديسكتوب فقط --}}
        <div class="hidden md:grid grid-cols-6 gap-2 px-4 py-2 text-xs" style="color: var(--text-muted)">
            <span class="col-span-2">البند</span>
            <span>المهامّ (نشطة/معتمدة/مُغلَقة)</span>
            <span>وعاء VXP والمنصرف</span>
            <span>النسبة</span>
            <span>أقرب ديدلاين</span>
        </div>

        <div class="space-y-3">
            @foreach ($rows as $row)
                @php $item = $row['item']; $c = $row['counts']; @endphp
                <div class="card p-4 animate-fadeup">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-center">
                        <div class="md:col-span-2 font-semibold">{{ $item->name }}</div>

                        <div class="text-xs" style="color: var(--text-muted)">
                            <span class="md:hidden">المهامّ: </span>
                            <span style="color: var(--color-state-warn)">▲ {{ $c['active'] }}</span> ·
                            <span style="color: var(--color-state-ok)">● {{ $c['done'] }}</span> ·
                            <span style="color: var(--color-state-idle)">○ {{ $c['closed'] }}</span>
                        </div>

                        <div class="text-xs">
                            <span class="md:hidden" style="color: var(--text-muted)">VXP: </span>
                            {{ rtrim(rtrim(number_format((float) $item->vxp_pool, 2), '0'), '.') }}
                            <span style="color: var(--text-muted)">/ منصرف {{ rtrim(rtrim(number_format((float) $item->vxp_spent, 2), '0'), '.') }}</span>
                        </div>

                        <div class="text-xs font-semibold">{{ rtrim(rtrim(number_format($row['percent'], 1), '0'), '.') }}%</div>

                        <div class="text-xs flex items-center gap-1">
                            <x-state-badge :state="$row['deadline_state']"
                                           :label="$row['deadline']?->format('m/d H:i') ?? 'بلا ديدلاين'" />
                        </div>
                    </div>

                    @if ($c['closed'] > 0)
                        <p class="text-xs mt-2" style="color: var(--color-state-idle)">
                            ○ {{ $c['closed'] }} مهمّة مُغلَقة — مستبعَدة من مقام النسبة
                        </p>
                    @endif

                    <details class="mt-2">
                        <summary class="text-sm cursor-pointer" style="color: var(--color-brand-500)">مهامّ البند</summary>
                        <div class="mt-2 space-y-1">
                            @forelse ($row['tasks'] as $task)
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-1 text-xs items-center rounded-lg px-2 py-1"
                                     style="background: var(--surface-raised)">
                                    <span class="md:col-span-2">{{ $task->title }}</span>
                                    <span style="color: var(--text-muted)">{{ $task->owner?->shortName() ?? 'بلا مالك' }}</span>
                                    <span class="flex items-center gap-2">
                                        <x-state-badge :state="in_array($task->status, ['approved'], true) ? 'ok' : ($task->status === 'closed' ? 'idle' : 'warn')"
                                                       :label="$statuses[$task->status] ?? $task->status" />
                                        <span>{{ rtrim(rtrim(number_format((float) $task->vxp_value, 2), '0'), '.') }} VXP</span>
                                    </span>
                                </div>
                            @empty
                                <p class="text-xs" style="color: var(--text-muted)">لسّه مفيش مهامّ في البند ده.</p>
                            @endforelse
                        </div>
                    </details>
                </div>
            @endforeach
        </div>
    @endif

    @if ($distributable->isNotEmpty())
        <div class="mt-5">
            <h2 class="text-sm font-bold mb-2">توزيع نقاط الإنتاج على الأبناء</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($distributable as $entry)
                    @continue($entry['children']->isEmpty())
                    <button type="button" data-modal-open="vxp-{{ $entry['task']->id }}"
                            class="btn rounded-xl px-3 py-2 text-xs motion-standard"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                        {{ $entry['task']->title }} — وعاء {{ rtrim(rtrim(number_format($entry['summary']['pool'], 2), '0'), '.') }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @push('modals')
        @foreach ($distributable as $entry)
            @continue($entry['children']->isEmpty())
            @php $task = $entry['task']; $s = $entry['summary']; @endphp
            <x-modal :id="'vxp-'.$task->id" :title="'توزيع VXP — '.$task->title">
                <form method="post" action="{{ route('volunteer.packages.vxp', $task) }}" class="space-y-3"
                      data-vxp-form data-pool="{{ $s['pool'] }}" data-max="{{ $s['max'] }}">
                    @csrf

                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-xl p-2" style="background: var(--surface-raised)">
                            <div style="color: var(--text-muted)">وعاء المهمّة</div>
                            <div class="font-bold text-base">{{ rtrim(rtrim(number_format($s['pool'], 2), '0'), '.') }}</div>
                        </div>
                        <div class="rounded-xl p-2" style="background: var(--surface-raised)">
                            <div style="color: var(--text-muted)">شريحتك المحفوظة ({{ rtrim(rtrim(number_format($minShare, 2), '0'), '.') }}%)</div>
                            <div class="font-bold text-base">{{ rtrim(rtrim(number_format($s['reserved'], 2), '0'), '.') }}</div>
                        </div>
                        <div class="rounded-xl p-2" style="background: var(--surface-raised)">
                            <div style="color: var(--text-muted)">أقصى ما يُوزَّع</div>
                            <div class="font-bold text-base">{{ rtrim(rtrim(number_format($s['max'], 2), '0'), '.') }}</div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        @foreach ($entry['children'] as $child)
                            <label class="flex items-center justify-between gap-3 text-sm">
                                <span class="min-w-0 truncate">{{ $child->title }}</span>
                                <input type="number" step="0.01" min="0" name="shares[{{ $child->id }}]"
                                       value="{{ old('shares.'.$child->id, rtrim(rtrim(number_format((float) $child->vxp_value, 2, '.', ''), '0'), '.')) }}"
                                       data-vxp-share
                                       class="w-28 rounded-xl px-3 py-2 text-sm text-left"
                                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                            </label>
                        @endforeach
                    </div>

                    <p class="text-xs" data-vxp-sum style="color: var(--text-muted)">المجموع: 0</p>

                    <label class="flex items-start gap-2 text-xs">
                        <input type="checkbox" name="consent_personal" value="1" style="accent-color: var(--color-brand-500)">
                        <span>موافق صراحةً على خصم الزيادة فوق الوعاء من رصيدي الشخصيّ.</span>
                    </label>

                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c; min-height: 44px">حفظ التوزيع</button>
                </form>
            </x-modal>
        @endforeach

        @if ($canObject)
            <x-modal id="version-diff" title="فرق النسخة — ما رفعتَه ↔ ما اعتُمد">
                <div class="space-y-3 text-sm">
                    @forelse ($versionDiff as $diff)
                        <div class="rounded-xl p-3" style="background: var(--surface-raised)">
                            <div class="text-xs mb-1" style="color: var(--text-muted)">{{ $diff['field'] }}</div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div><span style="color: var(--text-muted)">رفعتَ:</span> {{ $diff['submitted'] ?? '—' }}</div>
                                <div><span style="color: var(--text-muted)">اعتُمد:</span> {{ $diff['approved'] ?? '—' }}</div>
                            </div>
                        </div>
                    @empty
                        <p style="color: var(--text-muted)">مفيش فروق بين ما رفعتَه وما اعتُمد.</p>
                    @endforelse

                    <p class="text-xs" style="color: var(--text-muted)">
                        مهلة الاعتراض تنتهي {{ \Illuminate\Support\Carbon::parse($package->objection_due_at)->format('Y/m/d H:i') }} — والسكوت قبول.
                    </p>

                    <form method="post" action="{{ route('volunteer.packages.object', $package) }}" class="space-y-2">
                        @csrf
                        <textarea name="note" rows="3" required minlength="10" class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                                  placeholder="اكتب سبب اعتراضك…"></textarea>
                        <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-state-warn); color: #04201c; min-height: 44px">رفع الاعتراض</button>
                    </form>
                </div>
            </x-modal>
        @endif
    @endpush
@endsection

@push('scripts')
    <script>
        // ردّ فوريّ لكلّ فعل: المجموع يتحدّث مع الكتابة، والقيد يُشرَح لحظة كسره (2.17-ب · 2.15-د)
        document.querySelectorAll('[data-vxp-form]').forEach((form) => {
            const output = form.querySelector('[data-vxp-sum]');
            const max = parseFloat(form.dataset.max || '0');
            const pool = parseFloat(form.dataset.pool || '0');

            const update = () => {
                let sum = 0;
                form.querySelectorAll('[data-vxp-share]').forEach((input) => {
                    sum += parseFloat(input.value || '0') || 0;
                });

                const rounded = Math.round(sum * 100) / 100;
                let note = 'المجموع: ' + rounded;

                if (rounded > max) {
                    note += ' — تخطّيت أقصى ما يُوزَّع (' + max + ')';
                    note += rounded > pool ? ' وتخطّيت وعاء المهمّة كمان.' : '، وشريحتك المحفوظة لازم تفضل.';
                    note += ' وافق صراحةً على الخصم من رصيدك أو قلّل القيم.';
                    output.style.color = 'var(--color-state-danger)';
                } else {
                    output.style.color = 'var(--text-muted)';
                }

                output.textContent = note;
            };

            form.addEventListener('input', update);
            update();
        });
    </script>
@endpush
