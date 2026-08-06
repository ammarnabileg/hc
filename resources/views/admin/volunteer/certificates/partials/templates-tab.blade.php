{{--
  تاب [القوالب] (24.2): شبكة كروت — الأنواع الأربعة لا خامس لها، وكلٌّ منها
  يُحرَّر عبر مصمّم القوالب المشترك (12.5-ب) بلا نظام موازٍ لهذه الشاشة.
--}}
<div class="flex items-center justify-between gap-3 flex-wrap mb-3">
    <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.volunteer.certificates.templates_intro', 'أربعة أنواعٍ ثابتة — كلّ نوعٍ له نسختان (عربيّة/إنجليزيّة) بتصميمٍ افتراضيّ جاهز، وتعدّله بحرّيّة.') }}</p>

    @can('certificate_templates.view')
        <form method="get" action="" onsubmit="return false" class="flex items-center gap-2">
            <select id="cert-tpl-jump" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                    onchange="if (this.value) window.location = this.value">
                <option value="">{{ setting('admin.volunteer.certificates.qalb_plus', '+ قالب — افتح مصمّمًا') }}</option>
                @foreach ($cards as $card)
                    @if ($card['type'])
                        <option value="{{ route('admin.certificates.designer', $card['type']) }}">{{ $card['label'] }}</option>
                    @endif
                @endforeach
            </select>
        </form>
    @endcan
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    @foreach ($cards as $card)
        <div class="card p-4">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <div class="font-semibold">{{ $card['label'] }}</div>
                    @if ($card['type'])
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ setting('admin.volunteer.certificates.allghat', 'اللغات:') }}
                            {{ $card['type']->lang_ar_enabled ? setting('admin.volunteer.certificates.lang_ar', 'عربيّة') : '' }}
                            {{ $card['type']->lang_en_enabled ? setting('admin.volunteer.certificates.lang_en', 'إنجليزيّة') : '' }}
                            @if (! $card['type']->lang_ar_enabled && ! $card['type']->lang_en_enabled)
                                {{ setting('admin.volunteer.certificates.mhdsh_mfal', '— محدّش مفعّل') }}
                            @endif
                        </div>
                        @if ($card['templates'])
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ setting('admin.volunteer.certificates.nskha_v1', 'نسخة ع: v') }}{{ $card['templates']['ar']->version }}
                                · {{ setting('admin.volunteer.certificates.nskha_v2', 'نسخة إ: v') }}{{ $card['templates']['en']->version }}
                            </div>
                        @endif
                    @endif
                </div>

                @can('volunteer_certificates.edit')
                    <form method="post" action="{{ route('admin.volunteer.certificates.types.toggle', $card['key']) }}">
                        @csrf
                        <button type="submit" class="text-xs">
                            <x-state-badge :state="$types[$card['key']]['enabled'] ? 'ok' : 'idle'"
                                           :label="$types[$card['key']]['enabled'] ? setting('admin.volunteer.certificates.mfala', 'مفعّلة') : setting('admin.volunteer.certificates.mwqwfa', 'موقوفة')" />
                        </button>
                    </form>
                @else
                    <x-state-badge :state="$types[$card['key']]['enabled'] ? 'ok' : 'idle'"
                                   :label="$types[$card['key']]['enabled'] ? setting('admin.volunteer.certificates.mfala', 'مفعّلة') : setting('admin.volunteer.certificates.mwqwfa', 'موقوفة')" />
                @endcan
            </div>

            @if ($card['type'])
                <div class="flex items-center gap-2 mt-3 flex-wrap text-xs">
                    @can('certificate_templates.view')
                        <a href="{{ route('admin.certificates.designer', ['type' => $card['type'], 'lang' => 'ar']) }}"
                           class="btn rounded-xl px-3 py-1.5 font-semibold"
                           style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.certificates.thryr_ar', 'تحرير (ع)') }}</a>
                        <a href="{{ route('admin.certificates.designer', ['type' => $card['type'], 'lang' => 'en']) }}"
                           class="btn rounded-xl px-3 py-1.5 font-semibold"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.volunteer.certificates.thryr_en', 'تحرير (إ)') }}</a>
                    @endcan

                    @can('certificate_templates.edit')
                        @if ($card['templates'])
                            <form method="post" action="{{ route('admin.certificates.designer.duplicate', $card['templates']['ar']) }}">
                                @csrf
                                <button class="underline">{{ setting('admin.volunteer.certificates.nskh_ar_en', 'نسخ ع ← إ') }}</button>
                            </form>
                            <form method="post" action="{{ route('admin.certificates.designer.duplicate', $card['templates']['en']) }}">
                                @csrf
                                <button class="underline">{{ setting('admin.volunteer.certificates.nskh_en_ar', 'نسخ إ ← ع') }}</button>
                            </form>
                        @endif
                    @endcan
                </div>
            @else
                <p class="text-xs mt-3" style="color: var(--color-state-danger)">{{ setting('admin.volunteer.certificates.nwa_ghyr_mwjwd', 'النوع غير موجود في قاعدة البيانات بعد — شغّل السيدر.') }}</p>
            @endif
        </div>
    @endforeach
</div>
