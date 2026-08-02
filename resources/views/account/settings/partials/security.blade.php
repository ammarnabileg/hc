{{--
    ⭐ **بلوك الأمان — مصدر واحد يُضمَّن في مكانين** (2.3 · 24.5).

    البند 2.3 يقول: **تاب «الأمان»** داخل الإعدادات يجمع كلمة السرّ **والجلسات
    النشطة** **ومنطقة الخطر**. والبند 24.5 يصف شاشة «الخصوصيّة والأمان» ويذكر
    فيها **الأمان** نصًّا: تغيير كلمة المرور · الجلسات النشطة · [تحميل بياناتي].

    فالبندان لا يتعارضان: **المحتوى واحد يُعرَض من مدخلين**. ولذلك هو هنا في
    ملفّ واحد يُضمَّن في التاب وفي الصفحة — فلو تغيّر نصّ أو زرّ تغيّر في
    المكانين معًا، ولا تنشأ نسختان تفترقان مع الوقت.

    والصلاحيّة فوق كلّ قسم: **ما لا يملكه المستخدم يُخفى ولا يُعطَّل** (2.15-أ-7).
--}}
@php
    $secInputStyle = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
    $secInputClass = 'w-full rounded-xl px-3 py-2 text-sm';
@endphp

@can('user_profile.edit')
    <h2 class="font-bold text-sm mb-3">كلمة السرّ</h2>

    <form method="post" action="{{ route('settings.password') }}" class="space-y-3"
          data-settings-item data-keywords="كلمة السرّ الباسوورد password">
        @csrf
        <label class="block">
            <span class="block text-sm mb-1">كلمة السرّ الحاليّة</span>
            <input type="password" name="current_password" required class="{{ $secInputClass }}" style="{{ $secInputStyle }}">
            @error('current_password')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
        </label>
        <label class="block">
            <span class="block text-sm mb-1">كلمة السرّ الجديدة</span>
            <input type="password" name="password" required class="{{ $secInputClass }}" style="{{ $secInputStyle }}">
            @error('password')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
        </label>
        <label class="block">
            <span class="block text-sm mb-1">تأكيد كلمة السرّ</span>
            <input type="password" name="password_confirmation" required class="{{ $secInputClass }}" style="{{ $secInputStyle }}">
        </label>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="min-height: 44px; background: var(--color-brand-500); color: #04201c">تغيير</button>
    </form>
@endcan

{{-- الجلسات النشطة: الجهاز · المكان · آخر نشاط · [إنهاء] (2.3 · 24.5) --}}
@can('user_sessions.list')
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

            @can('user_sessions.delete')
                <form method="post" action="{{ route('settings.devices.destroy', $device) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn rounded-xl px-3 py-2 text-xs motion-standard"
                            style="min-height: 44px; {{ $secInputStyle }}">إنهاء</button>
                </form>
            @endcan
        </div>
    @empty
        <p class="text-sm py-2" style="color: var(--text-muted)">مفيش جلسات مسجّلة دلوقتي.</p>
    @endforelse

    {{-- ⭐ الزرّ المنصوص عليه: إنهاء **كلّ** الجلسات دفعةً واحدة (2.3) --}}
    @can('user_sessions.delete')
        <form method="post" action="{{ route('settings.devices.destroy-all') }}" class="mt-3 pt-3"
              data-settings-item data-keywords="خروج من كلّ الأجهزة logout all devices"
              style="border-top: 1px solid var(--border)"
              onsubmit="return confirm('هنقفل كلّ الجلسات على كلّ الأجهزة — وهتحتاج تسجّل دخولك تاني. نكمّل؟')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="min-height: 44px; background: color-mix(in srgb, var(--color-state-warn) 18%, transparent); color: var(--text)">
                <x-icon name="logout" size="16" /> {{ setting('auth.logout.all_devices_label', 'تسجيل الخروج من كلّ الأجهزة') }}
            </button>
            <p class="text-xs mt-2" style="color: var(--text-muted)">
                بيقفل حسابك على كلّ الأجهزة — بما فيها الجهاز ده.
            </p>
        </form>
    @endcan
@endcan

@can('data_export.create')
    <div class="mt-4 pt-3" data-settings-item data-keywords="تحميل بياناتي export"
         style="border-top: 1px solid var(--border)">
        <a href="{{ route('settings.export') }}"
           class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="min-height: 44px; {{ $secInputStyle }}">تحميل بياناتي</a>
        <p class="text-xs mt-2" style="color: var(--text-muted)">ملفّ JSON فيه كلّ اللي المنصّة محتفظة بيه عنك.</p>
    </div>
@endcan

{{-- منطقة الخطر: حذف الحساب بتأكيد OTP رباعيّ — **Soft-delete** (2.3) --}}
@can('user_profile.edit')
    <div data-settings-item data-keywords="حذف الحساب منطقة الخطر danger delete">
        @include('security.danger-zone')
    </div>
@endcan
