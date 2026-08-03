@extends('layouts.admin')

@section('title', 'سجلّ الـWebhook الخام')

@section('content')
    <x-page-header title="سجلّ الـWebhook الخام"
                   subtitle="كلّ نداء بوقته وIPه وجسمه الكامل ونتيجة التحقّق — عرضٌ فقط."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'بوّابة الدفع', 'url' => route('admin.topups.gateway')],
                       ['label' => 'سجلّ الـWebhook'],
                   ]" />

    <x-filters :action="route('admin.topups.gateway.logs')">
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">رقم الفاتورة</span>
            <input type="search" name="invoice" value="{{ request('invoice') }}"
                   class="rounded-xl px-3 py-2 text-sm w-48"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">التحقّق</span>
            <select name="hash" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="valid" @selected(request('hash') === 'valid')>هاش صحيح</option>
                <option value="invalid" @selected(request('hash') === 'invalid')>هاش خاطئ</option>
            </select>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">فلترة</button>
    </x-filters>

    @if ($logs->isEmpty())
        <x-empty message="مافيش نداءات مسجَّلة لسه." />
    @else
        <div class="space-y-3">
            @foreach ($logs as $log)
                <details class="card p-3 text-sm">
                    <summary class="cursor-pointer flex flex-wrap items-center justify-between gap-2">
                        <span class="font-mono text-xs">{{ $log->invoice_id ?? '—' }}</span>
                        <span class="flex items-center gap-2">
                            <x-state-badge :state="$log->hash_valid ? 'ok' : 'danger'" :label="$log->hash_valid ? 'هاش صحيح' : 'هاش خاطئ'" />
                            <span class="text-xs" style="color: var(--text-muted)">{{ $log->result ?? '—' }} · {{ $log->created_at?->diffForHumans() }}</span>
                        </span>
                    </summary>
                    <div class="mt-2 text-xs" style="color: var(--text-muted)">IP: {{ $log->ip ?? '—' }}</div>
                    {{-- الجسم الخام داخل حاوية متمرّرة بذاتها فلا تُمرَّر الصفحة أفقيًّا --}}
                    <pre class="mt-2 text-xs p-2 rounded-xl" style="background: var(--surface-sunken); overflow-x: auto">{{ $log->body }}</pre>
                </details>
            @endforeach
        </div>

        <div class="mt-5">{{ $logs->links() }}</div>
    @endif
@endsection
