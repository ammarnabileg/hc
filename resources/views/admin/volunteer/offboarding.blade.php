@extends('layouts.app')

@section('title', 'الأوفبوردنج')

@section('content')
    <x-page-header
        title="الأوفبوردنج"
        subtitle="ثلاثة أنواع للخروج لا رابع لها — والإقصاء حصرًا عبر سلّم العتبات."
        :breadcrumbs="[['label' => 'التطوّع', 'url' => route('admin.volunteer.index')], ['label' => 'الأوفبوردنج']]">
        <x-slot:action>
            @can('offboarding.create')
                <button type="button" data-modal-open="offboarding-modal"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ ملفّ إنهاء</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    @include('admin.volunteer.partials.tabs', ['current' => 'offboarding'])

    {{-- قاعدتان معلَنتان — تُقرآن قبل أيّ إجراء --}}
    <div class="card p-3 mb-4 text-sm space-y-1">
        <div>🔒 <strong>الإقصاء</strong> لا يكون إلّا عبر سلّم العتبات (عتبة التعليق الحاليّة: {{ $exclusionThreshold }}) — لا فصل بقرار فرديّ.</div>
        <div>🔒 <strong>السبب لا يُنشَر للفريق</strong> — يظهر «انتهت عضويّة فلان» فقط، والتفصيل في الملاحظات الإداريّة.</div>
    </div>

    <x-filters :action="route('admin.volunteer.offboarding')">
        <div>
            <label class="block text-xs mb-1" for="f-q" style="color: var(--text-muted)">بحث بالاسم/الكود</label>
            <input id="f-q" type="search" name="q" value="{{ $filters['q'] }}"
                   class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </div>
        <div>
            <label class="block text-xs mb-1" for="f-type" style="color: var(--text-muted)">النوع</label>
            <select id="f-type" name="type" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">فلتر</button>
    </x-filters>

    {{-- كروت رأسيّة: الجدول لا يُمرَّر أفقيًّا على الموبايل (2.15-ج) --}}
    <section class="space-y-3">
        @forelse ($records as $record)
            <article class="card p-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-semibold">{{ $record->user?->name }} <span class="text-xs" style="color: var(--text-muted)">#{{ $record->user?->code }}</span></div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted)">
                            {{ $types[$record->type] ?? $record->type }} ·
                            مهلة الإشعار حتى {{ $record->notice_until?->format('Y-m-d') ?? '—' }}
                        </div>
                    </div>
                    <x-state-badge :state="$record->completed_at ? 'idle' : 'warn'"
                                   :label="$record->completed_at ? 'مقفول' : 'مفتوح'" />
                </div>

                {{-- التصفية الإلزاميّة: لا إنهاء قبل اكتمالها --}}
                @can('offboarding.create')
                    <details class="mt-3">
                        <summary class="cursor-pointer text-sm font-semibold select-none">التصفية الإلزاميّة (Checklist)</summary>
                        <form method="post" action="{{ route('admin.volunteer.offboarding.clearance', $record) }}" class="mt-2">
                            @csrf
                            @foreach ($clearance as $index => $label)
                                @php $done = (bool) ($record->clearance_checklist[$index]['done'] ?? false); @endphp
                                <label class="flex items-start gap-2 text-sm py-1">
                                    <input type="checkbox" name="clearance[{{ $index }}]" value="1" @checked($done)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                            <button type="submit" class="btn mt-2 rounded-xl px-3 py-1.5 text-xs font-semibold"
                                    style="background: var(--surface-raised)">احفظ التصفية</button>
                        </form>
                    </details>

                    {{-- مقابلة الخروج: 3 أسئلة تغذّي تقرير أسباب التسرّب --}}
                    @if (setting('volunteer.offboarding.exit_interview_enabled', true))
                        <details class="mt-2">
                            <summary class="cursor-pointer text-sm font-semibold select-none">
                                مقابلة الخروج
                                @if ($record->exit_interview_done)
                                    <x-state-badge state="ok" label="تمّت" />
                                @endif
                            </summary>
                            <form method="post" action="{{ route('admin.volunteer.offboarding.interview', $record) }}" class="mt-2">
                                @csrf
                                @foreach ($questions as $i => $question)
                                    <label class="block text-xs mb-1 mt-2">{{ $question }}</label>
                                    <textarea name="answers[{{ $i }}]" rows="2" maxlength="1000"
                                              class="w-full rounded-xl px-3 py-2 text-sm"
                                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                                @endforeach
                                <button type="submit" class="btn mt-2 rounded-xl px-3 py-1.5 text-xs font-semibold"
                                        style="background: var(--surface-raised)">سجّل المقابلة</button>
                            </form>
                        </details>
                    @endif
                @endcan

                <div class="flex items-center gap-3 mt-3 flex-wrap">
                    @if (! $record->completed_at)
                        @can('offboarding.approve')
                            <form method="post" action="{{ route('admin.volunteer.offboarding.complete', $record) }}">
                                @csrf
                                <button type="submit" class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                                        style="background: var(--color-brand-500); color: #04201c">أنهِ العضويّة</button>
                            </form>
                        @endcan
                    @else
                        <span class="text-xs" style="color: var(--text-muted)">
                            @if ($record->honorable_certificate_issued)
                                🎖️ خروج مشرَّف — صدرت شهادة خبرة تطوّع.
                            @else
                                لا شهادة خبرة لهذا النوع.
                            @endif
                        </span>
                    @endif
                </div>
            </article>
        @empty
            <x-empty message="مفيش ملفّات إنهاء — وده خبر كويّس." />
        @endforelse
    </section>

    @can('volunteer_central_settings.edit')
        @include('admin.volunteer.partials.settings-card', [
            'title' => 'مدد التبريد والمهل وبنود التصفية',
            'rows' => $settings,
            'action' => route('admin.volunteer.offboarding.settings.save'),
            'resetAction' => route('admin.volunteer.reset', 'volunteer_offboarding'),
            'lockedKeys' => ['volunteer.offboarding.reason_published_to_team'],
        ])
    @endcan
@endsection

@push('modals')
    @can('offboarding.create')
        <x-modal id="offboarding-modal" title="ملفّ إنهاء عضويّة">
            <form method="post" action="{{ route('admin.volunteer.offboarding.open') }}">
                @csrf

                <label class="block text-sm font-semibold mb-1" for="ob-code">كود المتطوّع</label>
                <input type="text" name="code" id="ob-code" required maxlength="32"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="ob-type">نوع الخروج</label>
                <select name="type" id="ob-type" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold mb-1" for="ob-reason">السبب (للملاحظات الإداريّة وحدها)</label>
                <textarea name="reason" id="ob-reason" rows="3" maxlength="1000"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-2"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                <p class="text-xs mb-3" style="color: var(--text-muted)">🔒 السبب <strong>لا يُنشَر للفريق</strong> — يظهر «انتهت عضويّة فلان» فقط.</p>

                <div class="text-sm font-semibold mb-1">التصفية الإلزاميّة</div>
                @foreach ($clearance as $index => $label)
                    <label class="flex items-start gap-2 text-sm py-1">
                        <input type="checkbox" name="clearance[{{ $index }}]" value="1">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">افتح الملفّ</button>
            </form>
        </x-modal>
    @endcan
@endpush
