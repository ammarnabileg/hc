{{-- 4) سجلّ الصادر (12.5-د): الحالات الثلاث سارية · منتهية · ملغاة --}}
<x-filters :action="route('admin.certificates.index')">
    <input type="hidden" name="tab" value="ledger">

    <label class="block flex-1 min-w-[12rem]">
        <span class="block text-sm mb-1">بحث</span>
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="كود الشهادة أو اسم صاحبها…"
               class="w-full rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    </label>

    <label class="block">
        <span class="block text-sm mb-1">النوع</span>
        <select name="type" class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <option value="">الكلّ</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected($filters['type'] === $type->id)>{{ $type->name_ar }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="block text-sm mb-1">الحالة</span>
        <select name="status" class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <option value="">الكلّ</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">تصفية</button>

    <x-slot:advanced>
        <label class="block">
            <span class="block text-sm mb-1">المصدر</span>
            <select name="source" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($sources as $key => $label)
                    <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="block text-sm mb-1">من تاريخ</span>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-slot:advanced>
</x-filters>

@can('certificate_ledger.export')
    <div class="mb-3 text-end">
        <a href="{{ route('admin.certificates.export', request()->query()) }}" class="text-sm underline">
            تصدير/طباعة جماعيّة
        </a>
    </div>
@endcan

@if ($certificates->isEmpty())
    <x-empty message="مفيش شهادات في الفلاتر دي." />
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
                            · المصدر: {{ $sources[$certificate->source] ?? $certificate->source }}
                            · <span title="{{ $certificate->issued_at }}">{{ $certificate->issued_at?->diffForHumans() }}</span>
                        </div>
                        @if ($certificate->status === 'revoked' && $certificate->revoked_reason)
                            <div class="text-xs mt-1" style="color: var(--color-state-danger)">
                                سبب الإلغاء: {{ $certificate->revoked_reason }}
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
                           class="underline">رابط التحقّق</a>
                    @endif

                    @can('certificates.delete')
                        @if ($certificate->status === 'valid')
                            <button type="button" data-modal-open="revoke-{{ $certificate->id }}"
                                    class="underline" style="color: var(--color-state-danger)">إلغاء</button>
                        @endif
                    @endcan

                    @can('certificates.create')
                        <form method="post" action="{{ route('admin.certificates.reissue', $certificate) }}"
                              onsubmit="return confirm('{{ setting('certificates.reissue.confirm_text', 'هنبطل القديمة ونصدر مصحّحة — نكمّل؟') }}')">
                            @csrf
                            <button class="underline">إعادة إصدار</button>
                        </form>
                    @endcan
                </div>
            </div>

            @can('certificates.delete')
                @if ($certificate->status === 'valid')
                    <x-modal :id="'revoke-'.$certificate->id" title="إلغاء شهادة">
                        <form method="post" action="{{ route('admin.certificates.revoke', $certificate) }}" class="space-y-3">
                            @csrf
                            <p class="text-sm" style="color: var(--text-muted)">
                                الإلغاء للتزوير المثبَت — والسبب إلزاميّ وبيتسجّل في التدقيق.
                            </p>
                            <label class="block">
                                <span class="block text-sm mb-1">السبب</span>
                                <select name="reason" required class="w-full rounded-xl px-3 py-2 text-sm"
                                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    @foreach ($revokeReasons as $reason)
                                        <option value="{{ $reason }}">{{ $reason }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="notify" value="1" checked> نبلّغ صاحبها بلباقة
                            </label>
                            <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                                    style="background: var(--color-state-danger); color: #fff">ألغِ الشهادة</button>
                        </form>
                    </x-modal>
                @endif
            @endcan
        @endforeach
    </div>

    <div class="mt-4">{{ $certificates->links() }}</div>
@endif
