@extends('layouts.admin')
@section('title', setting('admin.wars.bank.index.bnk_asyla_alhrwb', 'بنك أسئلة الحروب'))

@section('content')
    @php
        $canEdit = auth()->user()->can('wars_bank.edit') || auth()->user()->can('wars_bank.create');
        $canSeeAnswers = auth()->user()->can('wars_bank.view');
        $revealed = session('reveal_answer');
    @endphp

    <x-page-header
        :title="setting('admin.wars.bank.index.bnk_asyla_alhrwb', 'بنك أسئلة الحروب')"
        :subtitle="setting('admin.wars.bank.index.asyla_alsaha_bijabatha_wdrjat_sawbtha', 'أسئلة الساحة بإجاباتها ودرجات صعوبتها — والتصحيح على الخادم دائمًا.')"
        :breadcrumbs="[['label' => setting('admin.wars.bank.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')], ['label' => setting('admin.wars.bank.index.bnk_asyla_alhrwb', 'بنك أسئلة الحروب')]]">
        <x-slot:action>
            @if ($canEdit)
                <button type="button" data-modal-open="wq-new"
                        class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.wars.bank.index.swal', '+ سؤال') }}</button>
            @endif
        </x-slot:action>
    </x-page-header>

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-kpi :label="setting('admin.wars.bank.index.kl_alasyla', 'كلّ الأسئلة')" :value="$counts['all']" icon="folder" />
        <x-kpi :label="setting('admin.wars.bank.index.almfala', 'المفعّلة')" :value="$counts['active']" icon="check" />
        <x-kpi :label="setting('admin.wars.bank.index.alrqmya_almfala', 'الرقميّة المفعّلة')" :value="$counts['numeric']" icon="level" />
        <x-kpi :label="setting('admin.wars.bank.index.bla_ijaba', 'بلا إجابة')" :value="$counts['missing']" icon="question" />
    </div>

    @if ($counts['active'] < $minActive)
        <div class="card p-3 mb-4 text-sm flex items-center gap-2" style="border-color: var(--color-state-warn)">
            <span aria-hidden="true">▲</span>
            <span>
                {!! strtr(setting('admin.wars.bank.index.almfal_v1_walhd_aladna_ltshghyl_hrb_v2', 'المفعّل :v1 والحدّ الأدنى لتشغيل حرب :v2 — الحروب مش هتشتغل لحدّ ما توصل للحدّ.'), [':v1' => e($counts['active']), ':v2' => e($minActive)]) !!}
            </span>
        </div>
    @endif

    @if (session('import_errors') && count(session('import_errors')))
        <div class="card p-3 mb-4 text-sm" style="border-color: var(--color-state-danger)">
            <p class="font-bold mb-1">{{ setting('admin.wars.bank.index.alastyrad_atwqf_wla_swal_atdaf', '◉ الاستيراد اتوقف — ولا سؤال اتضاف:') }}</p>
            <ul class="space-y-1 text-xs">
                @foreach (session('import_errors') as $error)
                    <li>• {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('import_preview'))
        <div class="card p-4 mb-4">
            <h2 class="font-bold mb-2">{!! strtr(setting('admin.wars.bank.index.maayna_alastyrad_v1_sf', 'معاينة الاستيراد (:v1 صفّ)'), [':v1' => e(count(session('import_preview')))]) !!}</h2>
            <ul class="space-y-1 text-xs mb-3" style="color: var(--text-muted)">
                @foreach (array_slice(session('import_preview'), 0, 8) as $row)
                    <li>• {{ $row['text'] }} — {{ $row['difficulty'] }} / {{ $row['source'] }}</li>
                @endforeach
            </ul>
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('admin.wars.bank.index.arfa_nfs_almlf_tany_ma_aatmd_alastyrad_ashan', 'ارفع نفس الملفّ تاني مع «اعتمد الاستيراد» عشان يتسجّل.') }}</p>
        </div>
    @endif

    {{-- بحث أوّلًا دائمًا، ثمّ فلترين إضافيّين ظاهرين، والباقي مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('admin.wars.bank.index')">
        <label class="block flex-1 min-w-40">
            <span class="block text-sm mb-1">{{ setting('admin.wars.bank.index.bhth', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['search'] }}" placeholder="{{ setting('admin.wars.bank.index.ns_alswal', 'نصّ السؤال…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.wars.bank.index.alsawba', 'الصعوبة') }}</span>
            <select name="difficulty" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.wars.bank.index.alkl', 'الكلّ') }}</option>
                @foreach ($difficulties as $key => $label)
                    <option value="{{ $key }}" @selected($filters['difficulty'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.wars.bank.index.alhala', 'الحالة') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.wars.bank.index.alkl', 'الكلّ') }}</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.wars.bank.index.tbq', 'طبّق') }}</button>

        <x-slot:advanced>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.wars.bank.index.almsdr', 'المصدر') }}</span>
                <select name="source" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('admin.wars.bank.index.alkl', 'الكلّ') }}</option>
                    @foreach ($sources as $key => $label)
                        <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="missing" value="1" class="w-5 h-5" @checked($filters['missing'])>
                <span>{{ setting('admin.wars.bank.index.bla_ijaba', 'بلا إجابة') }}</span>
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($questions->isEmpty())
        <x-empty :message="setting('admin.wars.bank.index.albnk_fargh_alhrwb_ln_taml', 'البنك فارغ — الحروب لن تعمل.')" />
    @else
        <form method="post" action="{{ route('admin.wars.bank.bulk') }}">
            @csrf

            @can('wars_bank.archive')
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <select name="action" class="rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="activate">{{ setting('admin.wars.bank.index.tfayl', 'تفعيل') }}</option>
                        <option value="draft">{{ setting('admin.wars.bank.index.thwyl_lmswda', 'تحويل لمسودّة') }}</option>
                        <option value="archive">{{ setting('admin.wars.bank.index.arshfa', 'أرشفة') }}</option>
                    </select>
                    <button type="submit" class="rounded-xl px-4 py-2 text-sm motion-standard"
                            style="background: var(--surface-sunken); color: var(--text); border: 1px solid var(--border); min-height: 44px">
                        {{ setting('admin.wars.bank.index.nfdh_ala_almhdd', 'نفّذ على المحدَّد') }}
                    </button>
                </div>
            @endcan

            {{-- على الموبايل كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
            <div class="hidden md:block min-w-0 overflow-x-auto">
                <table class="w-full text-sm"
                       {{-- حدّ الأعمدة الافتراضيّ من الإعدادات، و«وضع متقدّم» يرفعه (2.15-أ-5) --}}
                       @unless (advanced_mode()) data-columns-cap="{{ view_mode()->defaultColumns() }}" @endunless>
                    <thead>
                        <tr style="color: var(--text-muted)">
                            <th class="p-2 text-start"><span class="sr-only">{{ setting('admin.wars.bank.index.thdyd', 'تحديد') }}</span></th>
                            <th class="p-2 text-start">{{ setting('admin.wars.bank.index.alswal', 'السؤال') }}</th>
                            <th class="p-2 text-start">{{ setting('admin.wars.bank.index.alijaba', 'الإجابة') }}</th>
                            <th class="p-2 text-start">{{ setting('admin.wars.bank.index.alsawba', 'الصعوبة') }}</th>
                            <th class="p-2 text-start">{{ setting('admin.wars.bank.index.almsdr', 'المصدر') }}</th>
                            <th class="p-2 text-start">{{ setting('admin.wars.bank.index.alastkhdam', 'الاستخدام') }}</th>
                            <th class="p-2 text-start">{{ setting('admin.wars.bank.index.alhala', 'الحالة') }}</th>
                            <th class="p-2 text-start">{{ setting('admin.wars.bank.index.ijraat', 'إجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($questions as $question)
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="p-2"><input type="checkbox" name="ids[]" value="{{ $question->id }}" class="w-5 h-5"></td>
                                <td class="p-2">{{ $question->text }}</td>
                                <td class="p-2">
                                    @include('admin.wars.bank.answer-cell', ['question' => $question, 'revealed' => $revealed, 'canSeeAnswers' => $canSeeAnswers])
                                </td>
                                <td class="p-2">{{ $difficulties[$question->difficulty] ?? $question->difficulty }}</td>
                                <td class="p-2">{{ $sources[$question->source] ?? $question->source }}</td>
                                <td class="p-2 tabular-nums">{{ (int) $question->usage_count }}</td>
                                <td class="p-2">
                                    <x-state-badge :state="['active' => 'ok', 'draft' => 'warn', 'archived' => 'idle'][$question->status] ?? 'idle'"
                                                   :label="$statuses[$question->status] ?? $question->status" />
                                </td>
                                <td class="p-2">
                                    @if ($canEdit)
                                        <button type="button" data-modal-open="wq-edit-{{ $question->id }}"
                                                class="rounded-lg px-3 py-2 text-xs motion-standard"
                                                style="background: var(--surface-sunken); color: var(--text); min-height: 44px">{{ setting('admin.wars.bank.index.tadyl', 'تعديل') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="md:hidden space-y-3">
                @foreach ($questions as $question)
                    <article class="card p-4">
                        <div class="flex items-start gap-2">
                            <input type="checkbox" name="ids[]" value="{{ $question->id }}" class="w-5 h-5 mt-1">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm">{{ $question->text }}</p>
                                <p class="text-xs mt-1" style="color: var(--text-muted)">
                                    {{ $difficulties[$question->difficulty] ?? $question->difficulty }} ·
                                    {{ $sources[$question->source] ?? $question->source }} {{ setting('admin.wars.bank.index.astkhdm', '· استُخدم') }} {{ (int) $question->usage_count }}
                                </p>
                                <div class="mt-2 flex items-center gap-2">
                                    <x-state-badge :state="['active' => 'ok', 'draft' => 'warn', 'archived' => 'idle'][$question->status] ?? 'idle'"
                                                   :label="$statuses[$question->status] ?? $question->status" />
                                    @include('admin.wars.bank.answer-cell', ['question' => $question, 'revealed' => $revealed, 'canSeeAnswers' => $canSeeAnswers])
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </form>

        <div class="mt-4">{{ $questions->links() }}</div>
    @endif

    <div class="mt-6 flex flex-wrap gap-2">
        @can('wars_bank.import')
            <form method="post" action="{{ route('admin.wars.bank.import') }}" enctype="multipart/form-data"
                  class="card p-4 flex flex-wrap items-end gap-3">
                @csrf
                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.wars.bank.index.astyrad_dfaa_csv', 'استيراد دفعة CSV') }}</span>
                    <input type="file" name="file" accept=".csv,text/csv" required class="text-sm">
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="confirm" value="1" class="w-5 h-5">
                    <span>{{ setting('admin.wars.bank.index.aatmd_alastyrad', 'اعتمد الاستيراد') }}</span>
                </label>
                <button type="submit" class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                        style="background: var(--surface-sunken); color: var(--text); border: 1px solid var(--border); min-height: 44px">
                    {{ setting('admin.wars.bank.index.arfa', 'ارفع') }}
                </button>
            </form>
        @endcan

        @can('wars_bank.export')
            <a href="{{ route('admin.wars.bank.export') }}"
               class="self-start rounded-xl px-4 py-2.5 text-sm motion-standard"
               style="background: var(--surface-sunken); color: var(--text); border: 1px solid var(--border); min-height: 44px">
                {{ setting('admin.wars.bank.index.tsdyr_csv', 'تصدير CSV') }}
            </a>
        @endcan
    </div>

    @if ($canEdit)
        @push('modals')
            @include('admin.wars.bank.form', ['id' => 'wq-new', 'question' => null, 'difficulties' => $difficulties, 'sources' => $sources, 'statuses' => $statuses])
            @foreach ($questions as $question)
                @include('admin.wars.bank.form', ['id' => 'wq-edit-'.$question->id, 'question' => $question, 'difficulties' => $difficulties, 'sources' => $sources, 'statuses' => $statuses])
            @endforeach
        @endpush
    @endif
@endsection

@section('mobile_action')
    @if ($canEdit)
        <button type="button" data-modal-open="wq-new"
                class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.wars.bank.index.swal', '+ سؤال') }}</button>
    @endif
@endsection
