@extends('layouts.app')

@section('title', setting('complaints.reasons.page_title', 'أسباب الشكاوى والمقترحات'))

@section('content')
    {{-- الأسباب قابلة للإدارة من لوحة الأدمن: إضافة/تعديل/حذف (الدستور 11) --}}
    <x-page-header
        :title="setting('complaints.reasons.page_title', 'أسباب الشكاوى والمقترحات')"
        :subtitle="setting('complaints.reasons.page_subtitle', 'دي القائمة اللي بيختار منها المستخدم — عدّلها زيّ ما تحبّ.')"
        :breadcrumbs="[
            ['label' => setting('complaints.admin.section_label', 'التوجيه والدعم'), 'url' => route('admin.guidance.index')],
            ['label' => setting('complaints.admin.queue_label', 'الشكاوى'), 'url' => route('admin.guidance.complaints')],
            ['label' => setting('complaints.reasons.page_title', 'أسباب الشكاوى والمقترحات')],
        ]" />

    <x-tabs :tabs="$tabs" current="complaints" />

    <form method="post" action="{{ route('admin.guidance.complaint_reasons.update') }}" class="card p-4" data-reasons>
        @csrf
        @method('put')

        <div class="space-y-2" data-reasons-list>
            @foreach ($reasons as $reason)
                <div class="flex items-center gap-2" data-reason-row>
                    <input type="text" name="reasons[]" value="{{ $reason }}" maxlength="48"
                           class="flex-1 rounded-xl px-3 text-sm"
                           style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                    @if (($inUse[$reason] ?? 0) > 0)
                        {{-- الحذف لا يُخفي شكاوى قائمة: الرقم يوضّح الأثر قبل الفعل (2.15-د) --}}
                        <span class="text-xs whitespace-nowrap" style="color: var(--text-muted)">
                            {{ $inUse[$reason] }} {{ setting('complaints.reasons.in_use_suffix', 'تذكرة مرتبطة') }}
                        </span>
                    @endif

                    <button type="button" data-reason-remove
                            class="rounded-lg px-3 text-sm"
                            style="min-height: 44px; background: var(--surface-sunken); color: var(--text-muted)"
                            aria-label="{{ setting('complaints.reasons.remove_label', 'حذف السبب') }}">
                        {{-- أيقونة مرسومة بهويّة المنصّة — بلا أيّ مكتبة أيقونات --}}
                        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" fill="none"
                             stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                            <path d="M3 3l8 8M11 3l-8 8" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>

        <template data-reason-template>
            <div class="flex items-center gap-2" data-reason-row>
                <input type="text" name="reasons[]" value="" maxlength="48"
                       placeholder="{{ setting('complaints.reasons.new_placeholder', 'سبب جديد') }}"
                       class="flex-1 rounded-xl px-3 text-sm"
                       style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <button type="button" data-reason-remove class="rounded-lg px-3 text-sm"
                        style="min-height: 44px; background: var(--surface-sunken); color: var(--text-muted)"
                        aria-label="{{ setting('complaints.reasons.remove_label', 'حذف السبب') }}">
                    <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" fill="none"
                         stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                        <path d="M3 3l8 8M11 3l-8 8" />
                    </svg>
                </button>
            </div>
        </template>

        @error('reasons')
            <p class="text-xs mt-2" style="color: var(--color-state-danger)">{{ $message }}</p>
        @enderror

        <div class="flex flex-wrap items-center justify-between gap-2 mt-4">
            <button type="button" data-reason-add class="btn rounded-xl px-4 text-sm"
                    style="min-height: 44px; background: var(--surface-sunken); color: var(--text)">
                {{ setting('complaints.reasons.add_label', 'إضافة سبب') }}
            </button>

            <button type="submit" class="btn rounded-xl px-5 text-sm font-semibold motion-standard"
                    style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                {{ setting('complaints.reasons.save_label', 'حفظ الأسباب') }}
            </button>
        </div>

        <p class="text-xs mt-3" style="color: var(--text-muted)">
            {{ setting('complaints.reasons.defaults_hint', 'الافتراضيّ:') }} {{ implode(' · ', $defaults) }}
        </p>
    </form>
@endsection

@section('mobile_action')
    <button type="submit" form="" onclick="document.querySelector('[data-reasons]').requestSubmit()"
            class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
            style="background: var(--color-brand-500); color: #04201c">
        {{ setting('complaints.reasons.save_label', 'حفظ الأسباب') }}
    </button>
@endsection

@push('scripts')
<script>
/* إضافة/حذف صفّ سبب — ردٌّ فوريّ بلا إعادة تحميل (2.17-ب) */
(function () {
    const form = document.querySelector('[data-reasons]');
    if (!form) return;

    const list = form.querySelector('[data-reasons-list]');
    const tpl = form.querySelector('[data-reason-template]');

    form.querySelector('[data-reason-add]').addEventListener('click', () => {
        list.appendChild(tpl.content.cloneNode(true));
        list.lastElementChild.querySelector('input')?.focus();
    });

    list.addEventListener('click', (e) => {
        if (!e.target.closest('[data-reason-remove]')) return;
        e.target.closest('[data-reason-row]').remove();
    });
})();
</script>
@endpush
