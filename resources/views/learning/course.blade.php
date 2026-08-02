@extends('layouts.app')
@section('title', $course->name_ar)

@push('head')
    @include('learning.partials.styles')
@endpush

@section('content')
    @php
        $libraryUrl = \Illuminate\Support\Facades\Route::has('library.index') ? route('library.index') : null;
        $certificatesUrl = \Illuminate\Support\Facades\Route::has('learning.certificates') ? route('learning.certificates') : null;
        $currentUrl = $outline['current_id'] ? route('learning.lesson', [$course, $outline['current_id']]) : null;
    @endphp

    <x-page-header :title="$course->name_ar"
                   :subtitle="$outline['completed'].' / '.$outline['total'].' '.setting('learning.lesson.unit_plural')"
                   :breadcrumbs="[
                       ['label' => setting('learning.breadcrumb.root'), 'url' => route('learning.courses')],
                       ['label' => setting('learning.courses.title'), 'url' => route('learning.courses')],
                       ['label' => $course->name_ar],
                   ]">
        <x-slot:action>
            @if ($currentUrl && $availability['open'])
                <a href="{{ $currentUrl }}"
                   class="btn hidden md:inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('learning.cta.continue') }}</a>
            @endif

            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm list-none"
                         style="background: var(--surface-raised)" aria-label="{{ setting('learning.more.label') }}">⋯</summary>
                <div class="absolute end-0 mt-1 card p-1 min-w-52 z-40">
                    @if ($libraryUrl)
                        <a href="{{ $libraryUrl }}" class="block rounded-lg px-3 py-2 text-sm">{{ setting('learning.cta.library') }}</a>
                    @endif
                    @if ($certificatesUrl)
                        <a href="{{ $certificatesUrl }}" class="block rounded-lg px-3 py-2 text-sm">{{ setting('learning.cta.certificates') }}</a>
                    @endif
                    <button type="button" data-modal-open="report-problem"
                            class="block w-full text-start rounded-lg px-3 py-2 text-sm">{{ setting('learning.report.cta') }}</button>
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    {{-- ⭐ لحظة الذروة بعد إكمال الدرس (2.14 · 4.1) — تصل مفلوشةً من الخادم --}}
    @if (session('celebration'))
        @include('learning.partials.celebration', ['celebration' => session('celebration')])
    @endif

    {{-- ⭐ شريط تقدّم لاصق أعلى التدريب (3.4-16): موقعي من التدريب لا يغيب أبدًا --}}
    <div class="sticky top-0 z-30 -mx-4 md:-mx-6 px-4 md:px-6 py-2 mb-4"
         style="background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px); border-bottom: 1px solid var(--border)"
         data-sticky-progress>
        <div class="flex items-center gap-3">
            <span class="text-xs tabular-nums shrink-0" style="color: var(--text-muted)">
                {{ $outline['completed'] }}/{{ $outline['total'] }}
            </span>
            <span class="flex-1 h-1.5 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                <span class="block h-full rounded-full motion-standard"
                      style="width: {{ $outline['percent'] }}%; background: var(--color-brand-500)"></span>
            </span>
            <span class="text-xs font-extrabold tabular-nums shrink-0">{{ $outline['percent'] }}%</span>
        </div>
    </div>

    {{-- «مجّاني أوّل مرّة» انتهى بالامتحان والشهادة ⟵ Paywall نفسيّ (16) --}}
    @if (($outline['paywall']['locked'] ?? false))
        @include('learning.partials.paywall', ['paywall' => $outline['paywall']])
    @endif

    {{-- خارج الإتاحة: ماذا حدث + متى يفتح بتوقيتك مع عدّاد، والمحتوى يبقى ظاهرًا (5 · 24.5) --}}
    @include('learning.partials.availability-notice', ['availability' => $availability])

    <div class="grid gap-4 lg:grid-cols-3 mb-5">
        <div class="lg:col-span-2 card p-4">
            @include('learning.partials.progress-bar', ['percent' => $outline['percent']])
            <p class="text-xs mt-3" style="color: var(--text-muted)">
                {{ setting('learning.xp.earned_label') }}: <strong style="color: var(--text)">{{ (int) $enrollment->xp_earned }}</strong>
                {{ setting('learning.xp.suffix') }}
                @if ($next_xp > 0 && $availability['open'])
                    · {{ setting('learning.xp.next_label') }} <strong style="color: var(--color-brand-400)">+{{ $next_xp }}</strong>
                @endif
            </p>
        </div>

        {{-- Ghost Timer 👻 (6) --}}
        @include('learning.partials.ghost-timer', ['deadline' => $deadline])
    </div>

    {{-- ⭐ دليل اجتماعيّ حيّ بأرقام حقيقيّة (3.4-45 · 3.4-49 · 2.9-7) --}}
    @include('learning.partials.social-proof', ['social' => $social])

    <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
        <h2 class="text-lg font-bold">{{ setting('learning.course.outline_title') }}</h2>

        {{-- بحث داخل الدروس بالاسم — لا فلاتر أخرى على هذه الشاشة (24.5) --}}
        <label class="block w-full sm:w-64">
            <span class="sr-only">{{ setting('learning.course.lesson_search') }}</span>
            <input type="search" data-lesson-search
                   placeholder="{{ setting('learning.course.lesson_search') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
    </div>

    {{-- Roadmap رأسيّ: السيكشنز وتحت كلّ سيكشن دروسه (24.5) --}}
    <div class="roadmap space-y-4">
        @foreach ($outline['sections'] as $index => $section)
            @php
                $lessonsOf = collect($section['lessons']);
                $isDoneSection = $lessonsOf->every(fn ($l) => $l['completed']);
                $isCurrentSection = $lessonsOf->contains(fn ($l) => $l['id'] === $outline['current_id']);
                // ⭐ «فتح المحطّة» (3.4-23): محطّة مفتوحة لم تُلمَس بعد — هنا وقعت اللحظة
                $justUnlocked = $isCurrentSection && ! $isDoneSection
                    && $lessonsOf->every(fn ($l) => ! $l['completed'])
                    && $lessonsOf->contains(fn ($l) => $l['unlocked']);
            @endphp

            <section class="roadmap-node card p-4"
                     data-done="{{ $isDoneSection ? 1 : 0 }}"
                     data-current="{{ $isCurrentSection ? 1 : 0 }}"
                     data-unlocked="{{ $justUnlocked ? 1 : 0 }}">
                <h3 class="font-bold mb-3 flex items-center gap-2">
                    <span class="text-xs rounded-full px-2 py-0.5" style="background: var(--surface-sunken); color: var(--text-muted)">
                        {{ $index + 1 }}
                    </span>
                    <span>{{ $section['title'] }}</span>
                </h3>

                <ul class="space-y-2">
                    @foreach ($section['lessons'] as $lessonRow)
                        @php
                            $isCurrent = $lessonRow['id'] === $outline['current_id'];
                            $unlocked = $lessonRow['unlocked'];
                        @endphp

                        <li class="rounded-xl px-3 py-2 motion-standard"
                            data-lesson-title="{{ $lessonRow['title'] }}"
                            @style([
                                'background: var(--surface-sunken)',
                                'outline: 2px solid var(--color-brand-500)' => $isCurrent,
                            ])>
                            <div class="flex items-center gap-2 flex-wrap">
                                {{-- أيقونة النوع SVG بهويّة المنصّة لا إيموجي (3 · 2.16-ج) --}}
                                <x-icon :name="$lessonRow['icon']" size="16" style="color: var(--text-muted)" />

                                @if ($unlocked)
                                    <a href="{{ route('learning.lesson', [$course, $lessonRow['id']]) }}"
                                       class="flex-1 min-w-0 truncate text-sm hover:underline">{{ $lessonRow['title'] }}</a>
                                @else
                                    <span class="flex-1 min-w-0 truncate text-sm" style="color: var(--text-muted)">{{ $lessonRow['title'] }}</span>
                                @endif

                                <span class="text-xs whitespace-nowrap" style="color: var(--text-muted)">
                                    {{ $lessonRow['duration'] }} {{ setting('learning.lesson.minutes_suffix') }}
                                </span>

                                @if ($lessonRow['completed'])
                                    {{-- ⭐ علامة إكمال «بتتملّى» بحركة (3.4-33) — والمعنى في النصّ لا في الحركة --}}
                                    <span class="check-fill inline-flex items-center gap-1 text-xs rounded-full px-2 py-0.5"
                                          style="background: color-mix(in srgb, var(--color-state-ok) 16%, transparent); color: var(--color-state-ok)">
                                        <x-icon name="check" size="13" />
                                        <span>{{ setting('learning.lesson.done_badge') }}</span>
                                    </span>
                                @elseif ($isCurrent)
                                    <x-state-badge state="warn" :label="setting('learning.lesson.current_badge')" />
                                @elseif (! $unlocked)
                                    <x-icon name="lock" size="14" :label="setting('learning.lesson.locked_label', 'مقفول')" style="color: var(--text-muted)" />
                                @endif

                                {{-- ⭐ حفظ الدرس (Bookmark — 3.4-34): القرار والتخزين في الخادم --}}
                                @if ($unlocked)
                                    <form method="post" action="{{ route('learning.lesson.bookmark', [$course, $lessonRow['id']]) }}">
                                        @csrf
                                        <button type="submit" class="motion-standard opacity-70 hover:opacity-100"
                                                style="color: {{ $lessonRow['bookmarked'] ? 'var(--color-state-honor)' : 'var(--text-muted)' }}"
                                                aria-label="{{ $lessonRow['bookmarked'] ? setting('learning.bookmark.remove', 'إزالة الحفظ') : setting('learning.bookmark.add', 'احفظ الدرس') }}"
                                                title="{{ $lessonRow['bookmarked'] ? setting('learning.bookmark.remove', 'إزالة الحفظ') : setting('learning.bookmark.add', 'احفظ الدرس') }}">
                                            <x-icon name="badge" size="15" />
                                        </button>
                                    </form>
                                @endif
                            </div>

                            {{-- ⭐ الدرس المقفول يظهر بقفل وسببٍ مكتوب — لا يُخفى (24.5) --}}
                            @unless ($unlocked)
                                <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $lessonRow['lock_reason'] }}</p>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>

    {{-- ⭐ مشاركة إنجاز/شهادة على السوشيال (3.4-47) — بعد الإنجاز الحقيقيّ فقط --}}
    @if ($outline['total'] > 0 && $outline['percent'] >= (int) setting('learning.progress.complete_percent', 100))
        @php
            $shareUrl = $certificate['exists'] && $certificate['url']
                ? url($certificate['url'])
                : route('learning.course', $course);
            $shareText = setting('learning.share.text').' — '.$course->name_ar;
        @endphp

        <section class="card p-4 mt-5">
            <h3 class="font-bold mb-1">{{ setting('learning.share.title') }}</h3>
            <p class="text-xs mb-3" style="color: var(--text-muted)">{{ $shareText }}</p>

            <div class="flex flex-wrap gap-2">
                <a href="https://wa.me/?text={{ urlencode($shareText.' '.$shareUrl) }}" target="_blank" rel="noopener"
                   class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <x-icon name="link" size="15" /> {{ setting('referral.share.whatsapp_label', 'واتساب') }}
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" rel="noopener"
                   class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <x-icon name="link" size="15" /> {{ setting('referral.share.facebook_label', 'فيسبوك') }}
                </a>
                <a href="https://t.me/share/url?url={{ urlencode($shareUrl) }}&text={{ urlencode($shareText) }}" target="_blank" rel="noopener"
                   class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <x-icon name="link" size="15" /> {{ setting('referral.share.telegram_label', 'تيليجرام') }}
                </a>
            </div>
        </section>
    @endif

    {{-- بلوك الامتحان النهائيّ بحالته وشرط فتحه (24.5) --}}
    <section class="card p-4 mt-5">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h3 class="font-bold">{{ setting('learning.exam.block_title') }}</h3>
                <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $exam['condition'] }}</p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <x-state-badge :state="$exam['state']" :label="$exam['label']" />

                @if ($exam['exists'] && $exam['unlocked'] && $exam['url'])
                    <a href="{{ $exam['url'] }}"
                       class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">{{ setting('learning.exam.start_cta') }}</a>
                @endif

                @if ($certificate['exists'] && $certificate['url'])
                    <a href="{{ $certificate['url'] }}"
                       class="inline-flex items-center rounded-xl px-4 py-2 text-sm motion-standard"
                       style="background: var(--surface-sunken)">{{ setting('learning.cta.certificates') }}</a>
                @endif
            </div>
        </div>
    </section>
