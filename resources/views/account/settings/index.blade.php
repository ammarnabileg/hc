@extends('layouts.app')
@section('title', setting('account.settings.title', 'الإعدادات'))

@php
    $inputStyle = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
    $inputClass = 'w-full rounded-xl px-3 py-2 text-sm';

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

                {{-- الأفاتار بقصّ ومعاينة (24.5) --}}
                <div class="py-3" data-settings-item data-keywords="الأفاتار الصورة الشخصيّة avatar"
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
                                {{ str_replace(':kb', $avatarMaxKb, (string) setting('account.settings.avatar_hint', 'بنقصّها مربّعة تلقائيًّا، وأقصى حجم :kb كيلوبايت.')) }}
                            </span>
                        </label>

                        <input type="hidden" name="avatar_data" data-avatar-data>
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-xs motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('account.settings.avatar_save', 'حفظ الصورة') }}</button>
                    </form>
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

        // الأفاتار: قصّ مربّع ومعاينة قبل الرفع (24.5)
        const avatarInput = document.querySelector('[data-avatar-input]');
        const canvas = document.querySelector('[data-avatar-canvas]');
        const avatarData = document.querySelector('[data-avatar-data]');
        avatarInput?.addEventListener('change', () => {
            const file = avatarInput.files?.[0];
            if (!file || !canvas) return;
            const img = new Image();
            img.onload = () => {
                const side = Math.min(img.width, img.height);
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, canvas.width, canvas.height);
                canvas.classList.remove('hidden');
                document.querySelector('[data-avatar-current]')?.classList.add('hidden');
                if (avatarData) avatarData.value = canvas.toDataURL('image/png');
            };
            img.src = URL.createObjectURL(file);
        });
    </script>
@endpush
