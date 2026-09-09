{{-- 4) سجلّ الصادر (12.5-د): الحالات الثلاث سارية · منتهية · ملغاة --}}
<x-filters :action="route('admin.certificates.index')">
    <input type="hidden" name="tab" value="ledger">

    <label class="block flex-1 min-w-[12rem]">
        <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.ledger.bhth', 'بحث') }}</span>
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('admin.certificates.partials.ledger.kwd_alshhada_aw_asm_sahbha', 'كود الشهادة أو اسم صاحبها…') }}"
               class="w-full rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    </label>

    <label class="block">
        <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.ledger.alnwa', 'النوع') }}</span>
        <select name="type" class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <option value="">{{ setting('admin.certificates.partials.ledger.alkl', 'الكلّ') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected($filters['type'] === $type->id)>{{ $type->name_ar }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.ledger.alhala', 'الحالة') }}</span>
        <select name="status" class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <option value="">{{ setting('admin.certificates.partials.ledger.alkl', 'الكلّ') }}</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.certificates.partials.ledger.tsfya', 'تصفية') }}</button>

    <x-slot:advanced>
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.ledger.almsdr', 'المصدر') }}</span>
            <select name="source" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.certificates.partials.ledger.alkl', 'الكلّ') }}</option>
                @foreach ($sources as $key => $label)
                    <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.ledger.mn_tarykh', 'من تاريخ') }}</span>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-slot:advanced>
</x-filters>

{{--
    ⭐ تصدير/طباعة جماعيّة (24.1 سطر 4676): pop-box حقيقيّ — بالنوع/الفعاليّة
    **أو** بأكواد الأشخاص + اختيار الصيغة. اللافتة محروسةٌ حرفًا (القسم 24)،
    والزرّ يُخفى لا يُعطَّل لو أُوقف Toggle «إتاحة التصدير الجماعيّ» (2.15-أ-7).
--}}
@can('certificate_ledger.export')
    @if ($exportEnabled)
        <div class="mb-3 text-end">
            <button type="button" data-modal-open="bulk-export" class="text-sm underline">
                {{ setting('admin.certificates.partials.ledger.tsdyr_tbaaa_jmaaya', 'تصدير/طباعة جماعيّة') }}
            </button>
        </div>

        <x-modal id="bulk-export" :title="setting('admin.certificates.partials.ledger.tsdyr_tbaaa_jmaaya', 'تصدير/طباعة جماعيّة')">
            <form method="get" action="{{ route('admin.certificates.export') }}" class="space-y-3">
                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-1">
                        <input type="radio" name="mode" value="filters" data-bulk-export-mode checked>
                        {{ setting('admin.certificates.partials.ledger.balnwa_alfaalya', 'بالنوع/الفعاليّة') }}
                    </label>
                    <label class="flex items-center gap-1">
                        <input type="radio" name="mode" value="codes" data-bulk-export-mode>
                        {{ setting('admin.certificates.partials.ledger.bakwad_alashkhas', 'بأكواد الأشخاص') }}
                    </label>
                </div>

                <div data-bulk-export-panel="filters" class="space-y-3">
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.ledger.alnwa', 'النوع') }}</span>
                        <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('admin.certificates.partials.ledger.alkl', 'الكلّ') }}</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}" @selected($filters['type'] === $type->id)>{{ $type->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.ledger.alfaalya', 'الفعاليّة (اختياريّ)') }}</span>
                        <select name="event_id" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('admin.certificates.partials.ledger.bla_thdyd', 'بلا تحديد') }}</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->id }}">{{ $event->title_ar }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div data-bulk-export-panel="codes" class="hidden">
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.ledger.akwad_alashkhas', 'أكواد الأشخاص') }}</span>
                        <textarea name="codes" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                                  placeholder="{{ setting('certificates.issue.codes_placeholder', 'الصق الأكواد مفصولة بمسافة أو فاصلة…') }}"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                </div>

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.ledger.alsygha', 'الصيغة') }}</span>
                    <select name="format" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="csv">{{ setting('admin.certificates.partials.ledger.csv', 'CSV') }}</option>
                        <option value="images">{{ setting('admin.certificates.partials.ledger.swr_zip', 'صور (ZIP)') }}</option>
                    </select>
                </label>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.certificates.partials.ledger.tsdyr', 'تصدير') }}</button>
            </form>
        </x-modal>

        @push('scripts')
            <script>
                document.querySelectorAll('[data-bulk-export-mode]').forEach((radio) => {
                    radio.addEventListener('change', () => {
                        const mode = document.querySelector('[data-bulk-export-mode]:checked').value;
                        document.querySelectorAll('[data-bulk-export-panel]').forEach((panel) => {
                            panel.classList.toggle('hidden', panel.dataset.bulkExportPanel !== mode);
                        });
                    });
                });
            </script>
        @endpush
    @endif
