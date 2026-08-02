@extends('layouts.admin')

@section('title', 'بنك الأسئلة والامتحانات')

@php
    /**
     * بنك الأسئلة والامتحانات (24.1-3).
     *
     * سؤال واحد للشاشة: «إيه عندي في البنك، وهل يكفي الامتحان النهائيّ؟»
     * فعل رئيسيّ واحد (+ سؤال) والباقي في «⋯» داخل الصفّ · 4 كروت KPI ·
     * 3 فلاتر ظاهرة والباقي مطويّ · جدول 6 أعمدة يتحوّل كروتًا على الموبايل.
     */
    $u = auth()->user();
    $canCreate = $u?->can('question_bank.create');
    $canEdit = $u?->can('question_bank.edit');
    $canDelete = $u?->can('question_bank.delete');
    $canGeneral = $u?->can('general_questions.edit') || $u?->can('question_bank.manage');
    $canReuse = $u?->can('course_exam.edit') || $u?->can('question_bank.manage');
    $canImport = $u?->can('question_bank.import') && setting('question_bank.import_enabled', true);
    $canExport = $u?->can('question_bank.export');
@endphp

@section('content')
    <x-page-header title="بنك الأسئلة والامتحانات"
                   subtitle="مخزن الأسئلة كلّه في مكان واحد — تبحث فيه، تعيد استخدامه، وتعرف هل يكفي الامتحان النهائيّ."
                   :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')], ['label' => 'بنك الأسئلة']]">
        <x-slot:action>
            @if ($canCreate)
                <button type="button" data-modal-open="question-modal" data-question-new
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ سؤال</button>
            @endif

            {{-- الباقي في «⋯» — فعل رئيسيّ واحد بارز (2.15-أ-2) --}}
            <details class="relative">
                <summary class="btn cursor-pointer rounded-xl px-3 py-2 text-sm select-none"
                         style="background: var(--surface-sunken); border: 1px solid var(--border)">⋯</summary>
                <div class="card absolute end-0 mt-2 p-2 w-56 z-20 text-sm space-y-1">
                    <a href="{{ route('admin.question-bank.preview') }}" class="block rounded-lg px-3 py-2 hover:underline">معاينة الامتحان النهائيّ</a>
                    @if ($canImport)
                        <button type="button" data-modal-open="import-modal" class="block w-full text-start rounded-lg px-3 py-2 hover:underline">استيراد CSV</button>
                    @endif
                    @if ($canExport)
                        <a href="{{ route('admin.question-bank.export', request()->query()) }}" class="block rounded-lg px-3 py-2 hover:underline">تصدير الأسئلة</a>
                    @endif
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    {{-- أربعة كروت بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi label="كلّ الأسئلة" :value="$stats['total']" icon="folder" />
        <x-kpi label="الأسئلة العامّة" :value="$stats['general']" icon="xp" />
        <x-kpi label="سقف الامتحان" :value="$stats['cap']" icon="goal" />
        <x-kpi label="المعطّلة" :value="$stats['paused']" icon="blocked" />
    </div>

    {{-- كارت مؤشّر: العامّة مقابل حدّ الامتحان — تحذير أحمر خافت إن نقصت (24.1-3) --}}
    @if ($stats['short'])
        <div class="card p-4 mb-4" role="status" style="border-color: var(--color-state-danger)">
            <div class="flex items-center gap-2 flex-wrap">
                <x-state-badge state="danger" label="الأسئلة العامّة ناقصة" />
                <span class="text-sm">
                    عندك {{ $stats['general'] }} سؤالًا عامًّا مقابل سقف امتحان {{ $stats['cap'] }}.
                </span>
            </div>
            <p class="text-sm mt-2" style="color: var(--text-muted)">
                {{ setting('question_bank.low_warning_text', 'الأسئلة العامّة أقلّ من سقف الامتحان — زوّد البنك قبل ما تنشر امتحانًا.') }}
            </p>
        </div>
    @endif

    @if (session('import_errors'))
        <div class="card p-4 mb-4" style="border-color: var(--color-state-warn)">
            <div class="flex items-center gap-2 mb-2"><x-state-badge state="warn" label="صفوف ما دخلتش" /></div>
            <ul class="text-sm space-y-1" style="color: var(--text-muted)">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('admin.question-bank.index')">
        <label class="text-sm grow min-w-40">بحث في نصّ السؤال
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="اكتب كلمة من السؤال"
                   class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-sm">التدريب
            <select name="course" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected($filters['course'] === (string) $course->id)>{{ $course->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">النوع
            <select name="type" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">الصعوبة
            <select name="difficulty" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($difficulties as $key => $label)
                    <option value="{{ $key }}" @selected($filters['difficulty'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">فلترة</button>

        <x-slot:advanced>
            <label class="text-sm">الدرس
                <select name="lesson" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">كلّ الدروس</option>
                    @foreach ($lessons as $lesson)
                        <option value="{{ $lesson->id }}" @selected($filters['lesson'] === (string) $lesson->id)>
                            {{ $lesson->course_name }} ← {{ $lesson->title_ar }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">الحالة
                <select name="state" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    <option value="active" @selected($filters['state'] === 'active')>نشط</option>
                    <option value="paused" @selected($filters['state'] === 'paused')>معطّل</option>
                </select>
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="general" value="1" @checked($filters['general'] === '1')>
                <span>الأسئلة العامّة فقط</span>
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($questions->isEmpty())
        <x-empty :message="setting('question_bank.empty_text', 'لسّه مافيش أسئلة في البنك — ابدأ بسؤال واحد وهيكبر معاك.')" />
    @else
        {{-- جدول 6 أعمدة على الديسكتوب · كروت رأسيّة بلا تمرير أفقيّ على الموبايل (2.15-ج) --}}
        <div class="card p-0 overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">السؤال</th>
                        <th class="text-start px-4 py-3 font-semibold">النوع</th>
                        <th class="text-start px-4 py-3 font-semibold">التدريب ← الدرس</th>
                        <th class="text-start px-4 py-3 font-semibold">عامّ</th>
                        <th class="text-start px-4 py-3 font-semibold">الاستخدام</th>
                        <th class="text-start px-4 py-3 font-semibold">الحالة</th>
                        <th class="text-start px-4 py-3 font-semibold">⋯</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($questions as $question)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3 max-w-xs">
                                <div class="truncate" title="{{ $question->prompt }}">{{ $question->prompt }}</div>
                                <div class="text-xs mt-0.5" style="color: var(--text-muted)">
                                    الصعوبة: {{ $difficulties[$question->difficulty] ?? $question->difficulty }}
                                    @if (($rates[$question->id] ?? null) !== null)
                                        · صحّ {{ $rates[$question->id] }}%
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $types[$question->type] ?? $question->type }}</td>
                            <td class="px-4 py-3">
                                <div class="text-xs">{{ $question->course_title }}</div>
                                <div class="text-xs" style="color: var(--text-muted)">← {{ $question->lesson_title }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="$question->is_general ? 'honor' : 'idle'"
                                               :label="$question->is_general ? 'عامّ' : 'خاصّ بالدرس'" />
                            </td>
                            <td class="px-4 py-3">{{ $usage[$question->id] ?? 0 }} امتحان</td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="$question->is_active ? 'ok' : 'idle'"
                                               :label="$question->is_active ? 'نشط' : 'معطّل'" />
                            </td>
                            <td class="px-4 py-3">
                                @include('admin.question-bank.partials.row-actions', ['question' => $question])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 md:hidden">
            @foreach ($questions as $question)
                <article class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold break-words">{{ $question->prompt }}</p>
                        <x-state-badge :state="$question->is_active ? 'ok' : 'idle'"
                                       :label="$question->is_active ? 'نشط' : 'معطّل'" />
                    </div>
                    <dl class="mt-3 text-xs space-y-1" style="color: var(--text-muted)">
                        <div><dt class="inline">النوع:</dt> <dd class="inline">{{ $types[$question->type] ?? $question->type }}</dd></div>
                        <div><dt class="inline">المكان:</dt> <dd class="inline">{{ $question->course_title }} ← {{ $question->lesson_title }}</dd></div>
                        <div><dt class="inline">الاستخدام:</dt> <dd class="inline">{{ $usage[$question->id] ?? 0 }} امتحان</dd></div>
                    </dl>
                    <div class="mt-3 pt-3" style="border-top: 1px solid var(--border)">
                        @include('admin.question-bank.partials.row-actions', ['question' => $question])
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4">{{ $questions->links() }}</div>
    @endif

    @include('admin.screens24.settings', [
        'settings' => $settings,
        'saveRoute' => route('admin.question-bank.settings'),
        'resetRoute' => route('admin.question-bank.settings.reset'),
        'blockTitle' => 'إعدادات بنك الأسئلة',
    ])
@endsection

@section('mobile_action')
    @if ($canCreate)
        <button type="button" data-modal-open="question-modal" data-question-new
                class="btn w-full rounded-xl px-4 py-3 text-sm font-bold"
                style="background: var(--color-brand-500); color: #04201c">+ سؤال</button>
    @endif
@endsection

@push('modals')
    @if ($canCreate || $canEdit)
        <x-modal id="question-modal" title="سؤال في البنك">
            <form method="post" action="{{ route('admin.question-bank.store') }}" data-question-form>
                @csrf
                <input type="hidden" name="_method" value="POST" data-question-method>

                <label class="block text-sm font-semibold mb-1" for="q-lesson">الدرس</label>
                <select name="lesson_id" id="q-lesson" required class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($lessons as $lesson)
                        <option value="{{ $lesson->id }}">{{ $lesson->course_name }} ← {{ $lesson->title_ar }}</option>
                    @endforeach
                </select>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <label class="text-sm font-semibold">النوع
                        <select name="type" id="q-type" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold">الصعوبة
                        <select name="difficulty" id="q-difficulty" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($difficulties as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <label class="block text-sm font-semibold mb-1" for="q-prompt">نصّ السؤال</label>
                <textarea name="prompt" id="q-prompt" required rows="2" maxlength="500"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>

                <label class="block text-sm font-semibold mb-1" for="q-options">الخيارات (افصلها بـ |)</label>
                <input type="text" name="options" id="q-options" maxlength="2000"
                       placeholder="خيار أوّل | خيار تاني | خيار تالت"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <label class="text-sm font-semibold">الإجابة الصحيحة
                        <input type="text" name="correct_answer" id="q-correct" maxlength="255"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">Placeholder داخل الحقل
                        <input type="text" name="placeholder" id="q-placeholder" maxlength="255"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <label class="flex items-center gap-2 text-sm mb-2">
                    <input type="hidden" name="is_general" value="0">
                    <input type="checkbox" name="is_general" id="q-general" value="1">
                    <span>سؤال عامّ — يدخل بنك الامتحان النهائيّ</span>
                </label>

                <label class="flex items-center gap-2 text-sm mb-4">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="q-active" value="1" checked>
                    <span>نشط</span>
                </label>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">احفظ السؤال</button>
            </form>
        </x-modal>
    @endif

    @if ($canReuse)
        <x-modal id="reuse-modal" title="إعادة استخدام السؤال في امتحان">
            <form method="post" action="{{ route('admin.question-bank.index') }}" data-reuse-form>
                @csrf
                <p class="text-sm mb-3" style="color: var(--text-muted)">
                    السؤال هيتنسخ للامتحانات المختارة مع رابط بأصله — فتعديل الأصل مش هيكسر امتحانًا اتأدّى.
                </p>

                <div class="space-y-2 mb-4 max-h-64 overflow-y-auto">
                    @forelse ($exams as $exam)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="exam_ids[]" value="{{ $exam->id }}">
                            <span>{{ $exam->title_ar }}</span>
                        </label>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">مافيش امتحانات نشطة لسّه.</p>
                    @endforelse
                </div>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">ضيفه للامتحانات المختارة</button>
            </form>
        </x-modal>
    @endif

    @if ($canEdit)
        <x-modal id="move-modal" title="نقل السؤال لدرس آخر">
            <form method="post" action="{{ route('admin.question-bank.index') }}" data-move-form>
                @csrf
                <label class="block text-sm font-semibold mb-1" for="move-lesson">الدرس الجديد</label>
                <select name="lesson_id" id="move-lesson" required class="w-full rounded-xl px-3 py-2 text-sm mb-4"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($lessons as $lesson)
                        <option value="{{ $lesson->id }}">{{ $lesson->course_name }} ← {{ $lesson->title_ar }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">انقل</button>
            </form>
        </x-modal>
    @endif

    @if ($canImport)
        <x-modal id="import-modal" title="استيراد أسئلة من CSV">
            <form method="post" action="{{ route('admin.question-bank.import') }}" enctype="multipart/form-data">
                @csrf
                <p class="text-sm mb-3" style="color: var(--text-muted)">
                    أعمدة القالب: <code>{{ setting('question_bank.import_columns', 'lesson_id,type,prompt,placeholder,options,correct_answer,is_general,difficulty') }}</code>
                </p>

                <label class="block text-sm font-semibold mb-1" for="import-file">الملفّ</label>
                <input type="file" name="file" id="import-file" accept=".csv,text/csv" required
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="import-lesson">درس افتراضيّ للصفوف بلا <code>lesson_id</code></label>
                <select name="lesson_id" id="import-lesson" class="w-full rounded-xl px-3 py-2 text-sm mb-4"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">— بلا درس افتراضيّ —</option>
                    @foreach ($lessons as $lesson)
                        <option value="{{ $lesson->id }}">{{ $lesson->course_name }} ← {{ $lesson->title_ar }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">ارفع واستورد</button>
            </form>
        </x-modal>
    @endif
@endpush

@push('scripts')
    <script>
        (() => {
            const open = (id) => {
                const modal = document.getElementById(id);
                if (!modal) return null;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                return modal;
            };

            const form = document.querySelector('[data-question-form]');
            const method = document.querySelector('[data-question-method]');
            const storeUrl = @json(route('admin.question-bank.store'));

            document.querySelectorAll('[data-question-new]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (!form) return;
                    form.action = storeUrl;
                    method.value = 'POST';
                    form.querySelector('#q-prompt').value = '';
                    form.querySelector('#q-options').value = '';
                    form.querySelector('#q-correct').value = '';
                    form.querySelector('#q-placeholder').value = '';
                    form.querySelector('#q-general').checked = false;
                    form.querySelector('#q-active').checked = true;
                });
            });

            document.querySelectorAll('[data-question-edit]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (!form) return;
                    form.action = btn.dataset.action;
                    method.value = 'PUT';
                    form.querySelector('#q-lesson').value = btn.dataset.lesson;
                    form.querySelector('#q-type').value = btn.dataset.type;
                    form.querySelector('#q-difficulty').value = btn.dataset.difficulty;
                    form.querySelector('#q-prompt').value = btn.dataset.prompt;
                    form.querySelector('#q-options').value = btn.dataset.options || '';
                    form.querySelector('#q-correct').value = btn.dataset.correct || '';
                    form.querySelector('#q-placeholder').value = btn.dataset.placeholder || '';
                    form.querySelector('#q-general').checked = btn.dataset.general === '1';
                    form.querySelector('#q-active').checked = btn.dataset.active === '1';
                    open('question-modal');
                });
            });

            const reuseForm = document.querySelector('[data-reuse-form]');
            document.querySelectorAll('[data-question-reuse]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (!reuseForm) return;
                    reuseForm.action = btn.dataset.action;
                    open('reuse-modal');
                });
            });

            const moveForm = document.querySelector('[data-move-form]');
            document.querySelectorAll('[data-question-move]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (!moveForm) return;
                    moveForm.action = btn.dataset.action;
                    moveForm.querySelector('#move-lesson').value = btn.dataset.lesson;
                    open('move-modal');
                });
            });
        })();
    </script>
@endpush
