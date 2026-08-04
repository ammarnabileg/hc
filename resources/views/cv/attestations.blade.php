@php
    /** نصوص السكربت — من الإعدادات لا محروقةً في الجافاسكربت (2.13-أ) */
    $hcWords = array_merge($hcWords ?? [], [
        'attestations.copied_message' => (string) setting('attestations.copied_message', 'الرابط اتنسخ ✓'),
        'attestations.public.error_message' => (string) setting('attestations.public.error_message', 'مقدرناش نغيّر الحالة — راجع النت وجرّب تاني.'),
    ]);
@endphp

@extends('layouts.app')

@section('title', setting('attestations.page.title', 'الإفادة'))

@section('content')
    <x-page-header
        :title="setting('attestations.page.title', 'الإفادة')"
        :subtitle="setting('attestations.page.subtitle', 'إثباتٌ موثّق من المنصّة — مجّانًا بلا تذاكر.')"
        :breadcrumbs="[
            ['label' => setting('cv.breadcrumb.experiences', 'خبراتي'), 'url' => \Illuminate\Support\Facades\Route::has('cv.index') ? route('cv.index') : url('/')],
            ['label' => setting('attestations.page.title', 'الإفادة')],
        ]">
        <x-slot:action>
            <button type="button" data-modal-open="attestation-request"
                    class="btn hidden md:inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">
                {{ setting('attestations.request.action_label', 'اطلب إفادة') }}
            </button>
        </x-slot:action>
    </x-page-header>

    {{-- الإفادة المولَّدة تلقائيًّا من داتا المنصّة (9.1) --}}
    <div class="card p-4 mb-4">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-bold">{{ setting('attestations.record.title', 'إفادتك من المنصّة') }}</h2>
                <p class="text-sm mt-1" style="color: var(--text-muted)">
                    {{ setting('attestations.record.subtitle', 'بتتولّد لوحدها من تدريباتك وشهاداتك وشاراتك ونقاطك.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('attestations.export') }}" target="_blank" rel="noopener"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--surface-sunken)">{{ setting('attestations.export_label', 'استخراج') }}</a>
                <button type="button" data-copy="{{ $publicUrl }}" data-attestation-copy
                        class="btn rounded-xl px-4 py-2 text-sm motion-standard {{ $publicUrl ? '' : 'hidden' }}"
                        style="background: var(--surface-sunken)">{{ setting('attestations.share_label', 'نسخ الرابط العامّ') }}</button>
            </div>
        </div>

        <div class="grid gap-3 grid-cols-2 md:grid-cols-4 mt-4">
            <x-kpi :label="setting('attestations.kpi.courses', 'تدريبات مكتملة')" :value="$record['courses']->count()" icon="training" />
            <x-kpi :label="setting('attestations.kpi.certificates', 'شهادات سارية')" :value="$record['certificates']->count()" icon="certificate" />
            <x-kpi :label="setting('attestations.kpi.badges', 'شارات')" :value="$record['badges']->count()" icon="trophy" />
            <x-kpi :label="setting('attestations.kpi.xp', 'نقاط الخبرة')" :value="$record['xp']" icon="spark" />
        </div>

        {{-- ⭐ الموافقة على النشر: مقفول افتراضيًّا، ويُقفَل بضغطة (9.1 · 10.0-ج) --}}
        <div class="flex flex-wrap items-center gap-2 mt-4 pt-3" style="border-top: 1px solid var(--border)">
            <label class="flex items-center gap-2 text-sm" style="min-height: 44px">
                <input type="checkbox" data-attestation-public class="w-5 h-5" @checked($isPublic)>
                <span>{{ setting('attestations.public.toggle_label', 'شغّل الرابط العامّ للإفادة') }}</span>
            </label>

            <input type="text" readonly data-attestation-url value="{{ $publicUrl }}"
                   class="flex-1 min-w-48 rounded-xl px-3 text-xs font-mono {{ $publicUrl ? '' : 'hidden' }}"
                   style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </div>

        <p class="text-xs mt-2" style="color: var(--text-muted)">
            {{ setting('attestations.public.hint', 'الرابط مقفول لحدّ ما تشغّله بنفسك — وتقدر تقفله في أيّ وقت.') }}
        </p>

        <p class="text-xs mt-3" data-copy-note style="color: var(--color-state-ok)"></p>
    </div>

    {{-- قائمة الإفادات بحالتها (24.5) --}}
    <h2 class="text-sm font-bold mb-2">{{ setting('attestations.list.title', 'إفاداتي') }}</h2>

    @forelse ($requests as $attestation)
        @php $meta = $builder->statusMeta($attestation->status); @endphp
        <article class="card p-4 mb-2">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <div class="font-semibold text-sm">{{ $attestation->from_user?->name ?? $attestation->from_name }}</div>
                    <div class="text-xs" style="color: var(--text-muted)"
                         title="{{ $attestation->created_at?->translatedFormat('j F Y') }}">
                        {{ $attestation->created_at?->diffForHumans() }}
                    </div>
                </div>
                <x-state-badge :state="$meta['state']" :label="$meta['label']" />
            </div>
            @if ($attestation->body)
                <p class="text-sm mt-2 whitespace-pre-line">{{ $attestation->body }}</p>
            @endif
        </article>
    @empty
        <x-empty :message="setting('attestations.empty.message', 'مفيش إفادات لسّه')"
                 :action="setting('attestations.request.action_label', 'اطلب إفادة')"
                 href="#" />
    @endforelse

    {{-- مكان ظهورها (24.5) --}}
    <div class="card p-4 mt-4">
        <div class="text-sm font-semibold mb-2">{{ setting('attestations.placements.title', 'بتظهر فين؟') }}</div>
        <ul class="text-sm space-y-1" style="color: var(--text-muted)">
            @foreach ($placements as $placement)
                <li>• {{ $placement }}</li>
            @endforeach
        </ul>
    </div>

    <x-modal id="attestation-request" :title="setting('attestations.request.action_label', 'اطلب إفادة')">
        <form method="post" action="{{ route('attestations.store') }}" class="space-y-3" id="attestation-form">
            @csrf
            <x-form.input name="from_name" :label="setting('attestations.field.from_label', 'الجهة أو الشخص')" />

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('attestations.field.reason_label', 'سبب الطلب') }}</span>
                <textarea name="reason" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('reason') }}</textarea>
                @error('reason')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('attestations.field.note_label', 'ملاحظة (اختياريّ)') }}</span>
                <textarea name="note" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('note') }}</textarea>
            </label>

            @if ($limitReached)
                <p class="text-xs" style="color: var(--color-state-warn)">
                    {{ setting('attestations.request.limit_message', 'عندك طلبات مفتوحة كتير — استنّى ردّها الأوّل.') }}
                </p>
            @endif
        </form>

        <x-slot:footer>
            <button type="submit" form="attestation-form"
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">
                {{ setting('attestations.request.submit_label', 'إرسال الطلب') }}
            </button>
        </x-slot:footer>
    </x-modal>
