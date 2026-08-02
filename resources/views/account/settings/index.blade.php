@extends('layouts.app')
@section('title', 'الإعدادات')

@php
    $inputStyle = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
    $inputClass = 'w-full rounded-xl px-3 py-2 text-sm';

    // صفحات الإعدادات تتجمّع في صفحة واحدة بتابات جانبيّة (2.15-ب)
    $groups = [
        'account' => ['label' => 'الحساب', 'icon' => 'user'],
        // ⭐ تاب الأمان (2.3): كلمة السرّ + الجلسات النشطة + منطقة الخطر — كلّها هنا
        'security' => ['label' => 'الأمان', 'icon' => 'lock'],
        'appearance' => ['label' => 'المظهر', 'icon' => 'palette'],
        'sound' => ['label' => 'الصوت والحركة', 'icon' => 'bell'],
        'emergency' => ['label' => 'جهة الطوارئ', 'icon' => '🆘'],
    ];
@endphp

@section('content')
    <x-page-header
        title="الإعدادات"
        subtitle="كلّ تعديل بيتحفظ لوحده — مش محتاج تدوس حفظ."
        :breadcrumbs="[['label' => 'حسابي', 'url' => route('settings.index')], ['label' => 'الإعدادات']]" />

    {{-- بحث داخل الإعدادات (24.5) --}}
    <label class="card p-3 mb-4 flex items-center gap-2">
        <span aria-hidden="true"><x-icon name="search" size="16" /></span>
        <input type="search" data-settings-search placeholder="دوّر على إعداد… مثال: اللغة، الصوت، الأفاتار"
               class="flex-1 bg-transparent text-sm outline-none" style="color: var(--text)"
               aria-label="بحث داخل الإعدادات">
    </label>

    <div class="grid md:grid-cols-[13rem_1fr] gap-4 items-start">

        {{-- تابات جانبيّة — وعلى الموبايل رقائق أفقيّة متمرّرة (2.15-ج) --}}
        <nav class="flex md:flex-col gap-2 overflow-x-auto no-scrollbar md:overflow-visible" aria-label="مجموعات الإعدادات">
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
               style="background: var(--surface-raised); color: var(--text)"><x-icon name="lock" size="16" /> الخصوصيّة</a>
        </nav>

        <div>
            {{-- ---------------------------------------------------- الحساب --}}
            <section class="card p-4" data-settings-panel="account">
                <h2 class="font-bold text-sm mb-1">الحساب</h2>

                @include('account.partials.autosave-field', [
                    'field' => 'name',
                    'label' => 'الاسم',
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
                            <span class="block text-sm mb-1">الصورة الشخصيّة</span>
                            <input type="file" name="avatar" accept="image/*" data-avatar-input class="text-xs">
                            <span class="block text-xs mt-1" style="color: var(--text-muted)">
                                بنقصّها مربّعة تلقائيًّا، وأقصى حجم {{ $avatarMaxKb }} كيلوبايت.
                            </span>
                        </label>

                        <input type="hidden" name="avatar_data" data-avatar-data>
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-xs motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">حفظ الصورة</button>
                    </form>
                </div>

                @include('account.partials.autosave-field', [
                    'field' => 'country_id',
                    'label' => 'الدولة',
                    'keywords' => 'الدولة country',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="">اختر الدولة</option>'
                        .$countries->map(fn ($c) => '<option value="'.$c->id.'"'.($user->country_id === $c->id ? ' selected' : '').'>'.e($c->name_ar).'</option>')->implode('')
                        .'</select>',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'governorate_id',
                    'label' => 'المحافظة',
                    'keywords' => 'المحافظة governorate',
                    // ⚠️ تعليق داخل تعبير PHP — لا وسوم Blade هنا وإلّا انكسر تصريف القالب.
                    // المحافظة حقل عامّ دائمًا ولا يجوز إخفاؤها (12.14-د) — تُملأ ولا تُخفى
                    'hint' => 'المحافظة بتظهر لكلّ الناس على بروفايلك — ودي قاعدة ثابتة في المنصّة.',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="">اختر المحافظة</option>'
                        .$governorates->map(fn ($g) => '<option value="'.$g->id.'"'.($user->governorate_id === $g->id ? ' selected' : '').'>'.e($g->name_ar).'</option>')->implode('')
                        .'</select>',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'locale',
                    'label' => 'اللغة',
                    'keywords' => 'اللغة language locale',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="ar"'.($user->locale === 'ar' ? ' selected' : '').'>العربيّة</option>'
                        .'<option value="en"'.($user->locale === 'en' ? ' selected' : '').'>English</option>'
                        .'</select>',
                ])

                {{-- تنبيه أثر تغيير البريد/الموبايل على موافقات إظهار التواصل (13.4-م) --}}
                <div class="mt-4 rounded-xl p-3 text-xs"
                     style="background: color-mix(in srgb, var(--color-state-warn) 12%, transparent); color: var(--text)">
                    <x-state-badge state="warn" label="خُد بالك" />
                    <p class="mt-2">
                        لو غيّرت البريد أو رقم الموبايل، هيتوقف عرض بياناتك لـ
                        <strong data-consent-count>{{ $activeConsents }}</strong>
                        من اللي وافقت لهم قبل كده — والموافقة القديمة مش بتنتقل للبيانات الجديدة.
                    </p>
                </div>

                @include('account.partials.autosave-field', [
                    'field' => 'email',
                    'label' => 'البريد الإلكترونيّ',
                    'keywords' => 'البريد الإيميل email',
                    'control' => '<input type="email" name="value" value="'.e($user->email).'" class="'.$inputClass.'" style="'.$inputStyle.'">',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'phone',
                    'label' => 'رقم الموبايل',
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
                <h2 class="font-bold text-sm mb-3">كلمة السرّ</h2>

                <form method="post" action="{{ route('settings.password') }}" class="space-y-3"
                      data-settings-item data-keywords="كلمة السرّ الباسوورد password">
                    @csrf
                    <label class="block">
                        <span class="block text-sm mb-1">كلمة السرّ الحاليّة</span>
                        <input type="password" name="current_password" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                        @error('current_password')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="block text-sm mb-1">كلمة السرّ الجديدة</span>
                        <input type="password" name="password" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                        @error('password')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="block text-sm mb-1">تأكيد كلمة السرّ</span>
                        <input type="password" name="password_confirmation" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                    </label>
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="min-height: 44px; background: var(--color-brand-500); color: #04201c">تغيير</button>
                </form>

                <h2 class="font-bold text-sm mt-6 mb-1">الجلسات النشطة</h2>
                <p class="text-xs mb-2" style="color: var(--text-muted)">
                    دي الأجهزة اللي حسابك مفتوح عليها دلوقتي.
                </p>

                @forelse ($devices as $device)
                    <div class="flex items-center justify-between gap-2 py-3" data-settings-item
                         data-keywords="الجلسات الأجهزة sessions devices {{ $device->device_label }}"
                         style="border-top: 1px solid var(--border)">
                        <div class="min-w-0 text-sm">
                            <div class="font-semibold truncate">
                                {{ $device->device_label ?? 'جهاز' }}
                                @if ($device->session_id === $currentSessionId)
                                    <span class="text-xs" style="color: var(--color-state-ok)">● الجهاز الحاليّ</span>
                                @endif
                            </div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                {{ $device->ip }}
                                @if ($device->last_active_at)
                                    · آخر نشاط {{ $device->last_active_at->diffForHumans() }}
                                @endif
                            </div>
                        </div>

                        <form method="post" action="{{ route('settings.devices.destroy', $device) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn rounded-xl px-3 py-2 text-xs motion-standard"
                                    style="min-height: 44px; {{ $inputStyle }}">إنهاء</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm py-2" style="color: var(--text-muted)">مفيش جلسات مسجّلة دلوقتي.</p>
                @endforelse

                {{-- ⭐ الزرّ المنصوص عليه والذي لم يكن موجودًا: إنهاء **كلّ** الجلسات دفعةً واحدة --}}
                <form method="post" action="{{ route('settings.devices.destroy-all') }}" class="mt-3 pt-3"
                      data-settings-item data-keywords="خروج من كلّ الأجهزة logout all devices"
                      style="border-top: 1px solid var(--border)"
                      onsubmit="return confirm('هنقفل كلّ الجلسات على كلّ الأجهزة — وهتحتاج تسجّل دخولك تاني. نكمّل؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="min-height: 44px; background: color-mix(in srgb, var(--color-state-warn) 18%, transparent); color: var(--text)">
                        ▲ {{ setting('auth.logout.all_devices_label', 'تسجيل الخروج من كلّ الأجهزة') }}
                    </button>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        بيقفل حسابك على كلّ الأجهزة — بما فيها الجهاز ده.
                    </p>
                </form>

                <div class="mt-4 pt-3" data-settings-item data-keywords="تحميل بياناتي export"
                     style="border-top: 1px solid var(--border)">
                    <a href="{{ route('settings.export') }}"
                       class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="min-height: 44px; {{ $inputStyle }}">تحميل بياناتي</a>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">ملفّ JSON فيه كلّ اللي المنصّة محتفظة بيه عنك.</p>
                </div>

                {{-- منطقة الخطر: حذف الحساب بتأكيد OTP رباعيّ — **Soft-delete** (2.3) --}}
                <div data-settings-item data-keywords="حذف الحساب منطقة الخطر danger delete">
                    @include('security.danger-zone')
                </div>
            </section>

            {{-- ---------------------------------------------------- المظهر --}}
            <section class="card p-4 mt-4" data-settings-panel="appearance">
                <h2 class="font-bold text-sm mb-1">المظهر</h2>

                @include('account.partials.autosave-field', [
                    'field' => 'theme',
                    'label' => 'الوضع',
                    'keywords' => 'داكن فاتح المظهر theme dark light',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="dark"'.($user->theme === 'dark' ? ' selected' : '').'>داكن</option>'
                        .'<option value="light"'.($user->theme === 'light' ? ' selected' : '').'>فاتح</option>'
                        .'</select>',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'simple_mode',
                    'label' => 'الوضع المبسّط العامّ',
                    'keywords' => 'الوضع المبسّط البساطة simple',
                    'hint' => 'بيخفي «الوضع المتقدّم» من كلّ الصفحات دفعةً واحدة.',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="1"'.($user->simple_mode ? ' selected' : '').'>مفعَّل</option>'
                        .'<option value="0"'.(! $user->simple_mode ? ' selected' : '').'>متوقّف</option>'
                        .'</select>',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'advanced_mode',
                    'label' => 'وضع متقدّم',
                    'keywords' => 'وضع متقدّم advanced',
                    'hint' => 'بيفتح كلّ اللي اتخفى في الصفحات — وعلى الموبايل بيفتح كصفحة كاملة.',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="1"'.($user->advanced_mode ? ' selected' : '').'>مفعَّل</option>'
                        .'<option value="0"'.(! $user->advanced_mode ? ' selected' : '').'>متوقّف</option>'
                        .'</select>',
                ])
            </section>

            {{-- --------------------------------- الصوت والحركة والتنبيهات --}}
            <section class="card p-4 mt-4" data-settings-panel="sound">
                <h2 class="font-bold text-sm mb-1">الصوت والحركة</h2>

                @include('account.partials.autosave-field', [
                    'field' => 'sound_enabled',
                    'label' => 'صوت المنصّة',
                    'keywords' => 'الصوت sound',
                    'hint' => 'بيتحكّم في أصوات الاحتفال ولحظات النجاح.',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="1"'.($user->sound_enabled ? ' selected' : '').'>مفعَّل</option>'
                        .'<option value="0"'.(! $user->sound_enabled ? ' selected' : '').'>متوقّف</option>'
                        .'</select>',
                ])

                @include('account.partials.autosave-field', [
                    'field' => 'motion_enabled',
                    'label' => 'حركة الواجهة',
                    'keywords' => 'الحركة الأنيميشن motion animation',
                    /*
                     | ⚠️ تعليق داخل تعبير PHP — لا وسوم Blade هنا وإلّا انكسر تصريف القالب.
                     | التحكّم في الحركة **من هنا** — من داخل المنصّة — لا من
                     | تفضيل نظام التشغيل، لأنّ `prefers-reduced-motion` مرفوض
                     | نصًّا (2.3 · 2.14-ب) وكان يُخفي الكونفيتي فتضيع الذروة.
                     */
                    'hint' => 'الأنيميشن جزء من إحساس المنصّة وشغّال افتراضيًّا — وتقدر تهدّيه من هنا وقت ما تحبّ.',
                    'control' => '<select name="value" class="'.$inputClass.'" style="'.$inputStyle.'">'
                        .'<option value="1"'.($user->motion_enabled ? ' selected' : '').'>مفعَّلة</option>'
                        .'<option value="0"'.(! $user->motion_enabled ? ' selected' : '').'>هادية</option>'
                        .'</select>',
                ])

                <p class="pt-3 text-xs" style="color: var(--text-muted)">
                    الإشعارات بتوصلك في التاب والجرس دايمًا، وتقدر تظبط تفاصيلها من مركز الإشعارات.
                </p>
            </section>

            {{-- ------------------------------------------- جهة الطوارئ --}}
            <section class="card p-4 mt-4" data-settings-panel="emergency">
                <h2 class="font-bold text-sm mb-1">جهة الطوارئ (اختياريّ)</h2>
                <p class="text-xs mb-3" style="color: var(--text-muted)">
                    بتظهر لمشرفيك وقت الحاجة بس — ومش بتظهر لباقي الناس.
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
                            <button type="submit" class="text-xs underline" style="color: var(--text-muted)">مسح</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm py-2" style="color: var(--text-muted)">مفيش جهة طوارئ مضافة.</p>
                @endforelse

                @if ($emergencyContacts->count() < $emergencyMax)
                    <form method="post" action="{{ route('settings.emergency.store') }}" class="mt-3 grid md:grid-cols-4 gap-2 items-end"
                          data-settings-item data-keywords="إضافة جهة طوارئ">
                        @csrf
                        <label class="block">
                            <span class="block text-xs mb-1">الاسم</span>
                            <input type="text" name="name" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs mb-1">رقم الموبايل</span>
                            <input type="tel" name="phone" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs mb-1">صلة القرابة</span>
                            <input type="text" name="relation" class="{{ $inputClass }}" style="{{ $inputStyle }}">
                        </label>
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">إضافة</button>
                    </form>
                @endif
            </section>

            <p class="text-xs text-center mt-4" data-settings-empty hidden style="color: var(--text-muted)">
                مفيش إعداد بالاسم ده — جرّب كلمة تانية.
            </p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
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
                    if (!res.ok) throw new Error(data.message || 'تعذّر الحفظ');
                    if (flag) {
                        flag.textContent = data.message || 'اتحفظ ✓';
                        flag.style.opacity = '1';
                        flag.style.color = 'var(--color-state-ok)';
                        setTimeout(() => { flag.style.opacity = '0'; }, 2500);
                    }
                } catch (e) {
                    // ماذا حدث + ماذا تفعل — والمُدخَل يفضل زيّ ما هو (2.17-ب)
                    if (flag) {
                        flag.textContent = 'تعذّر الحفظ — جرّب تاني';
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
