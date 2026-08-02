@php
    /**
     * إعدادات العنصر الشرفيّ «أخوكم» (13.4-ص-د) — **لمالك المنصّة وحده 🔒**.
     * $honorary: مخرجات SettingsWriter::groupRows('volunteer_honorary')
     * $honoraryPlaces · $honoraryFrames: القوائم المقفولة من الخدمة
     */
    $val = fn (string $key, $fallback = '') => $honorary[$key]['value'] ?? $fallback;
    $places = json_decode((string) $val('volunteer.honorary.places', '{}'), true);
    $places = is_array($places) ? $places : [];
    $field = 'w-full rounded-xl px-3 py-2 text-sm';
    $fieldStyle = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
@endphp

<details class="card p-4 md:p-5 mt-4">
    <summary class="cursor-pointer font-bold select-none"><x-icon name="crown" size="18" /> العنصر الشرفيّ «أخوكم» <span class="text-xs" style="color: var(--text-muted)">— لمالك المنصّة وحده</span></summary>

    <p class="text-xs mt-2" style="color: var(--text-muted)">
        شرفيّ بحت: بلا صلاحيّات ولا نطاق إشراف ولا داونلاين، ولا يدخل أيّ عدّاد،
        ولا تُصدَر له شهادة بوزشن ولا بطاقة متطوّع.
    </p>

    <form method="post" action="{{ route('admin.volunteer.honorary.save') }}" class="mt-3">
        @csrf

        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-semibold mb-1" for="hon-enabled">الإظهار</label>
                <select id="hon-enabled" name="settings[volunteer.honorary.enabled]" class="{{ $field }}" style="{{ $fieldStyle }}">
                    <option value="1" @selected((string) $val('volunteer.honorary.enabled') === '1')>مفعَّل</option>
                    <option value="0" @selected((string) $val('volunteer.honorary.enabled') !== '1')>موقوف</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1" for="hon-user">الحساب المرتبط</label>
                <select id="hon-user" name="settings[volunteer.honorary.user_id]" class="{{ $field }}" style="{{ $fieldStyle }}">
                    <option value="0" @selected((int) $val('volunteer.honorary.user_id', 0) === 0)>حساب مالك المنصّة (الافتراضيّ)</option>
                    @foreach ($honoraryAccounts as $account)
                        <option value="{{ $account->id }}" @selected((int) $val('volunteer.honorary.user_id', 0) === $account->id)>
                            {{ $account->name }} · #{{ $account->code }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1" for="hon-ar">الوصف — عربيّ</label>
                <input id="hon-ar" type="text" maxlength="60" name="settings[volunteer.honorary.label_ar]"
                       value="{{ $val('volunteer.honorary.label_ar', 'أخوكم') }}" class="{{ $field }}" style="{{ $fieldStyle }}">
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1" for="hon-en">الوصف — إنجليزيّ</label>
                <input id="hon-en" type="text" maxlength="60" name="settings[volunteer.honorary.label_en]" dir="ltr"
                       value="{{ $val('volunteer.honorary.label_en', 'Your brother') }}" class="{{ $field }}" style="{{ $fieldStyle }}">
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1" for="hon-frame">شكل الإطار</label>
                <select id="hon-frame" name="settings[volunteer.honorary.frame_style]" class="{{ $field }}" style="{{ $fieldStyle }}">
                    @foreach ($honoraryFrames as $key => $label)
                        <option value="{{ $key }}" @selected((string) $val('volunteer.honorary.frame_style', 'soft') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1" for="hon-note">سطر التوضيح</label>
                <input id="hon-note" type="text" maxlength="120" name="settings[volunteer.honorary.note]"
                       value="{{ $val('volunteer.honorary.note') }}" class="{{ $field }}" style="{{ $fieldStyle }}">
            </div>
        </div>

        <fieldset class="mt-3">
            <legend class="text-sm font-semibold mb-1">أماكن الظهور</legend>
            <div class="flex flex-wrap gap-3">
                @foreach ($honoraryPlaces as $key => $label)
                    <label class="flex items-center gap-2 text-sm rounded-xl px-3 py-2" style="background: var(--surface-sunken); min-height: 44px">
                        <input type="checkbox" name="places[{{ $key }}]" value="1"
                               @checked((bool) ($places[$key] ?? $key !== 'landing'))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="flex items-center gap-2 mt-4">
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">احفظ</button>
            <span class="text-xs" style="color: var(--text-muted)">كلّ تعديل هنا يُسجَّل في سجلّ التدقيق.</span>
        </div>
    </form>

    <form method="post" action="{{ route('admin.volunteer.reset', 'volunteer_honorary') }}" class="mt-3">
        @csrf
        <button type="submit" class="text-xs underline" style="color: var(--text-muted)"><x-icon name="refresh" size="16" /> رجّع إعدادات العنصر الشرفيّ للافتراضيّ</button>
    </form>
</details>
