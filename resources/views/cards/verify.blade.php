@extends('layouts.guest')

@section('title', (string) setting('volunteer_card.verify.section_1', 'التحقّق من بطاقة المتطوّع'))

@php
    /**
     * صفحة التحقّق (13.4-ر-ج): تبيّن **سارية** أو **منتهية** بنفس منظومة الشهادات (8.1).
     * والبطاقة **لا تُحذَف** بانتهاء العضويّة — تبقى في السجلّ بتاريخيها.
     * ⛔ ولا بيانات تواصل هنا كذلك.
     */
    $dateFormat = (string) setting('volunteer.org.date_format', 'j F Y');
@endphp

@section('content')
    <div class="w-full max-w-sm">
        <div class="card p-5 text-center">
            <div class="flex justify-center mb-3">
                <x-state-badge :state="$valid ? 'ok' : 'idle'" :label="$valid ? (string) setting('volunteer_card.verify.label_1', 'سارية') : (string) setting('volunteer_card.verify.label_2', 'منتهية')" />
            </div>

            <h1 class="text-lg font-extrabold">{{ $data['name'] }}</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">
                {{ $data['position'] }}{{ $data['department'] ? ' · '.$data['department'] : '' }}
            </p>

            <dl class="mt-4 text-sm grid grid-cols-2 gap-y-2 text-start">
                <dt style="color: var(--text-muted)">{{ setting('volunteer_card.verify.text_1', 'كود العضو') }}</dt>
                <dd class="text-end font-semibold">#{{ $data['code'] }}</dd>

                <dt style="color: var(--text-muted)">{{ setting('volunteer_card.verify.text_2', 'رقم البطاقة') }}</dt>
                <dd class="text-end font-semibold" dir="ltr">{{ $data['card_code'] }}</dd>

                <dt style="color: var(--text-muted)">{{ setting('volunteer_card.verify.text_3', 'تاريخ الإصدار') }}</dt>
                <dd class="text-end font-semibold">{{ $data['issued_at']?->translatedFormat($dateFormat) ?? '—' }}</dd>

                @if (! $valid)
                    <dt style="color: var(--text-muted)">{{ setting('volunteer_card.verify.text_4', 'تاريخ الانتهاء') }}</dt>
                    <dd class="text-end font-semibold">{{ $data['expired_at']?->translatedFormat($dateFormat) ?? '—' }}</dd>
                @endif
            </dl>

            <p class="text-xs mt-4" style="color: var(--text-muted)">
                {{ $valid
                    ? setting('volunteer_card.verify.valid_text', 'البطاقة سارية، وصاحبها متطوّع مُسكَّن عندنا.')
                    : setting('volunteer_card.verify.expired_text', 'البطاقة منتهية — انتهت عضويّة صاحبها، والسجلّ محفوظ.') }}
            </p>

            <a href="{{ $cardUrl }}" class="btn inline-block mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
               style="background: var(--color-brand-500); color:#04201c">{{ setting('volunteer_card.verify.text_5', 'فتح البطاقة') }}</a>
        </div>
    </div>
@endsection
