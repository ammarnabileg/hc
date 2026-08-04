@php
    use App\Services\Account\ConsentDirectory;
    use App\Services\Volunteer\Profile\ViewerLevel;

    /** تاب «التواصل» (13.4-م-2) — Masking افتراضيّ، والحقل المقفول لا يُعرَض فراغًا. */
    $c = $panel;
    $isOwner = $level === ViewerLevel::OWNER;
@endphp

{{-- طلبات مستنّية ردّ صاحب البروفايل — الفعل من الصفّ نفسه بلا بوب-أب (2.15-ب) --}}
@if ($isOwner && $c['pending']->isNotEmpty())
    <section class="card p-4 mb-4">
        <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.profile_tab_contact.heading', 'طلبات مستنّية ردّك') }}</h2>
        <ul class="space-y-3">
            @foreach ($c['pending'] as $request)
                <li class="flex flex-wrap items-center gap-2 justify-between">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold">
                            {{ $request->requester?->shortName() }} {{ setting('volunteer.profile_tab_contact.bullet', 'طالب إظهار') }} {{ \App\Services\Volunteer\Profile\ConsentFlow::fieldLabel($request->field) }}
                        </div>
                        {{-- سبب الطلب اختياريّ — ووجوده يرفع القبول ويقلّل الرفض العشوائيّ --}}
                        <div class="text-xs" style="color: var(--text-muted)">
                            {{ $request->reason ?: setting('volunteer.profile_tab_contact.text', 'من غير سبب مكتوب') }}
                            · {{ setting('volunteer.profile_tab_contact.bullet_2', 'ينتهي') }} {{ $request->request_expires_at?->diffForHumans() }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('volunteer.profile.consent.approve', $request) }}">
                            @csrf
                            <button type="submit" class="btn rounded-xl px-3 py-2 text-sm font-semibold motion-standard"
                                    style="min-height: 44px; background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.profile_tab_contact.action', 'وافق') }}</button>
                        </form>
                        <form method="POST" action="{{ route('volunteer.profile.consent.deny', $request) }}">
                            @csrf
                            {{-- الرفض صامت: بلا سبب إلزاميّ وبلا إشعار للطرف الآخر --}}
                            <button type="submit" class="btn rounded-xl px-3 py-2 text-sm motion-standard"
                                    style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('volunteer.profile_tab_contact.action_2', 'مش دلوقتي') }}</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif

