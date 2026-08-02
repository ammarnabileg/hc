@extends('layouts.app')

@section('title', 'التحكيمات')

@section('content')
    <x-page-header
        title="التحكيمات"
        subtitle="الفصل في خلاف مالك/مساهم بقرار نهائيّ لا يُعاد."
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'التحكيمات']]" />

    <p class="card p-3 mb-4 text-sm" style="border-inline-start: 3px solid var(--color-state-danger)">
        ◉ فوات الـ24 = {{ $slowdown }} Rep · وفوات نافذة السقف 48 = تسوية آليّة 50% للطرفين.
        @if ($lateMessages > 0)
            <span class="ms-2 rounded-full px-2 py-0.5 text-xs"
                  style="background: color-mix(in srgb, var(--color-state-danger) 18%, transparent); color: var(--color-state-danger)">
                {{ $lateMessages }} فائتة
            </span>
        @endif
    </p>

    <x-filters :action="route('volunteer.arbitrations')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الحالة</span>
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
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="نصّ الاعتراض…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($cases->isEmpty())
        <x-empty message="مفيش قضايا على مكتبك — كلّه تمام" />
    @else
        {{-- نمط «قائمة + بانل» موحَّد — وعلى الموبايل العمودان شاشة واحدة (2.15-ج) --}}
        <div class="grid lg:grid-cols-[320px_1fr] gap-4">
            <aside class="space-y-2 order-1">
                @foreach ($cases as $case)
                    <a href="{{ route('volunteer.arbitrations', ['case' => $case->id] + $filters) }}"
                       class="card p-3 block motion-standard"
                       @if ($selected && $selected->id === $case->id) style="border-color: var(--color-brand-500)" @endif>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold">قضيّة #{{ $case->id }}</span>
                            <x-state-badge :state="$case->status === 'decided' ? 'ok' : $engine->windowState($case->window_due_at)"
                                           :label="$statuses[$case->status] ?? $case->status" />
                        </div>
                        <p class="text-xs mt-1 truncate" style="color: var(--text-muted)">{{ $case->claim }}</p>
                    </a>
                @endforeach
            </aside>

            <section class="order-2 min-w-0">
                @if ($file)
                    @include('volunteer.escalations.arbitrations.partials.file', ['file' => $file])
                @else
                    <x-empty message="اختر قضيّة من القائمة." />
                @endif
            </section>
        </div>
    @endif
@endsection