@endcan

@if ($certificates->isEmpty())
    <x-empty :message="setting('admin.certificates.partials.ledger.mfysh_shhadat_fy_alflatr_dy', 'مفيش شهادات في الفلاتر دي.')" />
@else
    <div class="space-y-3">
        @foreach ($certificates as $certificate)
            <div class="card p-4">
                <div class="flex items-start gap-3 flex-wrap">
                    <x-avatar :user="$certificate->user" size="10" />

                    <div class="flex-1 min-w-0">
                        <div class="font-semibold">{{ $certificate->code }}</div>
                        <div class="text-sm">{{ $certificate->user?->name }} · #{{ $certificate->user?->code }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $certificate->certificate_type?->name_ar }}
                            {{ setting('admin.certificates.partials.ledger.almsdr_2', '· المصدر:') }} {{ $sources[$certificate->source] ?? $certificate->source }}
                            · <span title="{{ $certificate->issued_at }}">{{ $certificate->issued_at?->diffForHumans() }}</span>
                        </div>
                        @if ($certificate->status === 'revoked' && $certificate->revoked_reason)
                            <div class="text-xs mt-1" style="color: var(--color-state-danger)">
                                {{ setting('admin.certificates.partials.ledger.sbb_alilgha', 'سبب الإلغاء:') }} {{ $certificate->revoked_reason }}
                            </div>
                        @endif
                    </div>

                    <x-state-badge
                        :state="match ($certificate->status) { 'valid' => 'ok', 'expired' => 'idle', default => 'danger' }"
                        :label="$statuses[$certificate->status] ?? $certificate->status" />
                </div>

                <div class="flex gap-3 mt-3 flex-wrap text-xs">
                    @if (\Illuminate\Support\Facades\Route::has('verify.certificate'))
                        <a href="{{ route('verify.certificate', ['code' => $certificate->code]) }}"
                           class="underline">{{ setting('admin.certificates.partials.ledger.rabt_althqq', 'رابط التحقّق') }}</a>
                    @endif

                    @can('certificates.delete')
                        @if ($certificate->status === 'valid')
                            <button type="button" data-modal-open="revoke-{{ $certificate->id }}"
                                    class="underline" style="color: var(--color-state-danger)">{{ setting('admin.certificates.partials.ledger.ilgha', 'إلغاء') }}</button>
                        @endif
                    @endcan

                    @can('certificates.create')
                        <form method="post" action="{{ route('admin.certificates.reissue', $certificate) }}"
                              onsubmit="return confirm('{{ setting('certificates.reissue.confirm_text', 'هنبطل القديمة ونصدر مصحّحة — نكمّل؟') }}')">
                            @csrf
                            <button class="underline">{{ setting('admin.certificates.partials.ledger.iaada_isdar', 'إعادة إصدار') }}</button>
                        </form>
                    @endcan
                </div>
            </div>

            @can('certificates.delete')
                @if ($certificate->status === 'valid')
                    <x-modal :id="'revoke-'.$certificate->id" :title="setting('admin.certificates.partials.ledger.ilgha_shhada', 'إلغاء شهادة')">
                        <form method="post" action="{{ route('admin.certificates.revoke', $certificate) }}" class="space-y-3">
                            @csrf
                            <p class="text-sm" style="color: var(--text-muted)">
                                {{ setting('admin.certificates.partials.ledger.alilgha_lltzwyr_almthbt_walsbb_ilzamy', 'الإلغاء للتزوير المثبَت — والسبب إلزاميّ وبيتسجّل في التدقيق.') }}
                            </p>
                            <label class="block">
                                <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.ledger.alsbb', 'السبب') }}</span>
                                <select name="reason" required class="w-full rounded-xl px-3 py-2 text-sm"
                                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    @foreach ($revokeReasons as $reason)
                                        <option value="{{ $reason }}">{{ $reason }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="notify" value="1" checked> {{ setting('admin.certificates.partials.ledger.nblgh_sahbha_blbaqa', 'نبلّغ صاحبها بلباقة') }}
                            </label>
                            <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                                    style="background: var(--color-state-danger); color: #fff">{{ setting('admin.certificates.partials.ledger.algh_alshhada', 'ألغِ الشهادة') }}</button>
                        </form>
                    </x-modal>
                @endif
            @endcan
        @endforeach
    </div>

    <div class="mt-4">{{ $certificates->links() }}</div>
@endif
