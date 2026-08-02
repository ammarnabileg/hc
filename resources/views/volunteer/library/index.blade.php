@extends('layouts.app')

@section('title', 'المكتبة الداخليّة')

@php
    /**
     * المكتبة الداخليّة (23-3.3 · 24.4-10).
     * ⛔ **بلا زرّ رفع يدويّ** — الفهرسة آليّة لحظة الاعتماد.
     * ⭐ البحث **داخل محتوى المُدخَل نفسه**، والنتيجة تعرض **مقتطفًا بتظليل الكلمة**.
     * ⭐ ما هو خارج نطاقي **يظهر بعنوانه وقفلٍ وزرّ [اطلب وصولًا]** — لا يُخفى.
     */
@endphp

@section('content')
    <x-page-header
        title="المكتبة الداخليّة"
        :subtitle="$items->count().' مُدخَلًا — الفهرسة آليّة لحظة الاعتماد'"
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'المكتبة الداخليّة']]">
        <x-slot:action>
            <a href="{{ route('volunteer.library', array_filter($filters + ['view' => $view === 'grid' ? 'list' : 'grid'])) }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm" style="background: var(--surface-sunken)">
                {{ $view === 'grid' ? 'عرض قائمة' : 'عرض شبكة' }}
            </a>
        </x-slot:action>
    </x-page-header>

    <x-filters :action="route('volunteer.library')">
        <input type="hidden" name="view" value="{{ $view }}">

        {{-- بحث نصّيّ بارز — وهو بطل الشاشة --}}
        <label class="text-sm flex-1 min-w-56 order-first">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">ابحث داخل المحتوى</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="كلمة داخل المستند نفسه…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">النوع</span>
            <select name="type" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="mine" value="1" @checked((bool) $filters['owner']) onchange="this.form.submit()">
            مخرجاتي
        </label>

        <x-slot:advanced>
            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">الكيان</span>
                <select name="entity" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}" @selected((int) $filters['entity'] === (int) $entity->id)>{{ $entity->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">المسار</span>
                <select name="track" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    @foreach ($tracks as $track)
                        <option value="{{ $track->id }}" @selected((int) $filters['track'] === (int) $track->id)>{{ $track->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">مستوى الوصول</span>
                <select name="access" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    @foreach ($accessLevels as $key => $label)
                        <option value="{{ $key }}" @selected($filters['access'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">وسم</span>
                <select name="tag" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag }}" @selected($filters['tag'] === $tag)>{{ $tag }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">من</span>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            </label>

            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">إلى</span>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            </label>

            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">طبّق</button>
        </x-slot:advanced>
    </x-filters>

    @if ($items->isEmpty())
        <x-empty message="المكتبة لسّه بتتكوّن — أوّل مخرج معتمَد هيظهر هنا تلقائيًّا" />
    @else
        <div class="{{ $view === 'grid' ? 'grid gap-4 md:grid-cols-3' : 'space-y-3' }}">
            @foreach ($items as $item)
                <article class="card p-4 animate-fadeup" data-library-item="{{ $item->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-bold text-sm">{{ $item->title }}</h2>
                        @if ($item->locked)
                            {{-- المقيَّد يظهر بعنوانه وقفله — لا يُخفى (23-3.3) --}}
                            <span class="text-xs shrink-0" style="color: var(--text-muted)" title="خارج نطاقك"><x-icon name="lock" size="16" /></span>
                        @endif
                    </div>

                    <p class="mt-1 text-xs" style="color: var(--text-muted)">
                        <span class="rounded-full px-2 py-0.5" style="background: var(--surface-sunken)">{{ $types[$item->type] ?? $item->type }}</span>
                        @if ($item->entity)
                            · {{ $item->entity->icon }} {{ $item->entity->name_ar }}
                        @endif
                        @if ($item->owner) · {{ $item->owner->shortName() }} @endif
                        @if ($item->approved_at) · {{ $item->approved_at->translatedFormat('j F Y') }} @endif
                    </p>

                    {{-- مقتطف من موضع المطابقة مع تظليل الكلمة (23-3.3) --}}
                    @if ($item->snippet)
                        <p class="mt-2 text-xs leading-6" style="color: var(--text-muted)">{!! $item->snippet !!}</p>
                    @endif

                    @if ($item->tags)
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($item->tags as $tag)
                                <span class="text-xs rounded-full px-2" style="background: var(--surface-sunken)">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($item->locked)
                            @if (in_array((int) $item->id, $pending, true))
                                <x-state-badge state="warn" label="طلبك بانتظار قرار الدايركتور" />
                            @else
                                <form method="post" action="{{ route('volunteer.library.request-access', $item) }}" class="flex gap-2">
                                    @csrf
                                    <input type="text" name="reason" placeholder="سبب (اختياريّ)" class="rounded-xl px-3 py-2 text-xs"
                                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    <button type="submit" class="btn rounded-xl px-3 py-2 text-sm font-semibold"
                                            style="background: var(--color-brand-500); color: #04201c">اطلب وصولًا</button>
                                </form>
                            @endif
                        @else
                            <a href="{{ route('volunteer.library.show', $item) }}"
                               class="btn rounded-xl px-3 py-2 text-sm font-semibold"
                               style="background: var(--color-brand-500); color: #04201c">افتح</a>
                            @if ($item->task_id)
                                <span class="text-xs self-center" style="color: var(--text-muted)">المهمّة المصدر #{{ $item->task_id }}</span>
                            @endif
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
