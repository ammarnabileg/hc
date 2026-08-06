{{-- تاب [السجلّ الصادر] (24.2): مستحقّ ولم تُصدَر ⟵ فلاتر ⟵ جدول/كروت بالتمرير التدريجيّ --}}
<div class="flex items-center gap-2 mb-4 text-sm">
    <a href="{{ route('admin.volunteer.certificates', ['tab' => 'ledger', 'view' => 'ledger']) }}"
       class="rounded-xl px-3 py-1.5 motion-standard"
       style="{{ $view === 'ledger' ? 'background: var(--color-brand-500); color:#04201c; font-weight:700' : 'background: var(--surface-sunken); color: var(--text)' }}">{{ setting('admin.volunteer.certificates.alsjl_alsadr', 'السجلّ الصادر') }}</a>
    <a href="{{ route('admin.volunteer.certificates', ['tab' => 'ledger', 'view' => 'pending']) }}"
       class="rounded-xl px-3 py-1.5 motion-standard"
       style="{{ $view === 'pending' ? 'background: var(--color-brand-500); color:#04201c; font-weight:700' : 'background: var(--surface-sunken); color: var(--text)' }}">{{ setting('admin.volunteer.certificates.msthq_wlm_tsdr', 'مستحقّ ولم تُصدَر') }}</a>
</div>

@if ($view === 'pending')
    <section class="card p-4 md:p-5">
        @include('admin.volunteer.certificates.partials.pending', ['pending' => $pending])
    </section>
@else
    <x-filters :action="route('admin.volunteer.certificates')" screen="volunteer_certificates_ledger">
        <input type="hidden" name="tab" value="ledger">
        <input type="hidden" name="view" value="ledger">

        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.bhth', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('admin.volunteer.certificates.bhth_ph', 'الاسم أو الكود أو كود الشهادة…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.alnwa', 'النوع') }}</span>
            <select name="type" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.volunteer.certificates.alkl', 'الكلّ') }}</option>
                @foreach ($filterOptions['types'] as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.alhala', 'الحالة') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.volunteer.certificates.alkl', 'الكلّ') }}</option>
                <option value="valid" @selected($filters['status'] === 'valid')>{{ setting('admin.volunteer.certificates.sadra', 'صادرة') }}</option>
                <option value="revoked" @selected($filters['status'] === 'revoked')>{{ setting('admin.volunteer.certificates.mlghaa', 'ملغاة') }}</option>
            </select>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.volunteer.certificates.tsfya', 'تصفية') }}</button>

        @can('volunteer_certificates.list')
            <a href="{{ route('admin.volunteer.certificates.export', request()->query()) }}" class="text-sm underline">{{ setting('admin.volunteer.certificates.tsdyr_alsjl', 'تصدير السجلّ') }}</a>
        @endcan

        <x-slot:advanced>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.almsar', 'المسار') }}</span>
                <select name="track_id" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('admin.volunteer.certificates.alkl', 'الكلّ') }}</option>
                    @foreach ($filterOptions['tracks'] as $track)
                        <option value="{{ $track->id }}" @selected($filters['track_id'] === $track->id)>{{ $track->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.alkyan', 'الكيان') }}</span>
                <select name="entity_id" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('admin.volunteer.certificates.alkl', 'الكلّ') }}</option>
                    @foreach ($filterOptions['entities'] as $entity)
                        <option value="{{ $entity->id }}" @selected($filters['entity_id'] === $entity->id)>{{ $entity->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.albwzshn', 'البوزشن') }}</span>
                <select name="position_id" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('admin.volunteer.certificates.alkl', 'الكلّ') }}</option>
                    @foreach ($filterOptions['positions'] as $position)
                        <option value="{{ $position->id }}" @selected($filters['position_id'] === $position->id)>{{ $position->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.allgha', 'اللغة') }}</span>
                <select name="language" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('admin.volunteer.certificates.alkl', 'الكلّ') }}</option>
                    <option value="ar" @selected($filters['language'] === 'ar')>{{ setting('admin.volunteer.certificates.lang_ar', 'عربيّة') }}</option>
                    <option value="en" @selected($filters['language'] === 'en')>{{ setting('admin.volunteer.certificates.lang_en', 'إنجليزيّة') }}</option>
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.mn_tarykh', 'من تاريخ') }}</span>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.ila_tarykh', 'إلى تاريخ') }}</span>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($issued->isEmpty())
        <x-empty :message="setting('admin.volunteer.certificates.la_shhadat_sadra_bad', 'لا شهادات صادرة بعد.')" />
    @else
        <div class="card p-0 overflow-hidden hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.volunteer.certificates.kwd_alshhada', 'كود الشهادة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.volunteer.certificates.almstfyd', 'المستفيد') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.volunteer.certificates.alnwa', 'النوع') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.volunteer.certificates.albwzshn_alkyan', 'البوزشن/الكيان') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.volunteer.certificates.almda', 'المدّة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.volunteer.certificates.ntaq_almswwlya', 'نطاق المسؤوليّة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.volunteer.certificates.tarykh_alisdar', 'تاريخ الإصدار') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.volunteer.certificates.allgha', 'اللغة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.volunteer.certificates.alhala', 'الحالة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.volunteer.certificates.ijraat', 'إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody data-cert-ledger-desktop>
                    @include('admin.volunteer.certificates.partials.ledger-rows-desktop', ['issued' => $issued])
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 md:hidden" data-cert-ledger-mobile>
            @include('admin.volunteer.certificates.partials.ledger-rows-mobile', ['issued' => $issued])
        </div>

        {{-- بلا إخفاء هنا: كلّ نافذة `<x-modal>` مخفيّة بنفسها، وإخفاء الحاوية يمنع إظهارها لاحقًا --}}
        <div data-cert-ledger-modals>
            @include('admin.volunteer.certificates.partials.ledger-modals', ['issued' => $issued])
        </div>

        @include('partials.load-more', [
            'hasMore' => $hasMore,
            'moreUrl' => route('admin.volunteer.certificates.more', array_merge(request()->except('offset'), ['offset' => $nextOffset])),
            'nextOffset' => $nextOffset,
            'pageSize' => $pageSize,
            'targetSelector' => '[data-cert-ledger-desktop], [data-cert-ledger-mobile], [data-cert-ledger-modals]',
        ])
    @endif
@endif