<div class="grid md:grid-cols-2 gap-3">

    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.profile_tab_contact.heading_2', 'بيانات التواصل') }}</h2>

        <dl class="space-y-4 text-sm">
            @foreach ($c['fields'] as $field)
                <div>
                    <dt class="text-xs mb-1" style="color: var(--text-muted)">{{ $field['label'] }}</dt>

                    @if (! $field['has_value'])
                        <dd style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_contact.text_2', 'مش مضاف') }}</dd>
                    @elseif ($field['visible'])
                        <dd class="flex flex-wrap items-center gap-2">
                            <span class="font-mono" dir="ltr">{{ $field['display'] }}</span>
                            @if ($field['whatsapp'])
                                <a href="{{ $field['whatsapp'] }}" target="_blank" rel="noopener"
                                   class="btn rounded-xl px-3 py-1.5 text-xs motion-standard"
                                   style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('volunteer.common.whatsapp', 'واتساب') }}</a>
                            @endif
                            @if ($field['mailto'])
                                <a href="{{ $field['mailto'] }}"
                                   class="btn rounded-xl px-3 py-1.5 text-xs motion-standard"
                                   style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('volunteer.profile_tab_contact.link', 'إيميل') }}</a>
                            @endif
                            <button type="button" data-copy-contact="{{ $field['display'] }}"
                                    class="btn rounded-xl px-3 py-1.5 text-xs motion-standard"
                                    style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('volunteer.profile_tab_contact.action_3', 'نسخ') }}</button>
                        </dd>
                    @else
                        {{-- الحقل المقفول **لا يُعرَض فراغًا** — يظهر مكانه زرّ الإجراء (13.4-م-2) --}}
                        <dd class="flex flex-wrap items-center gap-2">
                            <span class="font-mono" dir="ltr" style="color: var(--text-muted)">{{ $field['display'] }}</span>

                            @if ($field['waiting'])
                                <span class="text-xs" style="color: var(--text-muted)">{{ $field['neutral'] }}</span>
                            @elseif ($field['can_request'])
                                <button type="button" data-modal-open="consent-{{ $field['key'] }}"
                                        class="btn rounded-xl px-3 py-1.5 text-xs font-semibold motion-standard"
                                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">{{ $field['request_label'] }}</button>
                            @else
                                <span class="text-xs" style="color: var(--text-muted)">{{ $field['neutral'] }}</span>
                            @endif
                        </dd>
                    @endif

                    {{-- خصوصيّة كلّ حقل — يراها صاحبها ويعدّلها من الإعدادات (13.4-م-2) --}}
                    @if ($isOwner)
                        <dd class="mt-1 text-xs" style="color: var(--text-muted)">
                            {{ setting('volunteer.profile_tab_contact.text_3', 'الظهور:') }} {{ $field['privacy_label'] }} ·
                            <a href="{{ route('settings.privacy') }}" style="color: var(--color-brand-500)">{{ setting('volunteer.profile_tab_contact.link_2', 'غيّرها') }}</a>
                        </dd>
                    @endif
                </div>
            @endforeach

            <div class="flex items-center justify-between gap-2">
                <dt style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_contact.text_4', 'الدولة / المحافظة') }}</dt>
                <dd>{{ $c['country'] ?? '—' }}{{ $c['governorate'] ? ' · '.$c['governorate'] : '' }}</dd>
            </div>

            {{-- التوقيت المحليّ الحاليّ — فلا يُكلَّم أحد في وقت غير مناسب (2.5-ج) --}}
            <div class="flex items-center justify-between gap-2">
                <dt style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_contact.text_5', 'التوقيت المحليّ عنده') }}</dt>
                <dd>{{ $c['local_time'] ?? '—' }}</dd>
            </div>
        </dl>

        @if ($isOwner)
            <p class="mt-4 text-xs" style="color: var(--text-muted)">{{ $c['transparency_note'] }}</p>
        @endif
    </section>

    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.profile_tab_contact.heading_3', 'جهة اتّصال للطوارئ') }}</h2>

        @if (! $c['shows_emergency'])
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_contact.text_6', 'متاحة لمشرفيه فقط.') }}</p>
        @elseif ($c['emergency']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_contact.text_7', 'مش مضافة —') }}
                @if ($isOwner)
                    <a href="{{ route('settings.index') }}" style="color: var(--color-brand-500)">{{ setting('volunteer.profile_tab_contact.link_3', 'أضفها من الإعدادات') }}</a>
                @endif
            </p>
        @else
            {{-- **ظاهرة دائمًا للأبلاينز** (13.4-م-2) --}}
            <ul class="space-y-2 text-sm">
                @foreach ($c['emergency'] as $contact)
                    <li class="flex items-center justify-between gap-2">
                        <span>{{ $contact->name }} <span style="color: var(--text-muted)">· {{ $contact->relation }}</span></span>
                        <span class="font-mono" dir="ltr">{{ $contact->phone }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

{{-- بوب-أب طلب الإظهار: سبب اختياريّ — الطلب على البيانات لا على الشخص --}}
@foreach ($c['fields'] as $field)
    @if ($field['can_request'])
        <x-modal :id="'consent-'.$field['key']" :title="$field['request_label']">
            <form method="POST" action="{{ route('volunteer.profile.consent.request', ['code' => $owner->code]) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="field" value="{{ $field['key'] }}">
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_contact.text_8', 'سبب الطلب اختياريّ — بس بيسهّل القرار.') }}</p>
                <input type="text" name="reason" maxlength="{{ (int) setting('volunteer.profile.consent.reason_max', 300) }}"
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                       placeholder="{{ setting('volunteer.profile_tab_contact.placeholder', 'محتاج أنسّق معاه في مهمّة…') }}">
                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.profile_tab_contact.action_4', 'ابعت الطلب') }}</button>
            </form>
        </x-modal>
    @endif
@endforeach

{{-- «مَن يرى بياناتي» — للموافقات الاختياريّة فقط، والأبلاين لا يظهر فيها (13.4-م-2) --}}
@if ($isOwner)
    @php $granted = app(ConsentDirectory::class)->activeFor($owner); @endphp
    <section class="card p-4 mt-3">
        <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.profile_tab_contact.heading_4', 'مين بيشوف بياناتي') }}</h2>
        @if ($granted->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_contact.text_9', 'محدّش دلوقتي — غير مشرفيك بحقّهم النظاميّ.') }}</p>
        @else
            <ul class="space-y-3">
                @foreach ($granted as $consent)
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold">{{ $consent->requester?->shortName() }}</div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                {{ ConsentDirectory::fieldLabel($consent->field) }} ·
                                {{ setting('volunteer.profile_tab_contact.bullet_2', 'ينتهي') }} {{ $consent->consent_expires_at?->diffForHumans() }}
                            </div>
                            <div class="mt-1 h-1.5 w-40 rounded-full" style="background: var(--surface-sunken)">
                                <span class="block h-1.5 rounded-full"
                                      style="width: {{ app(ConsentDirectory::class)->remainingPercent($consent) }}%; background: var(--color-brand-500)"></span>
                            </div>
                        </div>
                        {{-- السحب يقطع الرؤية فورًا — **بلا إشعار للطرف الآخر** --}}
                        <form method="POST" action="{{ route('settings.privacy.revoke', $consent) }}">
                            @csrf
                            <button type="submit" class="btn rounded-xl px-3 py-2 text-sm motion-standard"
                                    style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('volunteer.profile_tab_contact.action_5', 'اسحب الموافقة') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif

@push('scripts')
    @php
        /** نصوص السكربت — تُمرَّر بـ`@json` فلا يبقى حرفٌ عربيّ محروق داخله (2.13-أ) */
        $jsText = [
            'copy_prompt' => (string) setting('volunteer.profile_contact.js_copy_prompt', 'انسخ البيانات'),
            'copied' => (string) setting('volunteer.profile_contact.js_copied', 'اتنسخ ✓'),
        ];
    @endphp

    <script>
        const T = @json($jsText);
        // ردّ فوريّ لكلّ فعل (2.17-ب)
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-copy-contact]');
            if (!btn) return;
            try { await navigator.clipboard.writeText(btn.dataset.copyContact); }
            catch { window.prompt(T.copy_prompt, btn.dataset.copyContact); }
            const original = btn.textContent;
            btn.textContent = T.copied;
            setTimeout(() => { btn.textContent = original; }, 2000);
        });

        // أنيميشن احتفال بسيط خالص وبلا صوت عند الموافقة (13.4-م-2)
        @if (session('consent_celebrate'))
            document.querySelector('[data-volunteer-profile]')?.classList.add('animate-fadeup');
        @endif
    </script>
@endpush
