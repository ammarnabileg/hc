@php
    /**
     * ملاحظات التدريب (3.2): **Text Area واحد مشترك لكلّ دروس التدريب** —
     * ما يكتبه هنا يجده كما هو في أيّ درسٍ آخر، **ويُحفَظ تلقائيًّا** بـ«اتحفظ ✓» (2.17-ب).
     */
@endphp

<section class="card p-4" data-notes
         data-url="{{ route('learning.course.notes.save', $course) }}"
         data-delay="{{ (int) setting('learning.notes.autosave_delay_ms', 800) }}">
    <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
        <h2 class="font-bold flex items-center gap-2">
            @include('learning.partials.icon', ['name' => 'note', 'box' => 18])
            <span>{{ setting('learning.notes.title') }}</span>
        </h2>
        <span class="text-xs" data-notes-state style="color: var(--text-muted)"></span>
    </div>

    <p class="text-xs mb-2" style="color: var(--text-muted)">{{ setting('learning.notes.hint') }}</p>

    <form method="post" action="{{ route('learning.course.notes.save', $course) }}" data-notes-form>
        @csrf
        <label class="sr-only" for="course-notes">{{ setting('learning.notes.title') }}</label>
        <textarea id="course-notes" name="body" rows="6" maxlength="{{ $note_max_length }}"
                  placeholder="{{ setting('learning.notes.placeholder') }}"
                  class="w-full rounded-xl px-3 py-2 text-sm leading-7"
                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                  data-notes-input>{{ $note_body }}</textarea>

        @error('body')
            <p class="text-xs mt-1" style="color: var(--color-state-danger)">▲ {{ $message }}</p>
        @enderror

        {{-- زرّ الحفظ الصريح للحالة التي لا يعمل فيها الجافاسكربت — يُخفى حين يعمل --}}
        <button class="btn mt-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c" data-notes-manual>
            {{ setting('learning.notes.save_cta') }}
        </button>
    </form>

    <div class="flex items-center gap-2 flex-wrap mt-3">
        @can('course_notes.export')
            <a href="{{ route('learning.course.notes.export', $course) }}"
               class="inline-flex items-center gap-1 rounded-xl px-3 py-2 text-xs motion-standard"
               style="background: var(--surface-sunken); min-block-size: 2.75rem">
                @include('learning.partials.icon', ['name' => 'download'])
                <span>{{ setting('learning.notes.export') }}</span>
            </a>
        @endcan

        @can('course_notes.delete')
            <form method="post" action="{{ route('learning.course.notes.clear', $course) }}"
                  onsubmit="return confirm(@js(setting('learning.notes.clear_confirm')))">
                @csrf
                @method('delete')
                <button class="inline-flex items-center gap-1 rounded-xl px-3 py-2 text-xs motion-standard"
                        style="background: var(--surface-sunken); min-block-size: 2.75rem">
                    @include('learning.partials.icon', ['name' => 'trash'])
                    <span>{{ setting('learning.notes.clear') }}</span>
                </button>
            </form>
        @endcan
    </div>
</section>
