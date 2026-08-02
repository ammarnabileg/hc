@extends('layouts.app')
@section('title', $owner->shortName().' — بروفايل')
@section('meta_description', 'بروفايل '.$owner->shortName().' على '.config('app.name'))

@section('content')
    @include('profile.partials.header')

    {{--
      تابات Sticky (2.10.1-14): نظرة عامّة · الإنجازات · الشهادات · خبراتي —
      ⭐ ثمّ تابات التطوّع (13.4-م) يحقنها **مجال التطوّع** في هذا الستاك،
      فترتيبها بعد الأربعة دائمًا وبلا أن يعرف بها غير المتطوّع (10.0-د).
    --}}
    <div class="sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)">
        <div class="flex gap-2 overflow-x-auto no-scrollbar">
            @foreach ($tabs as $item)
                <a href="{{ $isOwner ? route('profile.me', ['tab' => $item['key']]) : route('u.profile', ['code' => $owner->code, 'tab' => $item['key']]) }}"
                   class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard"
                   style="{{ $tab === $item['key']
                        ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                        : 'background: var(--surface-raised); color: var(--text)' }}">{{ $item['label'] }}</a>
            @endforeach

            @stack('volunteer_profile_tabs')
        </div>
    </div>

    {{-- تحميل كسول: التاب المفتوح وحده هو المحمَّل (2.15-د) --}}
    @switch($tab)
        @case('achievements')
            @include('profile.partials.tab-achievements')
            @break
        @case('certificates')
            @include('profile.partials.tab-certificates')
            @break
        @case('experience')
            @include('profile.partials.tab-experience')
            @break
        @default
            @include('profile.partials.tab-overview')
    @endswitch

    {{-- محتوى تابات التطوّع يُحقَن هنا من مجاله (10.0-أ) --}}
    @stack('volunteer_profile_content')
@endsection

@push('scripts')
    <script>
        // [نسخ رابطي] — ردّ فوريّ لكلّ فعل (2.17-ب)
        document.querySelector('[data-copy-profile]')?.addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            const link = btn.dataset.copyProfile;
            try {
                await navigator.clipboard.writeText(link);
            } catch {
                window.prompt('انسخ رابطك', link);
            }
            const original = btn.textContent;
            btn.textContent = 'اتنسخ ✓';
            setTimeout(() => { btn.textContent = original; }, 2000);
        });
    </script>
@endpush