@endsection

@if ($outline['current_id'] && $availability['open'])
    @section('mobile_action')
        <a href="{{ route('learning.lesson', [$course, $outline['current_id']]) }}"
           class="btn flex items-center justify-center w-full rounded-xl px-4 py-3 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('learning.cta.continue') }}</a>
    @endsection
@endif

@push('scripts')
    @include('learning.partials.clock-scripts')

    <script>
        /* بحث داخل الدروس بالاسم — تصفية فوريّة بلا إعادة تحميل (2.17-ب) */
        (() => {
            const box = document.querySelector('[data-lesson-search]');
            if (!box) return;

            box.addEventListener('input', () => {
                const term = box.value.trim();
                document.querySelectorAll('[data-lesson-title]').forEach((row) => {
                    row.style.display = !term || row.dataset.lessonTitle.includes(term) ? '' : 'none';
                });
                document.querySelectorAll('.roadmap-node').forEach((node) => {
                    const visible = [...node.querySelectorAll('[data-lesson-title]')]
                        .some((row) => row.style.display !== 'none');
                    node.style.display = visible ? '' : 'none';
                });
            });
        })();
    </script>
@endpush

@push('modals')
    {{-- «الإبلاغ عن مشكلة في الدرس» ⟵ يفتح تذكرة دعم (24.5) --}}
    <x-modal id="report-problem" :title="setting('learning.report.cta')">
        <form method="post" action="{{ route('learning.course.report', $course) }}" class="space-y-3">
            @csrf

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('learning.report.type_label') }}</span>
                <select name="problem_type" required class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($report_types as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('learning.report.lesson_label') }}</span>
                <select name="lesson_title" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('learning.report.lesson_any') }}</option>
                    @foreach ($outline['sections'] as $section)
                        @foreach ($section['lessons'] as $lessonRow)
                            <option value="{{ $lessonRow['title'] }}">{{ $lessonRow['title'] }}</option>
                        @endforeach
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('learning.report.body_label') }}</span>
                <textarea name="body" rows="4" required minlength="5" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>

            <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('learning.report.submit') }}</button>
        </form>
    </x-modal>
@endpush
