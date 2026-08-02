@extends('layouts.app')

@section('title', 'مراجعة دفعة الصب-تاسكات')

@section('content')
    <x-page-header
        title="مراجعة دفعة الصب-تاسكات"
        :subtitle="'المهمّة الأمّ: '.$parent->title"
        :breadcrumbs="[
            ['label' => 'بانتظار مراجعتي', 'url' => route('volunteer.reviews')],
            ['label' => 'مراجعة دفعة'],
        ]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد بارز: الموافقة الجماعيّة بضغطة (2.15-أ-2) --}}
            <form method="post" action="{{ route('volunteer.reviews.batch.approve', $parent) }}">
                @csrf
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">موافقة جماعيّة</button>
            </form>
        </x-slot:action>
    </x-page-header>

    <p class="card p-3 mb-4 text-sm" style="border-inline-start: 3px solid var(--color-state-warn)">
        ▲ فوات نافذة السقف على هذه الحالة = <strong>اعتماد الدفعة كاملة</strong> آليًّا.
    </p>

    @if ($items->isEmpty())
        <x-empty message="الدفعة فاضية — مفيش صب-تاسكات مرفوعة للمراجعة." />
    @else
        <div class="space-y-3">
            @foreach ($items as $item)
                <article class="card p-4">
                    {{-- تعديل مباشر على أيّ بند — استثناء منصوص يُسجَّل في سجلّ النسخ --}}
                    <form method="post" action="{{ route('volunteer.reviews.batch.edit', $item) }}" class="grid md:grid-cols-4 gap-3 items-end">
                        @csrf
                        <x-form.input name="title" label="العنوان" :value="$item->title" />
                        <x-form.input name="deadline_at" label="الديدلاين" type="datetime-local"
                                      :value="$item->deadline_at?->format('Y-m-d\TH:i')" />
                        <x-form.input name="vxp_value" label="VXP" type="number" :value="(float) $item->vxp_value" />
                        <div class="flex items-center gap-2">
                            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">حفظ</button>
                        </div>
                    </form>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <x-state-badge :state="$item->batch_status === 'approved' ? 'ok' : 'warn'"
                                       :label="$item->batch_status ?? 'مسودّة'" />

                        {{-- حذف بند يرجع مسودّة لصاحبه بملاحظة، ويُعتمَد الباقي --}}
                        <form method="post" action="{{ route('volunteer.reviews.batch.remove', $item) }}"
                              class="flex items-center gap-2">
                            @csrf
                            @method('delete')
                            <input type="text" name="note" placeholder="ملاحظة لصاحب البند"
                                   class="rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <button type="submit" class="rounded-xl px-4 py-2 text-sm"
                                    style="border: 1px solid var(--border)">رجّعه مسودّة</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
