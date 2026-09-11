@extends('layouts.app')
@section('title', setting('account.settings.title', 'الإعدادات'))

@php
    $inputStyle = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
    $inputClass = 'w-full rounded-xl px-3 py-2 text-sm';

    /*
     | ⭐ 2.7-1 — «قصّ مربّع إجباريّ **بواسطة المستخدم** أوّلًا».
     | مقاس الناتج = أكبر نسخة يشتقّها الخادم (500 افتراضًا) فلا نرفع بكسلًا
     | زائدًا ولا ننقص عمّا سيُعرَض. والمصدر خدمة المعالجة نفسها لا رقمٌ ثانٍ
     | يتقادم بجوارها (2.13).
     */
    $avatarCropPx = app(\App\Services\Images\AvatarProcessor::class)->sizes()[0] ?? 500;
    $avatarZoomMax = 4;

    // صفحات الإعدادات تتجمّع في صفحة واحدة بتابات جانبيّة (2.15-ب)
    $groups = [
        'account' => ['label' => setting('account.settings.tab_account', 'الحساب'), 'icon' => 'user'],
        // ⭐ تاب الأمان (2.3): كلمة السرّ + الجلسات النشطة + منطقة الخطر — كلّها هنا
        'security' => ['label' => setting('account.settings.tab_security', 'الأمان'), 'icon' => 'lock'],
        'appearance' => ['label' => setting('account.settings.tab_appearance', 'المظهر'), 'icon' => 'palette'],
        'sound' => ['label' => setting('account.settings.tab_sound', 'الصوت'), 'icon' => 'bell'],
        'emergency' => ['label' => setting('account.settings.tab_emergency', 'جهة الطوارئ'), 'icon' => '🆘'],
    ];
@endphp

