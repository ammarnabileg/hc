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
    {{-- بلا زجاج (Glassmorphism) — خلفيّةٌ صلبة كالهيدر، لا `blur`/شفافيّة (2.10.1-14 · §25 v5.9) --}}
    <div class="sticky top-0 z-30 -mx-4 md:-mx-6 px-4 md:px-6 py-2 mb-4"
         style="background: var(--surface); border-bottom: 1px solid var(--border)"
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

    {{-- ⭐ لافتة تهنئة عند نصّ التدريب (3.4-19) --}}
    @include('learning.partials.half-banner', ['half' => $outline['half_point'] ?? null])

    {{-- «مجّاني أوّل مرّة» انتهى بالامتحان والشهادة ⟵ Paywall نفسيّ (16) --}}
    @if (($outline['paywall']['locked'] ?? false))
        @include('learning.partials.paywall', ['paywall' => $outline['paywall']])
    @endif

    {{-- خارج الإتاحة: ماذا حدث + متى يفتح بتوقيتك مع عدّاد، والمحتوى يبقى ظاهرًا (5 · 24.5) --}}
    @include('learning.partials.availability-notice', ['availability' => $availability])

    {{-- تقدّمك في التدريب + الشبح — حرفيًّا من ملف الهويّة (`.grid2`) --}}
    <div class="grid2 mb-8">
        <div class="stack justify-center">
            <div class="spread">
                <h3>{{ setting('learning.course.progress_title', 'تقدّمك في التدريب') }}</h3>
                <strong>{{ $outline['percent'] }}%</strong>
            </div>
            <div class="progress" role="progressbar" aria-valuenow="{{ $outline['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                <span style="--value: {{ $outline['percent'] }}%"></span>
            </div>
            <p class="small">
                {{ setting('learning.xp.earned_label') }} <strong style="color: var(--ink)">{{ (int) $enrollment->xp_earned }}</strong>
                {{ setting('learning.xp.suffix') }}
                @if ($next_xp > 0 && $availability['open'])
                    · {{ setting('learning.xp.next_label') }} <strong class="accent">+{{ $next_xp }}</strong>
                @endif
            </p>
        </div>

        {{-- Ghost Timer 👻 (6) --}}
        @include('learning.partials.ghost-timer', ['deadline' => $deadline])
    </div>

    {{-- ⭐ دليل اجتماعيّ حيّ بأرقام حقيقيّة (3.4-45 · 3.4-49 · 2.9-7) --}}
    @include('learning.partials.social-proof', ['social' => $social])

    <div class="spread mb-4">
        <h2>{{ setting('learning.course.outline_title') }}</h2>

        {{-- بحث داخل الدروس بالاسم — لا فلاتر أخرى على هذه الشاشة (24.5) --}}
        <label class="block w-full sm:w-64">
            <span class="sr-only">{{ setting('learning.course.lesson_search') }}</span>
            <input type="search" data-lesson-search
                   placeholder="{{ setting('learning.course.lesson_search') }}">
        </label>
    </div>

    {{-- محتوى التدريب: أقسامٌ أصيلة details/summary — حرفيًّا من ملف الهويّة --}}
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

        <details open data-course-section
                  data-done="{{ $isDoneSection ? 1 : 0 }}"
                  data-current="{{ $isCurrentSection ? 1 : 0 }}"
                  data-unlocked="{{ $justUnlocked ? 1 : 0 }}">
            <summary>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }} · {{ $section['title'] }}</summary>

            <div>
                @foreach ($section['lessons'] as $lessonRow)
                    @php
                        $isCurrent = $lessonRow['id'] === $outline['current_id'];
                        $unlocked = $lessonRow['unlocked'];
                    @endphp

                    <div class="lesson-row {{ $isCurrent ? 'current' : '' }}" data-lesson-title="{{ $lessonRow['title'] }}">
                        @if ($lessonRow['completed'])
                            {{-- ⭐ علامة إكمال «بتتملّى» بحركة (3.4-33) — والمعنى في النصّ لا في الحركة --}}
                            <span class="check-fill inline-flex rounded-full" style="color: var(--success)">
                                <x-icon name="check" size="16" />
                            </span>
                        @elseif (! $unlocked)
                            <x-icon name="lock" size="16" :label="setting('learning.lesson.locked_label', 'مقفول')" style="color: var(--muted)" />
                        @else
                            <x-icon :name="$lessonRow['icon']" size="16" style="color: {{ $isCurrent ? 'var(--brand)' : 'var(--muted)' }}" />
                        @endif

                        <div class="min-w-0">
                            @if ($unlocked)
                                <a href="{{ route('learning.lesson', [$course, $lessonRow['id']]) }}" class="hover:underline">
                                    <h3 class="truncate">{{ $lessonRow['title'] }}</h3>
                                </a>
                            @else
                                <h3 class="truncate" style="color: var(--muted)">{{ $lessonRow['title'] }}</h3>
                            @endif
                            <p class="small">{{ $lessonRow['duration'] }} {{ setting('learning.lesson.minutes_suffix') }}</p>
                            {{-- ⭐ الدرس المقفول يظهر بقفل وسببٍ مكتوب — لا يُخفى (24.5) --}}
                            @unless ($unlocked)
                                <p class="small mt-1" style="color: var(--muted)">{{ $lessonRow['lock_reason'] }}</p>
                            @endunless
                        </div>

                        @if ($lessonRow['completed'])
                            <span class="small accent">{{ setting('learning.lesson.done_badge') }}</span>
                        @elseif ($isCurrent)
                            <span class="small accent">{{ setting('learning.lesson.current_badge') }}</span>
                        @elseif ($unlocked)
                            {{-- ⭐ حفظ الدرس (Bookmark — 3.4-34): القرار والتخزين في الخادم --}}
                            @if (setting('learning.ux.bookmark_enabled', true))
                                <form method="post" action="{{ route('learning.lesson.bookmark', [$course, $lessonRow['id']]) }}">
                                    @csrf
                                    <button type="submit" class="motion-standard opacity-70 hover:opacity-100"
                                            style="color: {{ $lessonRow['bookmarked'] ? 'var(--gold)' : 'var(--muted)' }}"
                                            aria-label="{{ $lessonRow['bookmarked'] ? setting('learning.bookmark.remove', 'إزالة الحفظ') : setting('learning.bookmark.add', 'احفظ الدرس') }}"
                                            title="{{ $lessonRow['bookmarked'] ? setting('learning.bookmark.remove', 'إزالة الحفظ') : setting('learning.bookmark.add', 'احفظ الدرس') }}">
                                        <x-icon name="badge" size="15" />
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </details>
    @endforeach

    {{-- ⭐ مشاركة إنجاز/شهادة على السوشيال (3.4-47) — بعد الإنجاز الحقيقيّ فقط --}}
    @if (setting('learning.ux.share_enabled', true) && $outline['total'] > 0 && $outline['percent'] >= (int) setting('learning.progress.complete_percent', 100))
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

    {{-- بلوك الامتحان النهائيّ بحالته وشرط فتحه — حرفيًّا `.panel.wash` (24.5) --}}
    <div class="panel wash spread mt-8">
        <div class="cluster">
            <x-icon name="lock" size="20" style="color: var(--muted)" />
            <div>
                <h3>{{ setting('learning.exam.block_title') }}</h3>
                <p class="small">{{ $exam['condition'] }}</p>

                @if ($exam['lock_reason'] ?? null)
                    {{-- السبب مكتوب: ماذا حدث + متى يفتح، بساعة المستخدم (2.17 · 5) --}}
                    <p class="small" style="color: var(--muted)">{{ $exam['lock_reason'] }}</p>
                @endif
            </div>
        </div>

        <div class="cluster">
            <x-state-badge :state="$exam['state']" :label="$exam['label']" />

            {{-- ⭐ المقفول يظهر بقفلٍ وسببٍ مكتوب لا بزرٍّ كاذب ولا بإخفاء (24.5 · 5) --}}
            @if ($exam['exists'] && ($exam['locked'] ?? false))
                <span class="small muted inline-flex items-center gap-1">
                    <x-icon name="lock" size="14" />
                    <span>{{ $exam['locked_label'] }}</span>
                </span>
            @elseif ($exam['exists'] && $exam['unlocked'] && $exam['url'])
                <a href="{{ $exam['url'] }}" class="btn btn-p">{{ setting('learning.exam.start_cta') }}</a>
            @endif

            @if ($certificate['exists'] && $certificate['url'])
                <a href="{{ $certificate['url'] }}" class="btn text">{{ setting('learning.cta.certificates') }}</a>
            @endif
        </div>
    </div>
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
                document.querySelectorAll('[data-course-section]').forEach((node) => {
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
