@extends('layouts.app')
@section('title', 'الشكاوى والمقترحات')

@php
    use App\Services\Account\ComplaintService;

    // على الموبايل: القائمة ثمّ Bottom Sheet — والبانل يفتح بوجود ?ticket (2.15-ج)
    $sheetOpen = request()->filled('ticket') && $selected;
    $closed = $selected && $selected->status === 'closed';
@endphp

@section('content')
    <x-page-header
        title="الشكاوى والمقترحات"
        subtitle="اكتب لنا، وهنتابع معاك لحدّ ما تتحلّ."
        :breadcrumbs="[['label' => 'الدعم', 'url' => route('complaints.index')], ['label' => 'الشكاوى والمقترحات']]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد بارز (2.15-أ-2) --}}
            <button type="button" data-modal-open="new-ticket"
                    class="btn hidden md:inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">تذكرة جديدة</button>
        </x-slot:action>
    </x-page-header>

    {{-- عدّادات الحالات — أربعة بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        @foreach ($statuses as $key => $label)
            <a href="{{ route('complaints.index', array_filter(['status' => $filters['status'] === $key ? null : $key])) }}"
               class="card p-3 motion-standard hover:opacity-90 @if($filters['status'] === $key) ring-1 @endif"
               @if($filters['status'] === $key) style="border-color: var(--color-brand-500)" @endif>
                <div class="flex items-center justify-between">
                    <span class="text-xs" style="color: var(--text-muted)">{{ $label }}</span>
                    <x-state-badge :state="ComplaintService::stateOf($key)" label="" />
                </div>
                <div class="mt-1 text-xl font-extrabold" data-count-to="{{ $counts[$key] ?? 0 }}">{{ $counts[$key] ?? 0 }}</div>
            </a>
        @endforeach
    </div>

    {{-- ثلاثة فلاتر ظاهرة: الحالة · النوع · بحث (2.15-أ-4) --}}
    <x-filters :action="route('complaints.index')">
        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الحالة</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">النوع</span>
            <select name="type" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث بالرقم أو العنوان</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="TK-… أو كلمة من العنوان"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">فلترة</button>
    </x-filters>

    @if ($tickets->isEmpty())
        {{-- سطر واحد + زرّ واحد **داخل** الحالة الفارغة نفسها (2.15-د) --}}
        <x-empty message="مفيش تذاكر لسّه — واحنا مستنّيين نسمع منك.">
            <button type="button" data-modal-open="new-ticket"
                    class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">تذكرة جديدة</button>
        </x-empty>
    @else
        {{-- نمط «قائمة + بانل» الموحَّد: يمين القائمة ويسار السلسلة (2.15-ب) --}}
        <div class="grid md:grid-cols-[minmax(0,22rem)_1fr] gap-4 items-start">

            <div class="space-y-2">
                @foreach ($tickets as $ticket)
                    <a href="{{ route('complaints.index', ['ticket' => $ticket->id] + array_filter($filters)) }}"
                       class="card block p-3 motion-standard hover:opacity-95 animate-fadeup"
                       @if ($selected && $selected->id === $ticket->id) style="border-color: var(--color-brand-500)" @endif>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-mono" style="color: var(--text-muted)">{{ $ticket->number }}</span>
                            <x-state-badge :state="ComplaintService::stateOf($ticket->status)" :label="$statuses[$ticket->status] ?? $ticket->status" />
                        </div>
                        <div class="mt-1 font-semibold text-sm truncate">{{ $ticket->title }}</div>
                        <div class="mt-1 flex items-center justify-between text-xs" style="color: var(--text-muted)">
                            <span>{{ $types[$ticket->type] ?? $ticket->type }}</span>
                            {{-- تواريخ نسبيّة والكامل بالـHover (2.15-د) --}}
                            <span title="{{ $ticket->updated_at?->format('Y-m-d H:i') }}">{{ $ticket->updated_at?->diffForHumans() }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            @if ($selected)
                <section @class([
                            'card p-4',
                            'hidden md:block' => ! $sheetOpen,
                            'fixed inset-x-0 bottom-0 top-20 z-40 overflow-y-auto rounded-b-none md:static md:inset-auto md:rounded-2xl' => $sheetOpen,
                         ])
                         style="{{ $sheetOpen ? 'background: var(--surface-raised)' : '' }}"
                         aria-label="سلسلة الردود">

                    <header class="flex items-start justify-between gap-3 pb-3" style="border-bottom: 1px solid var(--border)">
                        <div class="min-w-0">
                            <div class="text-xs font-mono" style="color: var(--text-muted)">{{ $selected->number }}</div>
                            <h2 class="font-bold truncate">{{ $selected->title }}</h2>
                            <div class="mt-1 flex items-center gap-2 text-xs" style="color: var(--text-muted)">
                                <span>{{ $types[$selected->type] ?? $selected->type }}</span>
                                @if ($selected->category)<span>· {{ $selected->category }}</span>@endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <x-state-badge :state="ComplaintService::stateOf($selected->status)" :label="$statuses[$selected->status] ?? $selected->status" />
                            {{-- الرجوع من الـBottom Sheet على الموبايل --}}
                            <a href="{{ route('complaints.index', array_filter($filters)) }}"
                               class="md:hidden text-sm opacity-70" aria-label="رجوع">✕</a>
                        </div>
                    </header>

                    <div class="py-4 space-y-3">
                        @foreach ($messages as $message)
                            <article class="flex gap-2 animate-fadeup">
                                <x-avatar :user="$message->user" size="9" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 text-xs" style="color: var(--text-muted)">
                                        <span class="font-semibold" style="color: var(--text)">{{ $message->user?->shortName() }}</span>
                                        <span title="{{ $message->created_at?->format('Y-m-d H:i') }}">{{ $message->created_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-1 text-sm whitespace-pre-line">{{ $message->body }}</p>
                                    @if ($message->attachment_path)
                                        <a href="{{ \Illuminate\Support\Facades\Storage::url($message->attachment_path) }}"
                                           class="mt-1 inline-block text-xs underline" style="color: var(--color-brand-500)">📎 مرفق</a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($closed)
                        {{-- المغلقة قراءة فقط بشارة (24.5) --}}
                        <div class="pt-3 text-sm" style="border-top: 1px solid var(--border); color: var(--text-muted)">
                            <x-state-badge state="idle" label="مغلقة — قراءة فقط" />
                            <span class="ms-2">لو ظهرت حاجة تانية، افتح تذكرة جديدة وهنكمّل معاك.</span>
                        </div>
                    @else
                        <form method="post" action="{{ route('complaints.reply', $selected) }}"
                              enctype="multipart/form-data" class="pt-3 space-y-2" style="border-top: 1px solid var(--border)">
                            @csrf
                            <textarea name="body" rows="3" required placeholder="اكتب ردّك هنا…"
                                      class="w-full rounded-xl px-3 py-2 text-sm"
                                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('body') }}</textarea>
                            @error('body')<p class="text-xs" style="color: var(--color-state-danger)">{{ $message }}</p>@enderror

                            <div class="flex flex-wrap items-center gap-2">
                                <input type="file" name="attachment" class="text-xs" aria-label="مرفق">
                                <div class="flex-1"></div>
                                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                        style="background: var(--color-brand-500); color: #04201c">{{ setting('complaints.field.submit_label', 'إرسال') }}</button>
                            </div>
                        </form>

                        <form method="post" action="{{ route('complaints.close', $selected) }}" class="mt-3 text-end">
                            @csrf
                            <button type="submit" class="text-xs underline" style="color: var(--text-muted)">إغلاق التذكرة</button>
                        </form>
                    @endif
                </section>
            @endif
        </div>
    @endif

    {{-- تذكرة جديدة: بوب-أب لا صفحة جديدة (2.15-أ-6) --}}
    <x-modal id="new-ticket" title="تذكرة جديدة">
        <form method="post" action="{{ route('complaints.store') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('complaints.field.type_label', 'النوع') }}</span>
                <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            {{-- ⭐ الحقل رقم 1 في القسم 11 — والأدمن يبني عليه قراره، فلا يُقرَأ بلا أن يُكتَب --}}
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('complaints.field.wants_contact_label', 'هل ترغب في التواصل معك؟') }}</span>
                <select name="wants_contact" required class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="1" @selected(old('wants_contact', '1') === '1')>{{ setting('complaints.field.wants_contact_yes', 'نعم') }}</option>
                    <option value="0" @selected(old('wants_contact') === '0')>{{ setting('complaints.field.wants_contact_no', 'لا') }}</option>
                </select>
                @error('wants_contact')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('complaints.field.reason_label', 'السبب') }}</span>
                <select name="category" required class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('complaints.field.reason_placeholder', 'اختر السبب') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                @error('category')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
            </label>

            <x-form.input name="title" :label="setting('complaints.field.title_label', 'العنوان المختصر')" :value="$prefillTitle" :placeholder="setting('complaints.field.title_placeholder', 'مثال: اقتراح تحسين المنصّة')" required />

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('complaints.field.body_label', 'نصّ الشكوى أو المقترح') }}</span>
                <textarea name="body" rows="5" required placeholder="{{ setting('complaints.field.body_placeholder', 'اكتب رسالتك هنا…') }}"
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('body') }}</textarea>
                @error('body')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('complaints.field.attachment_label', 'مرفق (اختياريّ)') }}</span>
                <input type="file" name="attachment" class="text-xs">
                <span class="block text-xs mt-1" style="color: var(--text-muted)">أقصى حجم {{ $maxKb }} كيلوبايت.</span>
            </label>

            <div class="text-end">
                <button type="submit" class="btn rounded-xl px-5 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('complaints.field.submit_label', 'إرسال') }}</button>
            </div>
        </form>
    </x-modal>
@endsection

{{-- الفعل الرئيسيّ على الموبايل في متناول الإبهام (2.15-ج) --}}
@section('mobile_action')
    <button type="button" data-modal-open="new-ticket"
            class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold motion-standard"
            style="background: var(--color-brand-500); color: #04201c">تذكرة جديدة</button>
@endsection

@push('scripts')
    @if ($openNew || $errors->any())
        <script>
            // «لم أجد إجابتي» في دليل المستخدم يفتح التذكرة بعنوان مملوء مسبقًا (24.5)
            document.getElementById('new-ticket')?.classList.replace('hidden', 'flex');
        </script>
    @endif
@endpush