@section('content')
    <x-page-header
        :title="setting('account.settings.title', 'الإعدادات')"
        :subtitle="setting('account.settings.subtitle', 'كلّ تعديل بيتحفظ لوحده — مش محتاج تدوس حفظ.')"
        :breadcrumbs="[['label' => setting('account.settings.breadcrumb_root', 'حسابي'), 'url' => route('settings.index')], ['label' => setting('account.settings.title', 'الإعدادات')]]" />

    {{-- بحث داخل الإعدادات (24.5) --}}
    <label class="card p-3 mb-4 flex items-center gap-2">
        <span aria-hidden="true"><x-icon name="search" size="16" /></span>
        <input type="search" data-settings-search placeholder="{{ setting('account.settings.search_placeholder', 'دوّر على إعداد… مثال: اللغة، الصوت، الأفاتار') }}"
               class="flex-1 bg-transparent text-sm outline-none" style="color: var(--text)"
               aria-label="{{ setting('account.settings.search_aria', 'بحث داخل الإعدادات') }}">
    </label>

    <div class="grid md:grid-cols-[13rem_1fr] gap-4 items-start">

        {{-- تابات جانبيّة — وعلى الموبايل رقائق أفقيّة متمرّرة (2.15-ج) --}}
        <nav class="flex md:flex-col gap-2 min-w-0 overflow-x-auto no-scrollbar md:overflow-visible" aria-label="{{ setting('account.settings.tabs_nav_aria', 'مجموعات الإعدادات') }}">
            @foreach ($groups as $key => $group)
                <button type="button" data-settings-tab="{{ $key }}"
                        class="shrink-0 text-start rounded-xl px-4 py-2 text-sm motion-standard"
                        style="{{ $tab === $key ? 'background: var(--color-brand-500); color:#04201c; font-weight:700' : 'background: var(--surface-raised); color: var(--text)' }}">
                    <x-icon :name="$group['icon']" size="16" /> {{ $group['label'] }}
                </button>
            @endforeach

            {{-- الخصوصيّة (مَن يرى كلّ حقل) صفحتها الخاصّة — أمّا الأمان فتابٌ هنا (2.3) --}}
            <a href="{{ route('settings.privacy') }}"
               class="shrink-0 rounded-xl px-4 py-2 text-sm motion-standard"
               style="background: var(--surface-raised); color: var(--text)"><x-icon name="lock" size="16" /> {{ setting('account.settings.tab_privacy', 'الخصوصيّة') }}</a>
        </nav>

        <div>
            {{-- ---------------------------------------------------- الحساب --}}
            <section class="card p-4" data-settings-panel="account">
                <h2 class="font-bold text-sm mb-1">{{ setting('account.settings.tab_account', 'الحساب') }}</h2>

                @include('account.partials.autosave-field', [
                    'field' => 'name',
                    'label' => setting('account.settings.field_name', 'الاسم'),
                    'keywords' => 'الاسم اسمي name',
                    'control' => '<input type="text" name="value" value="'.e($user->name).'" class="'.$inputClass.'" style="'.$inputStyle.'">',
                ])

                {{-- الأفاتار بقصّ ومعاينة (24.5) — والقصّ **بيد المستخدم** (2.7-1) --}}
                <div class="py-3" data-settings-item data-keywords="الأفاتار الصورة الشخصيّة قصّ قص avatar crop"
                     style="border-bottom: 1px solid var(--border)">
                    <form method="post" action="{{ route('settings.avatar') }}" enctype="multipart/form-data"
                          class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="text-center">
                            <span data-avatar-current class="inline-block"><x-avatar :user="$user" size="16" /></span>
                            <canvas data-avatar-canvas width="256" height="256" class="hidden rounded-full mx-auto"
                                    style="width:4rem;height:4rem"></canvas>
                        </div>

                        <label class="block flex-1 min-w-48">
                            <span class="block text-sm mb-1">{{ setting('account.settings.avatar_label', 'الصورة الشخصيّة') }}</span>
                            <input type="file" name="avatar" accept="image/*" data-avatar-input class="text-xs">
                            <span class="block text-xs mt-1" style="color: var(--text-muted)">
                                {{ str_replace(':kb', $avatarMaxKb, (string) setting('account.settings.avatar_hint', 'هتقصّها مربّعة بنفسك قبل الحفظ، وأقصى حجم :kb كيلوبايت.')) }}
                            </span>
                        </label>

                        <input type="hidden" name="avatar_data" data-avatar-data>
                        <button type="submit" data-avatar-save class="btn rounded-xl px-4 py-2 text-xs motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('account.settings.avatar_save', 'حفظ الصورة') }}</button>
                    </form>

                    {{--
                      ✂️ محرّر القصّ المربّع (2.7-1): «قص مربّع إجباري **بواسطة
                      المستخدم** أولًا». القصّ التلقائيّ من المنتصف كان يختار
                      عن صاحب الصورة فيقصّ رأسه أحيانًا؛ هنا هو يسحب ويكبّر
                      ويؤكّد، والمربّع الناتج يدخل **نفس** مسار الرفع القديم
                      (`avatar_data`) فيكمّل الخادم اشتقاق النسخ الثلاث
                      (500 · 150 · 50) بلا تغيير.
                    --}}
                    <div data-avatar-cropper hidden class="mt-3 rounded-2xl p-3"
                         style="background: var(--surface-sunken); border: 1px solid var(--border)">
                        <h3 class="text-sm font-bold mb-1">{{ setting('account.settings.avatar_crop_title', 'اقصّ صورتك') }}</h3>
                        <p class="text-xs mb-3" style="color: var(--text-muted)">
                            {{ setting('account.settings.avatar_crop_hint', 'حرّك الصورة جوّه المربّع واظبط التكبير — اللي بيّن جوّه بالظبط هو اللي هيتحفظ.') }}
                        </p>

                        <div data-avatar-stage tabindex="0" role="application"
                             aria-label="{{ setting('account.settings.avatar_crop_stage_aria', 'منطقة القصّ — اسحب بالماوس أو بإصبعك، والأسهم تحرّك، و+ و− يكبّرا ويصغّرا.') }}"
                             class="relative mx-auto select-none motion-standard"
                             style="width: 16rem; max-width: 100%; aspect-ratio: 1 / 1; overflow: hidden; border-radius: 1rem; cursor: grab; touch-action: none; background: var(--surface-raised); border: 1px solid var(--border)">
                            <canvas data-avatar-crop-canvas class="block" style="width: 100%; height: 100%"></canvas>

                            {{-- تعتيم ما خارج الدائرة: المربّع هو المحفوظ، والدائرة شكله كأفاتار --}}
                            <span aria-hidden="true" class="pointer-events-none absolute inset-0"
                                  style="box-shadow: inset 0 0 0 9999px color-mix(in srgb, var(--surface-sunken) 55%, transparent); -webkit-mask: radial-gradient(circle at center, transparent 49.5%, #000 50%); mask: radial-gradient(circle at center, transparent 49.5%, #000 50%)"></span>
                            <span aria-hidden="true" class="pointer-events-none absolute inset-0 rounded-full"
                                  style="border: 2px dashed color-mix(in srgb, var(--color-brand-500) 70%, transparent)"></span>
                        </div>

                        <label class="block mt-3">
                            <span class="block text-xs mb-1">{{ setting('account.settings.avatar_crop_zoom', 'التكبير') }}</span>
                            {{-- بلا `accent-color` (2.10.1-11) — المنزلق المخصّص في `app.css` --}}
                            <input type="range" data-avatar-zoom min="1" max="{{ $avatarZoomMax }}" step="0.01" value="1"
                                   class="w-full" style="border: none; outline: none; min-height: 44px">
                        </label>

                        <div class="flex flex-wrap gap-2 mt-2">
                            <button type="button" data-avatar-confirm class="btn rounded-xl px-4 py-2 text-xs font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('account.settings.avatar_crop_confirm', 'تأكيد القصّ') }}</button>
                            <button type="button" data-avatar-cancel class="rounded-xl px-4 py-2 text-xs motion-standard"
                                    style="background: var(--surface-raised); color: var(--text); border: 1px solid var(--border)">{{ setting('account.settings.avatar_crop_cancel', 'إلغاء') }}</button>
                        </div>
                    </div>

                    <p class="text-xs mt-2" data-avatar-crop-note hidden></p>
                </div>

                @include('account.partials.autosave-field', [
                    'field' => 'country_id',
                    'label' => setting('account.settings.field_country', 'الدولة'),
                    'keywords' => 'الدولة country',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="">'.e(setting('account.settings.country_placeholder', 'اختر الدولة')).'</option>'
                        .$countries->map(fn ($c) => '<option value="'.$c->id.'"'.($user->country_id === $c->id ? ' selected' : '').'>'.e($c->name_ar).'</option>')->implode('')
                        .'</select>',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'governorate_id',
                    'label' => setting('account.settings.field_governorate', 'المحافظة'),
                    'keywords' => 'المحافظة governorate',
                    // ⚠️ تعليق داخل تعبير PHP — لا وسوم Blade هنا وإلّا انكسر تصريف القالب.
                    // المحافظة حقل عامّ دائمًا ولا يجوز إخفاؤها (12.14-د) — تُملأ ولا تُخفى
                    'hint' => setting('account.settings.governorate_hint', 'المحافظة بتظهر لكلّ الناس على بروفايلك — ودي قاعدة ثابتة في المنصّة.'),
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="">'.e(setting('account.settings.governorate_placeholder', 'اختر المحافظة')).'</option>'
                        .$governorates->map(fn ($g) => '<option value="'.$g->id.'"'.($user->governorate_id === $g->id ? ' selected' : '').'>'.e($g->name_ar).'</option>')->implode('')
                        .'</select>',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'locale',
                    'label' => setting('account.settings.field_language', 'اللغة'),
                    'keywords' => 'اللغة language locale',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="ar"'.($user->locale === 'ar' ? ' selected' : '').'>'.e(setting('account.settings.language_ar', 'العربيّة')).'</option>'
                        .'<option value="en"'.($user->locale === 'en' ? ' selected' : '').'>English</option>'
                        .'</select>',
                ])

                {{-- تنبيه أثر تغيير البريد/الموبايل على موافقات إظهار التواصل (13.4-م) --}}
                <div class="mt-4 rounded-xl p-3 text-xs"
                     style="background: color-mix(in srgb, var(--color-state-warn) 12%, transparent); color: var(--text)">
                    <x-state-badge state="warn" :label="setting('account.settings.contact_warn_badge', 'خُد بالك')" />
                    <p class="mt-2">
                        {!! str_replace(
                            ':count',
                            '<strong data-consent-count>'.(int) $activeConsents.'</strong>',
                            e(setting('account.settings.contact_warn_message', 'لو غيّرت البريد أو رقم الموبايل، هيتوقف عرض بياناتك لـ :count من اللي وافقت لهم قبل كده — والموافقة القديمة مش بتنتقل للبيانات الجديدة.')),
                        ) !!}
                    </p>
                </div>

                @include('account.partials.autosave-field', [
                    'field' => 'email',
                    'label' => setting('account.settings.field_email', 'البريد الإلكترونيّ'),
                    'keywords' => 'البريد الإيميل email',
                    'control' => '<input type="email" name="value" value="'.e($user->email).'" class="'.$inputClass.'" style="'.$inputStyle.'">',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'phone',
                    'label' => setting('account.settings.field_phone', 'رقم الموبايل'),
                    'keywords' => 'الموبايل الهاتف phone',
                    'control' => '<input type="tel" name="value" value="'.e((string) $user->phone).'" class="'.$inputClass.'" style="'.$inputStyle.'">',
                ])
            </section>

            {{--
              ------------------------------------------------------ الأمان (2.3)
              تاب واحد يجمع ما كان مبعثرًا: كلمة السرّ · **الجلسات النشطة** ·
              **منطقة الخطر**. والبند يذكرهما نصًّا داخل تاب الأمان لا في صفحة
              منفصلة — فمَن يبحث عن أمان حسابه يجده في مكان واحد.
            --}}
            <section class="card p-4 mt-4" data-settings-panel="security">
                @include('account.settings.partials.security')
            </section>

            {{-- ---------------------------------------------------- المظهر --}}
            <section class="card p-4 mt-4" data-settings-panel="appearance">
                <h2 class="font-bold text-sm mb-1">{{ setting('account.settings.tab_appearance', 'المظهر') }}</h2>

                @include('account.partials.autosave-field', [
                    'field' => 'theme',
                    'label' => setting('account.settings.field_theme', 'الوضع'),
                    'keywords' => 'داكن فاتح المظهر theme dark light',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="dark"'.($user->theme === 'dark' ? ' selected' : '').'>'.e(setting('account.settings.theme_dark', 'داكن')).'</option>'
                        .'<option value="light"'.($user->theme === 'light' ? ' selected' : '').'>'.e(setting('account.settings.theme_light', 'فاتح')).'</option>'
                        .'</select>',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'simple_mode',
                    'label' => setting('account.settings.field_simple_mode', 'الوضع المبسّط العامّ'),
                    'keywords' => 'الوضع المبسّط البساطة simple',
                    'hint' => setting('account.settings.simple_mode_hint', 'بيخفي «الوضع المتقدّم» من كلّ الصفحات دفعةً واحدة.'),
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="1"'.($user->simple_mode ? ' selected' : '').'>'.e(setting('account.settings.toggle_on', 'مفعَّل')).'</option>'
                        .'<option value="0"'.(! $user->simple_mode ? ' selected' : '').'>'.e(setting('account.settings.toggle_off', 'متوقّف')).'</option>'
                        .'</select>',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'advanced_mode',
                    'label' => setting('account.settings.field_advanced_mode', 'وضع متقدّم'),
                    'keywords' => 'وضع متقدّم advanced',
                    'hint' => setting('account.settings.advanced_mode_hint', 'بيفتح كلّ اللي اتخفى في الصفحات — وعلى الموبايل بيفتح كصفحة كاملة.'),
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="1"'.($user->advanced_mode ? ' selected' : '').'>'.e(setting('account.settings.toggle_on', 'مفعَّل')).'</option>'
                        .'<option value="0"'.(! $user->advanced_mode ? ' selected' : '').'>'.e(setting('account.settings.toggle_off', 'متوقّف')).'</option>'
                        .'</select>',
                ])
            </section>

            {{-- ------------------------------------------- الصوت والتنبيهات --}}
            {{-- ⛔ **توجّل الصوت وحده** هنا: «**Toggle للصوت فقط** في **صفحة إعدادات
                 البروفايل** … **⛔ ولا يوجد Toggle للأنيميشن — الأنيميشن حاضر دائمًا
                 لأنّه روح المنصّة**» (2.3) — فلا صفَّ حركةٍ في هذه الشاشة. --}}
            <section class="card p-4 mt-4" data-settings-panel="sound">
                <h2 class="font-bold text-sm mb-1">{{ setting('account.settings.tab_sound', 'الصوت') }}</h2>

                @include('account.partials.autosave-field', [
                    'field' => 'sound_enabled',
                    'label' => setting('account.settings.field_sound', 'صوت المنصّة'),
                    'keywords' => 'الصوت sound',
                    'hint' => setting('account.settings.sound_hint', 'بيتحكّم في أصوات الاحتفال ولحظات النجاح.'),
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="1"'.($user->sound_enabled ? ' selected' : '').'>'.e(setting('account.settings.toggle_on', 'مفعَّل')).'</option>'
                        .'<option value="0"'.(! $user->sound_enabled ? ' selected' : '').'>'.e(setting('account.settings.toggle_off', 'متوقّف')).'</option>'
                        .'</select>',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'email_channel',
                    'label' => setting('account.settings.field_email_channel', 'رسايل البريد'),
                    'keywords' => 'البريد الإيميل email mail قنوات',
                    /*
                     | ⚠️ تعليق داخل تعبير PHP — لا وسوم Blade هنا وإلّا انكسر تصريف القالب.
                     | قناة البريد (12.6-أ) يوقفها صاحبها من هنا، والخادم يحترم
                     | إيقافه قبل أيّ إرسال. وما يوقفه هذا هو **المنشورات**
                     | وحدها — لا رموز الدخول ولا استعادة كلمة السرّ، وإلّا
                     | حبس المستخدمُ نفسَه خارج حسابه بضغطة تفضيل.
                     */
                    'hint' => setting('account.settings.email_channel_hint', 'ده بيوقف رسايل المنشورات على بريدك بس — رموز الدخول واستعادة كلمة السرّ هتفضل توصلك دايمًا.'),
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="1"'.($user->email_optout_at === null ? ' selected' : '').'>'.e(setting('account.settings.email_channel_on', 'توصلني')).'</option>'
                        .'<option value="0"'.($user->email_optout_at !== null ? ' selected' : '').'>'.e(setting('account.settings.email_channel_off', 'متوصلنيش')).'</option>'
                        .'</select>',
                ])

                <p class="pt-3 text-xs" style="color: var(--text-muted)">
                    {{ setting('account.settings.notifications_note', 'الإشعارات بتوصلك في التاب والجرس دايمًا، وتقدر تظبط تفاصيلها من مركز الإشعارات.') }}
                </p>
            </section>

            {{-- ------------------------------------------- جهة الطوارئ --}}
            <section class="card p-4 mt-4" data-settings-panel="emergency">
                <h2 class="font-bold text-sm mb-1">{{ setting('account.settings.emergency_title', 'جهة الطوارئ (اختياريّ)') }}</h2>
                <p class="text-xs mb-3" style="color: var(--text-muted)">
                    {{ setting('account.settings.emergency_hint', 'بتظهر لمشرفيك وقت الحاجة بس — ومش بتظهر لباقي الناس.') }}
                </p>

                @forelse ($emergencyContacts as $contact)
                    <div class="flex items-center justify-between gap-2 py-2" data-settings-item
                         data-keywords="جهة الطوارئ {{ $contact->name }}" style="border-bottom: 1px solid var(--border)">
                        <div class="text-sm">
                            <div class="font-semibold">{{ $contact->name }}</div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                {{ $contact->phone }}@if ($contact->relation) · {{ $contact->relation }}@endif
                            </div>
                        </div>
                        <form method="post" action="{{ route('settings.emergency.destroy', $contact) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs underline" style="color: var(--text-muted)">{{ setting('account.settings.emergency_delete', 'مسح') }}</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm py-2" style="color: var(--text-muted)">{{ setting('account.settings.emergency_empty', 'مفيش جهة طوارئ مضافة.') }}</p>
                @endforelse

                @if ($emergencyContacts->count() < $emergencyMax)
                    <form method="post" action="{{ route('settings.emergency.store') }}" class="mt-3 grid md:grid-cols-4 gap-2 items-end"
                          data-settings-item data-keywords="إضافة جهة طوارئ">
                        @csrf
                        <label class="block">
                            <span class="block text-xs mb-1">{{ setting('account.settings.emergency_name', 'الاسم') }}</span>
                            <input type="text" name="name" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs mb-1">{{ setting('account.settings.emergency_phone', 'رقم الموبايل') }}</span>
                            <input type="tel" name="phone" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs mb-1">{{ setting('account.settings.emergency_relation', 'صلة القرابة') }}</span>
                            <input type="text" name="relation" class="{{ $inputClass }}" style="{{ $inputStyle }}">
                        </label>
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('account.settings.emergency_add', 'إضافة') }}</button>
                    </form>
                @endif
            </section>

            <p class="text-xs text-center mt-4" data-settings-empty hidden style="color: var(--text-muted)">
                {{ setting('account.settings.search_empty', 'مفيش إعداد بالاسم ده — جرّب كلمة تانية.') }}
            </p>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        // ردود الحفظ التلقائيّ من الإعدادات لا من السكربت (2.13)
        $autosaveWords = [
            'failed' => (string) setting('account.settings.autosave_failed', 'تعذّر الحفظ'),
            'saved' => (string) setting('account.settings.saved_flag', 'اتحفظ ✓'),
            'retry' => (string) setting('account.settings.autosave_retry', 'تعذّر الحفظ — جرّب تاني'),
        ];

        // ونصوص محرّر القصّ كذلك — لا نصّ عربيّ داخل السكربت (2.13)
        $cropWords = [
            'required' => (string) setting('account.settings.avatar_crop_required', 'اقصّ الصورة الأوّل عشان تقدر تحفظها.'),
            'done' => (string) setting('account.settings.avatar_crop_done', 'تمّ القصّ ✓ — تقدر تحفظ دلوقتي.'),
        ];
    @endphp
    <script>
        const autosaveWords = @json($autosaveWords);
        /* ------------------------------------------------------------------
         | إعدادات الحساب: تابات جانبيّة + بحث داخليّ + حفظ تلقائيّ (24.5 · 2.17-ب)
         ------------------------------------------------------------------ */
        const panels = document.querySelectorAll('[data-settings-panel]');
        const tabs = document.querySelectorAll('[data-settings-tab]');

        const showTab = (key) => {
            panels.forEach((p) => { p.hidden = p.dataset.settingsPanel !== key; });
            tabs.forEach((t) => {
                const on = t.dataset.settingsTab === key;
                t.style.background = on ? 'var(--color-brand-500)' : 'var(--surface-raised)';
                t.style.color = on ? '#04201c' : 'var(--text)';
                t.style.fontWeight = on ? '700' : '400';
            });
            // الصفحة تفتح على آخر تاب فُتِح فيها (2.15-د)
            try { localStorage.setItem('settings.tab', key); } catch {}
        };

        tabs.forEach((t) => t.addEventListener('click', () => showTab(t.dataset.settingsTab)));

        let initial = 'account';
        try { initial = localStorage.getItem('settings.tab') || 'account'; } catch {}
        showTab(initial);

        // بحث داخل الإعدادات: يفتح كلّ المجموعات ويُبقي المطابق فقط
        const search = document.querySelector('[data-settings-search]');
        const emptyNote = document.querySelector('[data-settings-empty]');
        search?.addEventListener('input', () => {
            const term = search.value.trim().toLowerCase();

            if (!term) {
                showTab(initial);
                document.querySelectorAll('[data-settings-item]').forEach((i) => { i.hidden = false; });
                if (emptyNote) emptyNote.hidden = true;
                return;
            }

            panels.forEach((p) => { p.hidden = false; });
            let hits = 0;
            document.querySelectorAll('[data-settings-item]').forEach((item) => {
                const match = (item.dataset.keywords || '').toLowerCase().includes(term);
                item.hidden = !match;
                if (match) hits += 1;
            });
            panels.forEach((p) => {
                p.hidden = p.querySelectorAll('[data-settings-item]:not([hidden])').length === 0;
            });
            if (emptyNote) emptyNote.hidden = hits > 0;
        });

        // حفظ تلقائيّ: التغيير يحفظ فورًا و«اتحفظ ✓» تظهر بجوار الحقل (2.17-ب)
        document.querySelectorAll('[data-autosave-form]').forEach((form) => {
            const submit = form.querySelector('[data-autosave-submit]');
            const flag = form.querySelector('[data-saved-flag]');
            if (submit) submit.hidden = true;

            const save = async () => {
                const body = new FormData(form);
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        body,
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || autosaveWords.failed);
                    if (flag) {
                        flag.textContent = data.message || autosaveWords.saved;
                        flag.style.opacity = '1';
                        flag.style.color = 'var(--color-state-ok)';
                        setTimeout(() => { flag.style.opacity = '0'; }, 2500);
                    }
                } catch (e) {
                    // ماذا حدث + ماذا تفعل — والمُدخَل يفضل زيّ ما هو (2.17-ب)
                    if (flag) {
                        flag.textContent = autosaveWords.retry;
                        flag.style.color = 'var(--color-state-danger)';
                        flag.style.opacity = '1';
                    }
                    if (submit) submit.hidden = false;
                }
            };

            form.querySelectorAll('input, select').forEach((el) => {
                if (el.type === 'hidden') return;
                el.addEventListener('change', save);
            });
        });

        /* ------------------------------------------------------------------
         | الأفاتار: **قصّ مربّع بيد المستخدم** قبل الرفع (2.7-1 · 24.5)
         |
         | البند يقول «قص مربّع إجباري **بواسطة المستخدم** أولًا» — فالقصّ
         | التلقائيّ من المنتصف كان يختار عن صاحب الصورة. هنا: المستخدم
         | يسحب الصورة داخل مربّعٍ ثابت ويكبّر (عجلة · شريط · إصبعين ·
         | لوحة مفاتيح) ثمّ **يؤكّد**، وناتج المربّع يدخل نفس الحقل القديم
         | `avatar_data` فلا يتغيّر شيء في الخادم: هو يستقبل مربّعًا كما كان،
         | لكنّه الآن المربّع الذي **اختاره المستخدم**، ويكمّل اشتقاق النسخ
         | الثلاث (500 · 150 · 50) بلا مساس.
         ------------------------------------------------------------------ */
        const cropWords = @json($cropWords);
        const cropPx = @json($avatarCropPx);
        const zoomMax = @json($avatarZoomMax);

        const avatarInput = document.querySelector('[data-avatar-input]');
        const preview = document.querySelector('[data-avatar-canvas]');
        const avatarData = document.querySelector('[data-avatar-data]');
        const cropper = document.querySelector('[data-avatar-cropper]');
        const stage = document.querySelector('[data-avatar-stage]');
        const stageCanvas = document.querySelector('[data-avatar-crop-canvas]');
        const zoomBar = document.querySelector('[data-avatar-zoom]');
        const avatarSave = document.querySelector('[data-avatar-save]');
        const cropNote = document.querySelector('[data-avatar-crop-note]');

        if (avatarInput && stage && stageCanvas) {
            // مخزن الكانفس = مقاس الناتج نفسه، فالمربّع المعروض هو المحفوظ حرفيًّا
            stageCanvas.width = cropPx;
            stageCanvas.height = cropPx;
            const ctx = stageCanvas.getContext('2d');

            let img = null;      // الصورة المختارة
            let baseScale = 1;   // أصغر تكبير يغطّي المربّع (cover)
            let level = 1;       // تكبير المستخدم فوق الأساس
            let offX = 0;
            let offY = 0;

            const drawnSize = () => {
                const s = baseScale * level;
                return { w: img.width * s, h: img.height * s };
            };

            // الصورة تغطّي المربّع دائمًا — فلا فراغ أبيض في زاويةٍ من الأفاتار
            const draw = () => {
                if (!img) return;
                const { w, h } = drawnSize();
                offX = Math.min(0, Math.max(cropPx - w, offX));
                offY = Math.min(0, Math.max(cropPx - h, offY));
                ctx.clearRect(0, 0, cropPx, cropPx);
                ctx.drawImage(img, offX, offY, w, h);
            };

            // التكبير حول نقطةٍ ثابتة (مركز المربّع أو موضع المؤشّر) فلا تقفز الصورة
            const setZoom = (next, ax = cropPx / 2, ay = cropPx / 2) => {
                if (!img) return;
                const prev = level;
                level = Math.min(zoomMax, Math.max(1, Number.isFinite(next) ? next : prev));
                const k = level / prev;
                offX = ax - (ax - offX) * k;
                offY = ay - (ay - offY) * k;
                if (zoomBar) {
                    zoomBar.value = String(level);
                    // تلوين المنزلق في `app.js` يسمع الأحداث لا الإسناد البرمجيّ —
                    // فالتكبير بالعجلة أو بالإصبعين يترك الشريط متخلّفًا بلا هذه.
                    zoomBar.dispatchEvent(new Event('change'));
                }
                draw();
            };

            // حالة «لسه ما قصّش»: الحفظ موقوف حتى يؤكّد — القصّ إجباريّ (2.7-1)
            const setPending = (on) => {
                if (avatarSave) {
                    avatarSave.disabled = on;
                    avatarSave.style.opacity = on ? '0.5' : '1';
                    avatarSave.style.cursor = on ? 'not-allowed' : '';
                }
                if (cropNote) {
                    cropNote.hidden = false;
                    cropNote.textContent = on ? cropWords.required : cropWords.done;
                    cropNote.style.color = on ? 'var(--color-state-warn)' : 'var(--color-state-ok)';
                }
                if (on && avatarData) avatarData.value = '';
            };

            const toCanvasPx = (px) => {
                const rect = stage.getBoundingClientRect();
                return rect.width ? px * (cropPx / rect.width) : px;
            };

            // ----------------------------------------------------- السحب واللمس
            const pointers = new Map();
            let lastDist = 0;

            stage.addEventListener('pointerdown', (e) => {
                if (!img) return;
                stage.setPointerCapture(e.pointerId);
                pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
                lastDist = 0;
                stage.style.cursor = 'grabbing';
            });

            stage.addEventListener('pointermove', (e) => {
                if (!img || !pointers.has(e.pointerId)) return;
                const prev = pointers.get(e.pointerId);
                pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

                const pts = [...pointers.values()];
                if (pts.length >= 2) {
                    // إصبعان = تكبير بالقرص
                    const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
                    if (lastDist > 0) setZoom(level * (dist / lastDist));
                    lastDist = dist;
                    return;
                }

                offX += toCanvasPx(e.clientX - prev.x);
                offY += toCanvasPx(e.clientY - prev.y);
                draw();
            });

            const release = (e) => {
                pointers.delete(e.pointerId);
                lastDist = 0;
                stage.style.cursor = 'grab';
            };
            stage.addEventListener('pointerup', release);
            stage.addEventListener('pointercancel', release);

            // ----------------------------------------------------- عجلة الماوس
            stage.addEventListener('wheel', (e) => {
                if (!img) return;
                e.preventDefault();
                const rect = stage.getBoundingClientRect();
                const f = rect.width ? cropPx / rect.width : 1;
                setZoom(level * (e.deltaY < 0 ? 1.1 : 1 / 1.1), (e.clientX - rect.left) * f, (e.clientY - rect.top) * f);
            }, { passive: false });

            // ------------------------------------- لوحة المفاتيح (وصوليّة 2.12)
            stage.addEventListener('keydown', (e) => {
                if (!img) return;
                const step = cropPx / 20;
                const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
                if (moves[e.key]) {
                    offX += moves[e.key][0];
                    offY += moves[e.key][1];
                    draw();
                    e.preventDefault();
                } else if (e.key === '+' || e.key === '=') {
                    setZoom(level + 0.1);
                    e.preventDefault();
                } else if (e.key === '-' || e.key === '_') {
                    setZoom(level - 0.1);
                    e.preventDefault();
                }
            });

            zoomBar?.addEventListener('input', () => setZoom(parseFloat(zoomBar.value)));

            // ----------------------------------------------- فتح المحرّر بالاختيار
            avatarInput.addEventListener('change', () => {
                const file = avatarInput.files?.[0];
                if (!file) return;

                const url = URL.createObjectURL(file);
                const next = new Image();
                next.onload = () => {
                    img = next;
                    baseScale = Math.max(cropPx / img.width, cropPx / img.height);
                    level = 1;
                    if (zoomBar) zoomBar.value = '1';
                    const { w, h } = drawnSize();
                    offX = (cropPx - w) / 2;
                    offY = (cropPx - h) / 2;
                    if (cropper) cropper.hidden = false;
                    setPending(true);
                    draw();
                    stage.focus({ preventScroll: true });
                    URL.revokeObjectURL(url);
                };
                next.src = url;
            });

            // ----------------------------------------------------- تأكيد وإلغاء
            document.querySelector('[data-avatar-confirm]')?.addEventListener('click', () => {
                if (!img) return;
                draw();
                if (avatarData) avatarData.value = stageCanvas.toDataURL('image/png');
                if (preview) {
                    const pctx = preview.getContext('2d');
                    pctx.clearRect(0, 0, preview.width, preview.height);
                    pctx.drawImage(stageCanvas, 0, 0, preview.width, preview.height);
                    preview.classList.remove('hidden');
                    document.querySelector('[data-avatar-current]')?.classList.add('hidden');
                }
                if (cropper) cropper.hidden = true;
                setPending(false);
            });

            document.querySelector('[data-avatar-cancel]')?.addEventListener('click', () => {
                img = null;
                avatarInput.value = '';
                if (avatarData) avatarData.value = '';
                if (cropper) cropper.hidden = true;
                preview?.classList.add('hidden');
                document.querySelector('[data-avatar-current]')?.classList.remove('hidden');
                if (avatarSave) {
                    avatarSave.disabled = false;
                    avatarSave.style.opacity = '1';
                    avatarSave.style.cursor = '';
                }
                if (cropNote) cropNote.hidden = true;
            });
        }
    </script>
@endpush
