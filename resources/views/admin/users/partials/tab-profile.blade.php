@php
    /*
     | تاب المعلومات الأساسيّة (12.1): **تعديل يدويّ** + **Checkbox تأكيد الإيميل** +
     | **ملاحظات إداريّة داخليّة (للفريق فقط)**.
     |
     | كان التاب للقراءة فقط — فمَن غلط في بريده عند التسجيل ماكانش قدّامه إلّا
     | حساب جديد. والتعديل بصلاحيّته `admin_user_detail.edit` **ومَن لا يملكها
     | يرى العرض فقط** بلا حقول معطَّلة (2.15-أ-7).
     */
    $canEdit = auth()->user()->allows('admin_user_detail.edit');
@endphp

<section class="card p-4">
    <h3 class="font-bold text-sm mb-3">{{ setting('admin.users.partials.tab_profile.albyanat_alasasya', 'البيانات الأساسيّة') }}</h3>

    @if (! $canEdit)
        <dl class="grid gap-3 sm:grid-cols-2 text-sm">
            <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_profile.alasm', 'الاسم') }}</dt><dd>{{ $user->name }}</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_profile.alkwd', 'الكود') }}</dt><dd>#{{ $user->code }}</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_profile.albryd', 'البريد') }}</dt><dd>{{ $directory->mask($user->email, 'email') }}</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_profile.almwbayl', 'الموبايل') }}</dt><dd>{{ $directory->mask($user->phone, 'phone') }}</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_profile.aldwla', 'الدولة') }}</dt><dd>{{ $user->country?->name_ar ?? '—' }}</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_profile.almhafza', 'المحافظة') }}</dt><dd>{{ $user->governorate?->name_ar ?? '—' }}</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted)">XP</dt><dd>{{ number_format((int) $user->xp) }}</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_profile.tarykh_altsjyl', 'تاريخ التسجيل') }}</dt><dd>{{ $user->created_at?->format('Y-m-d') }}</dd></div>
        </dl>
    @else
        <form method="post" action="{{ route('admin.users.update', $user) }}" class="grid gap-3 sm:grid-cols-2">
            @csrf
            @method('put')

            <x-form.input name="name" :label="setting('admin.users.partials.tab_profile.alasm', 'الاسم')" :value="$user->name" required />
            <x-form.input name="email" :label="setting('admin.users.partials.tab_profile.albryd', 'البريد')" type="email" :value="$user->email" required />
            <x-form.input name="phone" :label="setting('admin.users.partials.tab_profile.almwbayl', 'الموبايل')" :value="$user->phone" />
            <x-form.input name="birthdate" :label="setting('admin.users.partials.tab_profile.tarykh_almylad', 'تاريخ الميلاد')" type="date"
                          :value="$user->birthdate?->format('Y-m-d')" />

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.users.partials.tab_profile.alnwa', 'النوع') }}</span>
                <select name="gender" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">—</option>
                    <option value="male" @selected($user->gender === 'male')>{{ setting('admin.users.partials.tab_profile.dhkr', 'ذكر') }}</option>
                    <option value="female" @selected($user->gender === 'female')>{{ setting('admin.users.partials.tab_profile.antha', 'أنثى') }}</option>
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.users.partials.tab_profile.almhafza', 'المحافظة') }}</span>
                <select name="governorate_id" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">—</option>
                    @foreach ($governorates as $governorate)
                        <option value="{{ $governorate->id }}" @selected($user->governorate_id === $governorate->id)>{{ $governorateLabels[$governorate->id] ?? $governorate->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            {{-- ⭐ تأكيد الإيميل يدويًّا بـCheckbox — لمن اتعطّل عنده الرمز (12.1) --}}
            <label class="sm:col-span-2 flex items-center gap-2 text-sm" style="min-block-size: 44px">
                <input type="checkbox" name="email_verified" value="1" @checked($user->email_verified_at)>
                <span>{{ setting('admin.users.partials.tab_profile.albryd_mwkd', 'البريد مؤكَّد') }}</span>
                @if ($user->email_verified_at)
                    <x-state-badge state="ok" :label="setting('admin.users.partials.tab_profile.atakd', 'اتأكّد ').$user->email_verified_at->format('Y-m-d')" />
                @endif
            </label>

            {{-- ⭐ ملاحظات إداريّة داخليّة — للفريق فقط، ولا تخرج في تصدير البيانات --}}
            <label class="sm:col-span-2 block">
                <span class="block text-sm mb-1">{{ setting('admin.users.partials.tab_profile.mlahzat_idarya_dakhlya', 'ملاحظات إداريّة داخليّة') }}</span>
                <textarea name="admin_notes" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('admin_notes', $user->admin_notes) }}</textarea>
                <span class="block text-xs mt-1" style="color: var(--text-muted)">
                    {{ setting('admin.users.notes_hint', 'ملاحظات للفريق فقط — المستخدم مابيشوفهاش أبدًا.') }}
                </span>
            </label>

            <div class="sm:col-span-2">
                <button class="btn rounded-xl px-5 py-2 text-sm font-semibold motion-standard"
                        style="min-block-size: 44px; background: var(--color-brand-500); color:#04201c">{{ setting('admin.users.partials.tab_profile.hfz_altadylat', 'حفظ التعديلات') }}</button>
            </div>
        </form>
    @endif
</section>
