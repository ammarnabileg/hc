@props(['message' => 'مفيش حاجة هنا', 'action' => null, 'href' => null])

{{-- الحالة الفارغة = سطر واحد + زرّ واحد (2.15-د) — تشجّع ولا تعاتب (2.17-ج) --}}
<div class="card p-8 text-center">
    <p class="text-sm" style="color: var(--text-muted)">{{ $message }}</p>
    @if ($action && $href)
        <a href="{{ $href }}" class="btn inline-flex items-center justify-center mt-4 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ $action }}</a>
    @endif
</div>
