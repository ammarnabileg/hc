@extends('layouts.volunteer')

@section('title', setting('volunteer.reviews_batch.title', 'مراجعة دفعة الصب-تاسكات'))

@section('content')
    <x-page-header
        :title="setting('volunteer.reviews_batch.title', 'مراجعة دفعة الصب-تاسكات')"
        :subtitle="setting('volunteer.reviews_batch.subtitle', 'المهمّة الأمّ: ').$parent->title"
        :breadcrumbs="[
            ['label' => setting('volunteer.reviews_batch.label', 'بانتظار مراجعتي'), 'url' => route('volunteer.reviews')],
            ['label' => setting('volunteer.reviews_batch.label_2', 'مراجعة دفعة')],
        ]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد بارز: الموافقة الجماعيّة بضغطة (2.15-أ-2) --}}
            <form method="post" action="{{ route('volunteer.reviews.batch.approve', $parent) }}">
                @csrf
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.reviews_batch.action', 'موافقة جماعيّة') }}</button>
            </form>
        </x-slot:action>
    </x-page-header>

    <p class="card p-3 mb-4 text-sm" style="border-inline-start: 3px solid var(--color-state-warn)">
        ▲ {{ setting('volunteer.reviews_batch.text', 'فوات نافذة السقف على هذه الحالة =') }} <strong>{{ setting('volunteer.reviews_batch.strong', 'اعتماد الدفعة كاملة') }}</strong> {{ setting('volunteer.reviews_batch.text_2', 'آليًّا.') }}
    </p>

    @if ($items->isEmpty())
        <x-empty :message="setting('volunteer.reviews_batch.empty', 'الدفعة فاضية — مفيش صب-تاسكات مرفوعة للمراجعة.')" />
    @else
        <div class="space-y-3">
            @foreach ($items as $item)
                <article class="card p-4">
                    {{-- تعديل مباشر على أيّ بند — استثناء منصوص يُسجَّل في سجلّ النسخ --}}
                    <form method="post" action="{{ route('volunteer.reviews.batch.edit', $item) }}" class="grid md:grid-cols-4 gap-3 items-end">
                        @csrf
                        <x-form.input name="title" :label="setting('volunteer.reviews_batch.label_3', 'العنوان')" :value="$item->title" />
                        <x-form.input name="deadline_at" :label="setting('volunteer.reviews_batch.label_4', 'الديدلاين')" type="datetime-local"
                                      :value="$item->deadline_at?->format('Y-m-d\TH:i')" />
                        <x-form.input name="vxp_value" label="VXP" type="number" :value="(float) $item->vxp_value" />
                        <div class="flex items-center gap-2">
                            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.reviews_batch.action_2', 'حفظ') }}</button>
                        </div>
                    </form>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <x-state-badge :state="$item->batch_status === 'approved' ? 'ok' : 'warn'"
                                       :label="$item->batch_status ?? setting('volunteer.reviews_batch.label_5', 'مسودّة')" />

                        {{-- حذف بند يرجع مسودّة لصاحبه بملاحظة، ويُعتمَد الباقي --}}
                        <form method="post" action="{{ route('volunteer.reviews.batch.remove', $item) }}"
                              class="flex items-center gap-2">
                            @csrf
                            @method('delete')
                            <input type="text" name="note" placeholder="{{ setting('volunteer.reviews_batch.placeholder', 'ملاحظة لصاحب البند') }}"
                                   class="rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <button type="submit" class="rounded-xl px-4 py-2 text-sm"
                                    style="border: 1px solid var(--border)">{{ setting('volunteer.reviews_batch.action_3', 'رجّعه مسودّة') }}</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
