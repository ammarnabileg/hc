@extends('layouts.app')
@section('title', 'الخصوصيّة والأمان')

@php
    use App\Services\Account\ConsentDirectory;

    $inputStyle = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
@endphp

@section('content')
    <x-page-header
        title="الخصوصيّة والأمان"
        subtitle="مين بيشوف بياناتك، وإزاي تحمي حسابك."
        :breadcrumbs="[
            ['label' => 'حسابي', 'url' => route('settings.index')],
            ['label' => 'الإعدادات', 'url' => route('settings.index')],
            ['label' => 'الخصوصيّة والأمان'],
        ]" />

    <div class="grid md:grid-cols-2 gap-4 items-start">

        {{-- ------------------------------------------ خصوصيّة كلّ حقل (13.4-م) --}}
        <section class="card p-4">
            <h2 class="font-bold text-sm">خصوصيّة كلّ حقل</h2>
            <p class="text-xs mt-1 mb-2" style="color: var(--text-muted)">اختار مين يشوف كلّ حقل — والحسّاس مقفول افتراضيًّا.</p>

            {{--
              ⭐ المحافظة **حقل عامّ دائمًا ولا يجوز إخفاؤها** (12.14-د) —
              ولذلك هي **غير موجودة في القائمة أصلًا، لا معطَّلة** (2.15-أ-7).
              ونكتفي بسطر يوضّح القاعدة حتى لا يبحث عنها المستخدم.
            --}}
            <p class="text-xs rounded-xl p-2 mb-2"
               style="background: color-mix(in srgb, var(--color-brand-500) 10%, transparent); color: var(--text-muted)">
                المحافظة بتفضل ظاهرة للكلّ على طول — دي قاعدة ثابتة في المنصّة.
            </p>

            <div>
                @foreach ($fields as $field)
                    <form method="post" action="{{ route('settings.privacy.field') }}"
                          data-privacy-form class="flex flex-wrap items-center gap-2 py-3"
                          style="border-top: 1px solid var(--border)">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="field" value="{{ $field['key'] }}">

                        <span class="flex-1 min-w-32 text-sm">
                            {{ $field['label'] }}
                            @if ($field['sensitive'])
                                <span class="text-xs" style="color: var(--text-muted)">· حسّاس</span>
                            @endif
                        </span>

                        <select name="visibility" class="rounded-xl px-3 py-2 text-sm" style="{{ $inputStyle }}"
                                aria-label="خصوصيّة {{ $field['label'] }}">
                            @foreach ($visibilities as $key => $label)
                                @if (in_array($key, $allowed, true))
                                    <option value="{{ $key }}" @selected($field['value'] === $key)>{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>

                        <button type="submit" class="btn rounded-xl px-3 py-2 text-xs motion-standard" data-privacy-submit
                                style="{{ $inputStyle }}">حفظ</button>
                        <span class="text-xs opacity-0 motion-standard" data-saved-flag style="color: var(--color-state-ok)">اتحفظ ✓</span>
                    </form>
                @endforeach
            </div>
        </section>

        {{-- --------------------------------- قائمة «مَن يرى بياناتي» (13.4-م) --}}
        <section class="card p-4">
            <h2 class="font-bold text-sm">مَن يرى بياناتي</h2>
            <p class="text-xs mt-1 mb-3" style="color: var(--text-muted)">
                دي الموافقات اللي إنت وافقت عليها بنفسك — وتقدر تسحبها في أيّ وقت.
            </p>

            @forelse ($consents as $consent)
                <div class="py-3" style="border-top: 1px solid var(--border)">
                    <div class="flex items-center gap-2">
                        <x-avatar :user="$consent->requester" size="9" />
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold truncate">{{ $consent->requester?->name }}</div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                {{ ConsentDirectory::fieldLabel($consent->field) }}
                                @if ($consent->consent_expires_at)
                                    · بتنتهي {{ $consent->consent_expires_at->format('Y-m-d') }}
                                @endif
                            </div>
                        </div>

                        {{-- السحب يقطع الرؤية فورًا وبلا إشعار للطرف الآخر (13.4-م) --}}
                        <form method="post" action="{{ route('settings.privacy.revoke', $consent) }}">
                            @csrf
                            <button type="submit" class="text-xs underline" style="color: var(--color-state-danger)">سحب</button>
                        </form>
                    </div>

                    {{-- بار زمنيّ صغير لما تبقّى من مدّة الموافقة --}}
                    <div class="mt-2 h-1 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                        <div class="h-full" style="width: {{ $consentBars[$consent->id] ?? 100 }}%; background: var(--color-brand-500)"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm py-2" style="color: var(--text-muted)">مفيش حدّ بيشوف بياناتك دلوقتي.</p>
            @endforelse
        </section>

        {{-- ------------------------------------------------ الأمان (24.5) --}}
        <section class="card p-4">
            <h2 class="font-bold text-sm mb-3">تغيير كلمة السرّ</h2>

            <form method="post" action="{{ route('settings.password') }}" class="space-y-3">
                @csrf
                <label class="block">
                    <span class="block text-sm mb-1">كلمة السرّ الحاليّة</span>
                    <input type="password" name="current_password" required class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $inputStyle }}">
                    @error('current_password')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
                </label>
                <label class="block">
                    <span class="block text-sm mb-1">كلمة السرّ الجديدة</span>
                    <input type="password" name="password" required class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $inputStyle }}">
                    @error('password')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
                </label>
                <label class="block">
                    <span class="block text-sm mb-1">تأكيد كلمة السرّ</span>
                    <input type="password" name="password_confirmation" required class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $inputStyle }}">
                </label>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">تغيير</button>
            </form>
        </section>

        <section class="card p-4">
            <h2 class="font-bold text-sm mb-3">الجلسات النشطة</h2>

            @forelse ($devices as $device)
                <div class="flex items-center justify-between gap-2 py-3" style="border-top: 1px solid var(--border)">
                    <div class="min-w-0 text-sm">
                        <div class="font-semibold truncate">
                            {{ $device->device_label ?? 'جهاز' }}
                            @if ($device->session_id === $currentSessionId)
                                <span class="text-xs" style="color: var(--color-state-ok)">· الجهاز الحاليّ</span>
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
                        <button type="submit" class="btn rounded-xl px-3 py-2 text-xs motion-standard" style="{{ $inputStyle }}">إنهاء</button>
                    </form>
                </div>
            @empty
                <p class="text-sm py-2" style="color: var(--text-muted)">مفيش جلسات مسجّلة دلوقتي.</p>
            @endforelse

            <div class="mt-4 pt-3" style="border-top: 1px solid var(--border)">
                <a href="{{ route('settings.export') }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">تحميل بياناتي</a>
                <p class="text-xs mt-2" style="color: var(--text-muted)">ملفّ JSON فيه كلّ اللي المنصّة محتفظة بيه عنك.</p>
            </div>
        </section>
    </div>

    {{-- منطقة الخطر: حذف الحساب بتأكيد OTP رباعيّ (2.3) --}}
    @include('security.danger-zone')
@endsection

@push('scripts')
    <script>
        // حفظ تلقائيّ لخصوصيّة الحقل مع «اتحفظ ✓» بجواره (2.17-ب)
        document.querySelectorAll('[data-privacy-form]').forEach((form) => {
            const submit = form.querySelector('[data-privacy-submit]');
            const flag = form.querySelector('[data-saved-flag]');
            if (submit) submit.hidden = true;

            form.querySelector('select')?.addEventListener('change', async () => {
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        body: new FormData(form),
                    });
                    if (!res.ok) throw new Error();
                    if (flag) {
                        flag.style.opacity = '1';
                        setTimeout(() => { flag.style.opacity = '0'; }, 2500);
                    }
                } catch {
                    if (flag) {
                        flag.textContent = 'تعذّر الحفظ — جرّب تاني';
                        flag.style.color = 'var(--color-state-danger)';
                        flag.style.opacity = '1';
                    }
                    if (submit) submit.hidden = false;
                }
            });
        });
    </script>
@endpush
