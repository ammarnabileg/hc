@extends('layouts.app')
@section('title', $owner->shortName().' — بروفايل')
@section('meta_description', 'بروفايل '.$owner->shortName().' على '.config('app.name'))

{{-- ⭐ صورة OG لرابط البروفايل — فيظهر كبطاقة مصمَّمة لا رابطًا أصلع (21.1-أ) --}}
@section('og_image', route('growth.og.profile', $owner->code))

@section('content')
    @include('profile.partials.header')

    {{-- سكشن الشارات **تحت الهيدر** مباشرةً (10) --}}
    @include('profile.partials.badges')

    {{-- بوب-أب مشاركة الحساب (10) --}}
    @include('profile.partials.share')

    {{--
      تابات Sticky (2.10.1-14): نظرة عامّة · الإنجازات · الشهادات · خبراتي —
      ⭐ ثمّ تابات التطوّع (13.4-م) يحقنها **مجال التطوّع** في هذا الستاك،
      فترتيبها بعد الأربعة دائمًا وبلا أن يعرف بها غير المتطوّع (10.0-د).
    --}}
    <div class="sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)">
        <div class="flex gap-2 min-w-0 overflow-x-auto no-scrollbar">
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
        @case('details')
            @include('profile.partials.tab-details')
            @break
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
        document.querySelectorAll('[data-copy-profile]').forEach((el) => el.addEventListener('click', async (e) => {
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
        }));

        // النبذة (10): حفظ تلقائيّ مع «اتحفظ ✓» بجوار الحقل (2.17-ب)
        (() => {
            const field = document.querySelector('[data-bio-field]');
            const saved = document.querySelector('[data-bio-saved]');
            if (!field) return;

            let timer = null;
            field.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(async () => {
                    const res = await fetch(@json(route('profile.bio')), {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({ bio: field.value }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (saved) {
                        saved.textContent = res.ok ? (data.label || 'اتحفظ ✓') : (data.message?.bio?.[0] || 'مقدرناش نحفظ — جرّب تاني.');
                        saved.style.visibility = 'visible';
                        setTimeout(() => { saved.style.visibility = 'hidden'; }, 2500);
                    }

                    // ⭐ فعل قابل للتراجع: يُنفَّذ فورًا ومعه «تراجع» في الـToast (2.15-د)
                    if (res.ok && data.undo_token) {
                        window.dispatchEvent(new CustomEvent('ui:undoable', {
                            detail: { token: data.undo_token, message: 'النبذة اتحدّثت' },
                        }));
                    }
                }, 700);
            });
        })();

        // بوب-أب الشارة: صورة + اسم + وصف (10)
        document.querySelectorAll('[data-badge-open]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const modal = document.getElementById('badge-detail');
                if (!modal) return;
                const icon = modal.querySelector('[data-badge-detail-icon]');
                const locked = btn.dataset.badgeLocked === '1';
                modal.querySelector('[data-badge-detail-name]').textContent = btn.dataset.badgeName || '';
                modal.querySelector('[data-badge-detail-desc]').textContent = btn.dataset.badgeDesc || '';
                modal.querySelector('[data-badge-detail-state]').textContent = locked ? '🔒 مقفولة — الشرط فوق' : '★ مفتوحة';
                if (btn.dataset.badgeIcon) {
                    icon.src = btn.dataset.badgeIcon;
                    icon.classList.remove('hidden');
                    icon.style.filter = locked ? 'grayscale(1)' : '';
                } else {
                    icon.classList.add('hidden');
                }
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });
    </script>
@endpush
