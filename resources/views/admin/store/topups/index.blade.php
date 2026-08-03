@extends('layouts.admin')

@section('title', 'طلبات الشحن')

@section('content')
    <x-page-header title="طلبات الشحن"
                   subtitle="راجع الإيصال الأوّل — وبعدين اعتمد."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'المتجر والماليّات', 'url' => route('admin.store.index')],
                       ['label' => 'طلبات الشحن'],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.topups.methods') }}" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">طرق التحويل والعروض</a>
            <a href="{{ route('admin.topups.gateway') }}" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">بوّابة الدفع</a>
        </x-slot:action>
    </x-page-header>

    {{-- ⭐ تاب بعدّاد N لأيّ جديد — والحالات الأربع لا خامس لها (19.5-ب-4) --}}
    <x-tabs :current="$filters['status']" :tabs="collect($statuses)->map(fn ($label, $key) => [
        'key' => $key,
        'label' => $label,
        'count' => $key === 'pending_review' ? $pendingCount : null,
        'url' => route('admin.topups.index', ['status' => $key]),
    ])->values()->push([
        'key' => 'all',
        'label' => 'الكلّ',
        'url' => route('admin.topups.index', ['status' => 'all']),
    ])->all()" />

    <x-filters :action="route('admin.topups.index')">
        <input type="hidden" name="status" value="{{ $filters['status'] }}">

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="رقم الطلب أو كود المستخدم…"
                   class="rounded-xl px-3 py-2 text-sm w-52"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">طريقة التحويل</span>
            <select name="method" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($methods as $method)
                    <option value="{{ $method->id }}" @selected($filters['method'] == $method->id)>{{ $method->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">من</span>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">فلترة</button>

        <x-slot:advanced>
            <label class="text-xs">
                <span class="block mb-1" style="color: var(--text-muted)">إلى</span>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty message="مافيش طلبات في النطاق ده — كلّ حاجة هادية." />
    @else
        <div class="card overflow-hidden">
            <table class="hidden md:table w-full text-sm">
                <thead style="background: var(--surface-sunken)">
                    <tr class="text-xs" style="color: var(--text-muted)">
                        <th class="text-start p-3">الطلب</th>
                        <th class="text-start p-3">المستخدم</th>
                        <th class="text-start p-3">القيمة المحوَّلة</th>
                        <th class="text-start p-3">طريقة التحويل</th>
                        <th class="text-start p-3">الحالة</th>
                        <th class="text-start p-3">العمر (داخليّ)</th>
                        <th class="text-start p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-3 font-mono text-xs">{{ $row->number }}</td>
                            <td class="p-3">{{ $row->user?->name }} <span style="color: var(--text-muted)">#{{ $row->user?->code }}</span></td>
                            <td class="p-3">{{ rtrim(rtrim(number_format((float) $row->transferred_amount, 2), '0'), '.') }}</td>
                            <td class="p-3">{{ $row->transfer_method?->name_ar ?? '—' }}</td>
                            <td class="p-3">
                                <x-state-badge :state="match ($row->status) { 'completed' => 'ok', 'pending_review' => 'warn', 'cancelled' => 'danger', default => 'idle' }"
                                               :label="$statuses[$row->status] ?? $row->status" />
                            </td>
                            {{-- ⛔ العدّاد وتلوين المتأخّر **داخليّان للأدمن فقط** ولا يُعلَنان للمُرسِل (19.5-أ) --}}
                            <td class="p-3 text-xs"
                                style="color: {{ $review->internalIsLate($row) ? 'var(--color-state-danger)' : 'var(--text-muted)' }}">
                                {{ $review->internalAgeHours($row) }} ساعة
                            </td>
                            <td class="p-3">
                                <a href="{{ route('admin.topups.show', $row) }}" class="text-xs underline">افتح المراجعة</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="md:hidden">
                @foreach ($rows as $row)
                    <a href="{{ route('admin.topups.show', $row) }}" class="block p-3 text-sm" style="border-top: 1px solid var(--border)">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs">{{ $row->number }}</span>
                            <x-state-badge :state="match ($row->status) { 'completed' => 'ok', 'pending_review' => 'warn', 'cancelled' => 'danger', default => 'idle' }"
                                           :label="$statuses[$row->status] ?? $row->status" />
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $row->user?->name }} · {{ rtrim(rtrim(number_format((float) $row->transferred_amount, 2), '0'), '.') }}
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="mt-5">{{ $rows->links() }}</div>
    @endif
@endsection
