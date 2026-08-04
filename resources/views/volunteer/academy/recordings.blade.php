@extends('layouts.volunteer')

@section('title', setting('volunteer.academy_recordings.title', 'التسجيلات'))

@php
    /**
     * الأكاديمية ← التسجيلات (13.4-ل · 24.4-9).
     * الكسب **مرّة واحدة لكلّ تسجيل** وبتحقّق **Server-side** — والشارة تقول ذلك صراحةً.
     */
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.academy_recordings.title', 'التسجيلات')"
        :subtitle="$recordings->count().setting('volunteer.academy_recordings.subtitle', ' تسجيلًا في قسمك')"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.academy_recordings.label', 'الأكاديمية')], ['label' => setting('volunteer.academy_recordings.title', 'التسجيلات')]]" />

    <x-tabs current="recordings" :tabs="[
        ['key' => 'paths', 'label' => setting('volunteer.academy_recordings.label_2', 'التدريبات'), 'url' => route('volunteer.academy')],
        ['key' => 'recordings', 'label' => setting('volunteer.academy_recordings.title', 'التسجيلات'), 'url' => route('volunteer.academy.recordings')],
    ]" />

    <x-filters :action="route('volunteer.academy.recordings')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.academy_recordings.field', 'الترتيب') }}</span>
            <select name="sort" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="newest" @selected($filters['sort'] === 'newest')>{{ setting('volunteer.academy_recordings.option', 'الأحدث') }}</option>
                <option value="most_viewed" @selected($filters['sort'] === 'most_viewed')>{{ setting('volunteer.academy_recordings.option_2', 'الأكثر مشاهدة') }}</option>
            </select>
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="has_otp" value="1" @checked($filters['has_otp']) onchange="this.form.submit()">
            {{ setting('volunteer.academy_recordings.field_2', 'له رمز') }}
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('volunteer.academy_recordings.placeholder', 'عنوان التسجيل…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($recordings->isEmpty())
        <x-empty :message="setting('volunteer.academy_recordings.empty', 'مفيش تسجيلات في قسمك بعد')" />
    @else
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($recordings as $recording)
                @php $isClaimed = in_array((int) $recording->id, $claimed, true); @endphp

                <article class="card p-4 animate-fadeup">
                    <h2 class="font-bold text-sm">{{ $recording->title }}</h2>
                    <p class="mt-1 text-xs" style="color: var(--text-muted)">
                        {{ $recording->source === 'youtube' ? setting('volunteer.academy_recordings.text', 'يوتيوب') : setting('volunteer.academy_recordings.text_2', 'درايف') }} ·
                        {{ $recording->created_at?->translatedFormat('j F Y') }}
                        @if ($recording->host_name) · {{ setting('volunteer.academy_recordings.field_3', 'المضيف') }} {{ $recording->host_name }} @endif
                    </p>

                    @if ($recording->grantsPoints())
                        <div class="mt-2">
                            <x-state-badge state="honor" :label="$badge" />
                        </div>
                    @endif

                    @if ($isClaimed)
                        <div class="mt-2"><x-state-badge state="ok" :label="setting('volunteer.academy_recordings.label_3', 'تمّ')" /></div>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ $recording->url }}" target="_blank" rel="noopener"
                           class="btn rounded-xl px-3 py-2 text-sm font-semibold"
                           style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.academy_recordings.link', 'شاهد') }}</a>

                        @if ($recording->grantsPoints() && ! $isClaimed)
                            <button type="button" class="btn rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)"
                                    data-otp-open="{{ route('volunteer.academy.recordings.claim', $recording) }}">{{ setting('volunteer.academy_recordings.action', 'أدخل الرمز') }}</button>
                        @endif

                        <form method="post" action="{{ route('volunteer.academy.recordings.report', $recording) }}">
                            @csrf
                            <button type="submit" class="btn rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">
                                {{ setting('volunteer.academy_recordings.action_2', 'أبلغ عن رابط معطّل') }}
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    {{-- بوب-أب OTP — والتحقّق Server-side لا في المتصفّح (13.4-ل) --}}
    <x-modal id="otp-modal" :title="setting('volunteer.academy_recordings.tooltip', 'رمز التسجيل')">
        <form method="post" data-otp-form class="space-y-3">
            @csrf
            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.academy_recordings.field_4', 'اكتب الرمز اللي ظهر في آخر التسجيل') }}</span>
                <input type="text" name="otp" required inputmode="numeric" class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            </label>
            <p class="text-xs" data-otp-message style="color: var(--text-muted)"></p>
            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.academy_recordings.action_3', 'تأكيد') }}</button>
        </form>
    </x-modal>
@endsection

@push('scripts')
@php
    /** نصوص السكربت — تُمرَّر بـ`@json` فلا يبقى حرفٌ عربيّ محروق داخله (2.13-أ) */
    $jsText = [
        'network_error' => (string) setting('volunteer.academy_recordings.js_network_error', 'الشبكة وقعت — جرّب تاني بعد شويّة.'),
    ];
@endphp

<script>
const T = @json($jsText);
(function () {
    const modal = document.getElementById('otp-modal');
    const form = modal?.querySelector('[data-otp-form]');
    const message = modal?.querySelector('[data-otp-message]');
    if (!modal || !form) return;

    document.querySelectorAll('[data-otp-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            form.action = btn.dataset.otpOpen;
            if (message) message.textContent = '';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form),
        })
            .then((r) => r.json())
            .then((json) => {
                if (message) message.textContent = json.message || '';
                if (json.result === 'ok') setTimeout(() => location.reload(), 1200);
            })
            .catch(() => { if (message) message.textContent = T.network_error; });
    });
})();
</script>
@endpush