@endsection

@section('mobile_action')
    <button type="button" data-modal-open="attestation-request"
            class="btn flex items-center justify-center w-full rounded-xl px-4 py-3 text-sm font-semibold"
            style="background: var(--color-brand-500); color: #04201c">
        {{ setting('attestations.request.action_label', 'اطلب إفادة') }}
    </button>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
    const note = document.querySelector('[data-copy-note]');
    try {
        await navigator.clipboard.writeText(btn.dataset.copy);
        note.textContent = @json($hcWords['attestations.copied_message']);
    } catch {
        note.textContent = btn.dataset.copy;
    }
}));

/* الموافقة على نشر الإفادة: ردٌّ فوريّ، والإغلاق فوريّ كذلك (9.1 · 2.17-ب) */
(function () {
    const toggle = document.querySelector('[data-attestation-public]');
    if (!toggle) return;

    const url = document.querySelector('[data-attestation-url]');
    const copy = document.querySelector('[data-attestation-copy]');
    const note = document.querySelector('[data-copy-note]');

    toggle.addEventListener('change', async () => {
        try {
            const res = await fetch(@json(route('attestations.public.toggle')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ enabled: toggle.checked }),
            });
            const data = await res.json();

            if (data.url) {
                url.value = data.url;
                copy?.setAttribute('data-copy', data.url);
            }
            url.classList.toggle('hidden', !(data.enabled && data.url));
            copy?.classList.toggle('hidden', !(data.enabled && data.url));
            note.textContent = data.message || '';
        } catch {
            toggle.checked = !toggle.checked;
            note.textContent = @json($hcWords['attestations.public.error_message']);
        }
    });
})();
</script>
@endpush
