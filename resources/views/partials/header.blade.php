@php
    $u = auth()->user();
    $unread = $u->notificationsFeed()->whereNull('read_at')->count();
@endphp

{{-- شريط نادي الخامسة العلويّ: يظهر داخل النافذة بتوقيت المستخدم وحدها (7.2) --}}
@include('achievements.components.club-topbar')

<header class="sticky top-0 z-50 flex items-center gap-3 px-4 py-3"
        style="height: var(--header-h); background: var(--surface); border-bottom: 1px solid var(--border)">

    <button type="button" class="md:hidden text-xl" data-drawer-toggle aria-label="القائمة"><x-icon name="menu" size="16" /></button>

    <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '/' }}"
       class="font-extrabold" style="color: var(--color-brand-500)">{{ config('app.name') }}</a>

    <div class="flex-1"></div>

    {{--
      ⭐ البحث الموحّد (2.15-د): على الشاشات الكبيرة يفتح لوحة Ctrl+K،
      وعلى الموبايل يفتح **شاشة البحث الكاملة** كما ينصّ البند حرفيًّا.
    --}}
    <a href="{{ \Illuminate\Support\Facades\Route::has('search') ? route('search') : '#' }}"
       data-palette-open
       class="inline-flex items-center justify-center rounded-xl motion-standard"
       style="min-width: 44px; min-height: 44px; color: var(--text-muted)"
       aria-label="بحث موحّد" title="بحث موحّد (Ctrl+K)">
        {{-- أيقونة SVG مرسومة داخل المشروع — ممنوع أيّ مكتبة أيقونات (2.16-ج) --}}
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
            <circle cx="11" cy="11" r="7" />
            <path d="M20 20l-3.5-3.5" />
        </svg>
    </a>

    {{-- مبدّل سياق العضويّة (قسم/محافظة/ملفّ) — كلّ شيء يُقرأ داخل العضويّة النشطة --}}
    @volunteer
        @if (($userMemberships ?? collect())->count() > 1)
            <form method="get" class="hidden sm:block">
                <select name="membership" onchange="this.form.submit()"
                        class="rounded-xl px-3 py-1.5 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                        aria-label="سياق العضويّة">
                    @foreach ($userMemberships as $m)
                        <option value="{{ $m->id }}" @selected(($activeMembership?->id) === $m->id)>
                            {{ $m->entity?->name_ar }} — {{ $m->position?->name_ar }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    @endvolunteer

    {{-- جرس الإشعارات بتاباته: الكلّ · المنصّة · التطوّع (2.8) --}}
    <div class="relative" x-data="{ open: false }">
        <button type="button" class="relative text-xl" data-bell aria-label="الإشعارات">
            <x-icon name="bell" size="16" />
            @if ($unread > 0)
                <span class="absolute -top-1 -end-1 text-[10px] rounded-full px-1.5"
                      style="background: var(--color-state-danger); color: #fff">{{ $unread }}</span>
            @endif
        </button>
        @include('partials.bell')
    </div>

    {{--
      أفاتار 36px (`size="9"`) داخل رابطٍ بلا مقاس ⟵ هدف لمسٍ **36×64**:
      عريضه دون 44 خلافًا لـ2.15-ج. والمقاس على **الرابط** لا على الأفاتار،
      فيبقى شكل الصورة كما هو (2.10.1-16: بلا هالة ولا تكبير) ويكبر الهدف.
    --}}
    <a href="{{ \Illuminate\Support\Facades\Route::has('profile.me') ? route('profile.me') : '#' }}"
       class="inline-flex items-center justify-center shrink-0"
       style="min-inline-size: var(--touch-min, 44px); min-block-size: var(--touch-min, 44px)"
       aria-label="{{ $u->shortName() }}">
        <x-avatar :user="$u" size="9" />
    </a>
</header>
